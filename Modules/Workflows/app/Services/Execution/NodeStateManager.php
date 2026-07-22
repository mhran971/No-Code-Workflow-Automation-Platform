<?php

namespace Modules\Workflows\Services\Execution;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Modules\Workflows\app\Enums\ResultKind;
use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Enums\NodeExecutionStatus;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Events\ChildInstanceEventForwarded;
use Modules\Workflows\Events\NodeCompleted;
use Modules\Workflows\Events\NodeFailed;
use Modules\Workflows\Events\NodeRetrying;
use Modules\Workflows\Events\NodeWaiting;
use Modules\Workflows\Jobs\ExecuteNodeJob;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Throwable;

class NodeStateManager
{
    public function __construct(
        protected NodeExecutorRegistry $registry,
        protected RetryPolicy $retryPolicy,
        protected MergeCoordinator $mergeCoordinator,
        protected InstanceStateManager $stateManager,
        protected string $controlQueue,
        protected string $actionQueue,
    ) {}

    public function claim(int $executionId): ?WorkflowNodeExecution
    {
        return DB::transaction(function () use ($executionId): ?WorkflowNodeExecution {
            $execution = WorkflowNodeExecution::query()->lockForUpdate()->find($executionId);

            if ($execution === null || ! $execution->isRunnable()) {
                return null;
            }

            $execution->update([
                'status' => NodeExecutionStatus::Running,
                'started_at' => $execution->started_at ?? now(),
            ]);

            return $execution;
        });
    }

    public function handleResult(
        WorkflowNodeExecution $execution,
        WorkflowInstance $instance,
        ExecutionPlan $plan,
        NodeExecutionContext $context,
        NodeExecutionResult $result,
    ): void {
        match ($result->kind) {
            ResultKind::Proceed, ResultKind::Branch => $this->onSucceed($execution, $instance, $plan, $context, $result),
            ResultKind::Terminate => $this->onTerminate($execution, $instance),
            ResultKind::Wait => $this->onWait($execution, $instance, $result),
            ResultKind::Fail => $this->onFail($execution, $instance, $plan, $result),
            ResultKind::Noop => $this->onConsume($execution),
        };
    }

    protected function onSucceed(
        WorkflowNodeExecution $execution,
        WorkflowInstance $instance,
        ExecutionPlan $plan,
        NodeExecutionContext $context,
        NodeExecutionResult $result,
    ): void {
        $execution->update([
            'status' => NodeExecutionStatus::Succeeded,
            'output' => $result->output,
            'finished_at' => now(),
        ]);

        if (! empty($result->output)) {
            $context->setContextValue((string) $execution->node_key, $result->output);
        }

        if ($instance->isDirty('context')) {
            $instance->save();
        }

        $this->broadcast(new NodeCompleted($instance, $execution));

        if (empty($result->edges)) {
            if (! $this->hasLiveExecutions($instance)) {
                $this->stateManager->transitionTo($instance, WorkflowInstanceStatus::Completed);
            }

            return;
        }

        foreach ($result->edges as $edge) {
            $this->seedExecution($execution, $instance, $edge, $plan);
        }
    }

    protected function onTerminate(
        WorkflowNodeExecution $execution,
        WorkflowInstance $instance,
    ): void {
        $execution->update([
            'status' => NodeExecutionStatus::Succeeded,
            'finished_at' => now(),
        ]);

        $this->broadcast(new NodeCompleted($instance, $execution));
        $this->stateManager->transitionTo($instance, WorkflowInstanceStatus::Completed);
    }

    protected function onWait(
        WorkflowNodeExecution $execution,
        WorkflowInstance $instance,
        NodeExecutionResult $result,
    ): void {
        $execution->update([
            'status' => NodeExecutionStatus::Waiting,
            'wait_until' => $result->waitUntil,
            'wait_type' => $result->waitType,
        ]);

        if (! $this->hasNonWaitingLiveExecutions($instance)) {
            $this->stateManager->transitionTo($instance, WorkflowInstanceStatus::Waiting);
        }

        $this->broadcast(new NodeWaiting($instance, $execution));
    }

    protected function onFail(
        WorkflowNodeExecution $execution,
        WorkflowInstance $instance,
        ExecutionPlan $plan,
        NodeExecutionResult $result,
    ): void {
        $errorPayload = [
            'message' => $result->errorMessage ?? 'Unknown error',
            'node_key' => $execution->node_key,
            'node_type' => $execution->node_type,
            'attempt' => $execution->attempt,
        ];

        if ($result->error !== null) {
            $errorPayload['exception'] = get_class($result->error);
            $errorPayload['file'] = $result->error->getFile().':'.$result->error->getLine();
        }

        Log::error('workflow.node.failed', array_merge($errorPayload, [
            'instance_id' => $instance->id,
            'execution_id' => $execution->id,
            'retryable' => $result->retryable,
            'trace' => $result->error?->getTraceAsString(),
        ]));

        $execution->update([
            'status' => NodeExecutionStatus::Failed,
            'error' => $errorPayload,
            'finished_at' => now(),
        ]);

        $willRetry = $result->retryable && $this->retryPolicy->shouldRetry($execution->attempt);

        $this->broadcast(new NodeFailed($instance, $execution, $willRetry));

        if ($willRetry) {
            $this->scheduleRetry($execution, $instance);

            return;
        }

        $errorEdges = array_filter(
            $plan->outgoing($execution->node_key),
            fn (PlanEdge $e) => $e->isErrorEdge(),
        );

        if (! empty($errorEdges)) {
            foreach ($errorEdges as $edge) {
                $this->seedExecution($execution, $instance, $edge, $plan);
            }

            return;
        }

        $this->stateManager->transitionTo($instance, WorkflowInstanceStatus::Failed, $errorPayload);
    }

    protected function onConsume(WorkflowNodeExecution $execution): void
    {
        $execution->update([
            'status' => NodeExecutionStatus::Consumed,
            'finished_at' => now(),
        ]);
    }

    protected function seedExecution(
        WorkflowNodeExecution $parent,
        WorkflowInstance $instance,
        PlanEdge $edge,
        ExecutionPlan $plan,
    ): void {
        $nodeKey = $edge->target;
        $nodeType = $plan->nodeType($nodeKey) ?? 'unknown';

        if ($plan->isMergeNode($nodeKey)) {
            $this->mergeCoordinator->arrive($parent, $instance, $nodeKey, $nodeType, $plan);

            return;
        }

        $idempotencyKey = hash('sha256', $parent->idempotency_key.':'.$nodeKey.':1');

        $child = WorkflowNodeExecution::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'instance_id' => $instance->id,
                'tenant_id' => $instance->tenant_id,
                'node_key' => $nodeKey,
                'node_type' => $nodeType,
                'status' => NodeExecutionStatus::Pending,
                'attempt' => 1,
                'parent_execution_id' => $parent->id,
                'input' => $parent->output ?? [],
            ],
        );

        if ($child->wasRecentlyCreated) {
            $category = $this->resolveCategory($nodeType);
            ExecuteNodeJob::dispatch($child->id, $category->value)
                ->onQueue($this->queueFor($category));
        }
    }

    protected function scheduleRetry(
        WorkflowNodeExecution $failed,
        WorkflowInstance $instance,
    ): void {
        $nextAttempt = $failed->attempt + 1;
        $idempotencyKey = hash('sha256', $instance->id.':'.$failed->node_key.':'.$nextAttempt);

        $retry = WorkflowNodeExecution::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'instance_id' => $instance->id,
                'tenant_id' => $instance->tenant_id,
                'node_key' => $failed->node_key,
                'node_type' => $failed->node_type,
                'status' => NodeExecutionStatus::Pending,
                'attempt' => $nextAttempt,
                'parent_execution_id' => $failed->parent_execution_id,
                'input' => $failed->input ?? [],
            ],
        );

        if ($retry->wasRecentlyCreated) {
            $this->broadcast(new NodeRetrying($instance, $failed, $retry));

            $delay = $this->retryPolicy->delaySeconds($nextAttempt);
            $category = $this->resolveCategory($failed->node_type);
            ExecuteNodeJob::dispatch($retry->id, $category->value)
                ->onQueue($this->queueFor($category))
                ->delay(now()->addSeconds($delay));
        }
    }

    protected function hasLiveExecutions(WorkflowInstance $instance): bool
    {
        return WorkflowNodeExecution::query()
            ->where('instance_id', $instance->id)
            ->whereIn('status', [
                NodeExecutionStatus::Pending->value,
                NodeExecutionStatus::Running->value,
                NodeExecutionStatus::Waiting->value,
            ])
            ->exists();
    }

    protected function hasNonWaitingLiveExecutions(WorkflowInstance $instance): bool
    {
        return WorkflowNodeExecution::query()
            ->where('instance_id', $instance->id)
            ->whereIn('status', [
                NodeExecutionStatus::Pending->value,
                NodeExecutionStatus::Running->value,
            ])
            ->exists();
    }

    protected function broadcast(object $event): void
    {
        DB::afterCommit(function () use ($event) {
            Event::dispatch($event);

            $instance = $event->instance ?? null;
            if ($instance instanceof WorkflowInstance && $instance->parent_instance_id) {
                $parentInstance = WorkflowInstance::find($instance->parent_instance_id);
                if ($parentInstance) {
                    Log::info('workflow.child_event.forwarding', [
                        'child_instance_id' => $instance->id,
                        'parent_instance_id' => $parentInstance->id,
                        'event' => $event->broadcastAs(),
                    ]);
                    Event::dispatch(new ChildInstanceEventForwarded(
                        $instance,
                        $parentInstance,
                        $event->broadcastAs(),
                        $event->broadcastWith(),
                    ));
                }
            }
        });
    }

    protected function resolveCategory(string $nodeType): NodeCategory
    {
        if ($this->registry->has($nodeType)) {
            return $this->registry->for($nodeType)->category();
        }

        return NodeCategory::Logic;
    }

    protected function queueFor(NodeCategory $category): string
    {
        return $category === NodeCategory::Action || $category === NodeCategory::Ai
            ? $this->actionQueue
            : $this->controlQueue;
    }
}
