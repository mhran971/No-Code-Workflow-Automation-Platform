<?php

namespace Modules\Workflows\Services\Execution;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Workflows\app\Enums\ResultKind;
use Modules\Workflows\Enums\DynamicFlowStatus;
use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Enums\NodeExecutionStatus;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Events\InstanceCancelled;
use Modules\Workflows\Events\InstanceCompleted;
use Modules\Workflows\Events\InstanceFailed;
use Modules\Workflows\Events\NodeCompleted;
use Modules\Workflows\Events\NodeFailed;
use Modules\Workflows\Events\NodeRetrying;
use Modules\Workflows\Events\NodeWaiting;
use Modules\Workflows\Jobs\ExecuteNodeJob;
use Modules\Workflows\Models\WorkflowDynamicFlow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Models\WorkflowTask;
use Modules\Workflows\Services\Execution\Data\NodeExecutionResult;
use Modules\Workflows\Services\Execution\Data\PlanEdge;

class WorkflowExecutionEngine
{
    protected array $allowed = [
        'pending'   => ['running', 'failed', 'cancelled'],
        'running'   => ['completed', 'failed', 'waiting', 'cancelled'],
        'waiting'   => ['running', 'failed', 'cancelled'],
        'paused'    => ['running'],
        'failed'    => ['running'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function __construct(
        protected NodeExecutorRegistry $registry,
        protected RetryPolicy $retryPolicy,
        protected MergeCoordinator $mergeCoordinator,
        protected EventBroadcaster $broadcaster,
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

        $this->broadcaster->broadcast(new NodeCompleted($instance, $execution));

        if (empty($result->edges)) {
            if (! $this->hasLiveExecutions($instance)) {
                $this->transitionTo($instance, WorkflowInstanceStatus::Completed);
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

        $this->broadcaster->broadcast(new NodeCompleted($instance, $execution));
        $this->transitionTo($instance, WorkflowInstanceStatus::Completed);
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
            $this->transitionTo($instance, WorkflowInstanceStatus::Waiting);
        }

        $this->broadcaster->broadcast(new NodeWaiting($instance, $execution));
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

        $this->broadcaster->broadcast(new NodeFailed($instance, $execution, $willRetry));

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

        $this->transitionTo($instance, WorkflowInstanceStatus::Failed, $errorPayload);
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

        $child = $this->createExecutionRow(
            $instance,
            $idempotencyKey,
            $nodeKey,
            $nodeType,
            1,
            $parent->id,
            $parent->output ?? [],
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

        $retry = $this->createExecutionRow(
            $instance,
            $idempotencyKey,
            $failed->node_key,
            $failed->node_type,
            $nextAttempt,
            $failed->parent_execution_id,
            $failed->input ?? [],
        );

        if ($retry->wasRecentlyCreated) {
            $this->broadcaster->broadcast(new NodeRetrying($instance, $failed, $retry));

            $delay = $this->retryPolicy->delaySeconds($nextAttempt);
            $category = $this->resolveCategory($failed->node_type);
            ExecuteNodeJob::dispatch($retry->id, $category->value)
                ->onQueue($this->queueFor($category))
                ->delay(now()->addSeconds($delay));
        }
    }

    public function retryFromNode(WorkflowInstance $instance, string $nodeKey, ExecutionPlan $plan): void
    {
        $nodeType = $plan->nodeType($nodeKey) ?? 'unknown';

        $lastExecution = WorkflowNodeExecution::query()
            ->where('instance_id', $instance->id)
            ->where('node_key', $nodeKey)
            ->orderByDesc('attempt')
            ->first();

        $nextAttempt = ($lastExecution?->attempt ?? 0) + 1;
        $idempotencyKey = hash('sha256', $instance->id.':'.$nodeKey.':'.$nextAttempt);

        $retry = $this->createExecutionRow(
            $instance,
            $idempotencyKey,
            $nodeKey,
            $nodeType,
            $nextAttempt,
            $lastExecution?->parent_execution_id,
            $lastExecution?->input ?? [],
        );

        if ($retry->wasRecentlyCreated) {
            $this->transitionTo($instance, WorkflowInstanceStatus::Running);
            $category = $this->resolveCategory($nodeType);
            ExecuteNodeJob::dispatch($retry->id, $category->value)
                ->onQueue($this->queueFor($category));
        }
    }

    public function transitionTo(
        WorkflowInstance $instance,
        WorkflowInstanceStatus $to,
        ?array $error = null,
    ): void {
        $this->assertTransition($instance, $to);

        match ($to) {
            WorkflowInstanceStatus::Completed => $this->complete($instance),
            WorkflowInstanceStatus::Failed => $this->fail($instance, $error ?? []),
            WorkflowInstanceStatus::Cancelled => $this->cancel($instance),
            WorkflowInstanceStatus::Waiting => $this->markWaiting($instance),
            WorkflowInstanceStatus::Running => $this->resume($instance),
        };
    }

    protected function complete(WorkflowInstance $instance): void
    {
        $instance->forceFill([
            'status' => WorkflowInstanceStatus::Completed,
            'finished_at' => $instance->finished_at ?? now(),
        ])->save();

        $instance->workflow()->decrement('active_instances');

        $this->broadcaster->broadcast(new InstanceCompleted($instance));
        $this->wakeParentExecution($instance);
    }

    protected function fail(WorkflowInstance $instance, array $error): void
    {
        WorkflowNodeExecution::query()
            ->where('instance_id', $instance->id)
            ->where('status', NodeExecutionStatus::Waiting->value)
            ->update(['status' => NodeExecutionStatus::Consumed->value, 'finished_at' => now()]);

        $instance->forceFill([
            'status' => WorkflowInstanceStatus::Failed,
            'error' => $error,
            'finished_at' => now(),
        ])->save();

        $this->broadcaster->broadcast(new InstanceFailed($instance));
        $this->wakeParentExecution($instance);
    }

    protected function cancel(WorkflowInstance $instance): void
    {
        WorkflowNodeExecution::query()
            ->where('instance_id', $instance->id)
            ->whereIn('status', [
                NodeExecutionStatus::Pending->value,
                NodeExecutionStatus::Running->value,
                NodeExecutionStatus::Waiting->value,
            ])
            ->update(['status' => NodeExecutionStatus::Consumed->value, 'finished_at' => now()]);

        WorkflowTask::query()
            ->where('instance_id', $instance->id)
            ->where('status', 'open')
            ->update(['status' => 'cancelled']);

        $instance->forceFill([
            'status' => WorkflowInstanceStatus::Cancelled,
            'finished_at' => now(),
        ])->save();

        $this->broadcaster->broadcast(new InstanceCancelled($instance));
    }

    protected function markWaiting(WorkflowInstance $instance): void
    {
        $instance->forceFill([
            'status' => WorkflowInstanceStatus::Waiting,
        ])->save();
    }

    protected function resume(WorkflowInstance $instance): void
    {
        $instance->forceFill([
            'status' => WorkflowInstanceStatus::Running,
        ])->save();
    }

    protected function assertTransition(WorkflowInstance $instance, WorkflowInstanceStatus $to): void
    {
        $from = $instance->status?->value ?? 'pending';
        $toValue = $to->value;
        $allowedFrom = $this->allowed[$from] ?? [];

        if (! in_array($toValue, $allowedFrom, true)) {
            throw new \LogicException(
                "Cannot transition workflow instance [{$instance->id}] from '{$from}' to '{$toValue}'."
            );
        }
    }

    protected function wakeParentExecution(WorkflowInstance $instance): void
    {
        $parentExecutionId = $instance->parent_execution_id;

        if ($parentExecutionId === null) {
            $dynamicFlow = WorkflowDynamicFlow::query()
                ->where('child_instance_id', $instance->id)
                ->where('status', DynamicFlowStatus::Executing)
                ->first();
            if ($dynamicFlow !== null) {
                $parentExecutionId = $dynamicFlow->execution_id;
            }
        }

        if ($parentExecutionId === null) {
            return;
        }

        $parentExecution = WorkflowNodeExecution::query()->find($parentExecutionId);
        if ($parentExecution === null || $parentExecution->status !== NodeExecutionStatus::Waiting) {
            return;
        }

        $parentExecution->update([
            'status' => NodeExecutionStatus::Pending,
            'wait_until' => null,
        ]);

        $category = $this->resolveCategory($parentExecution->node_type);
        ExecuteNodeJob::dispatch($parentExecution->id, $category->value)
            ->onQueue($this->queueFor($category));
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

    protected function createExecutionRow(
        WorkflowInstance $instance,
        string $idempotencyKey,
        string $nodeKey,
        string $nodeType,
        int $attempt,
        ?int $parentExecutionId,
        array $input,
    ): WorkflowNodeExecution {
        return WorkflowNodeExecution::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'instance_id' => $instance->id,
                'tenant_id' => $instance->tenant_id,
                'node_key' => $nodeKey,
                'node_type' => $nodeType,
                'status' => NodeExecutionStatus::Pending,
                'attempt' => $attempt,
                'parent_execution_id' => $parentExecutionId,
                'input' => $input,
            ],
        );
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
