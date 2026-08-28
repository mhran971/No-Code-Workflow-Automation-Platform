<?php

namespace Modules\Workflows\Services\Execution;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Workflows\app\Enums\ResultKind;
use Modules\Workflows\Enums\NodeExecutionStatus;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Events\NodeCompleted;
use Modules\Workflows\Events\NodeFailed;
use Modules\Workflows\Events\NodeWaiting;
use Modules\Workflows\Jobs\ExecuteNodeJob;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Services\Execution\Concerns\DispatchesNodes;
use Modules\Workflows\Services\Execution\Concerns\NodeSeeder;
use Modules\Workflows\Services\Execution\Data\NodeExecutionResult;
use Modules\Workflows\Services\Execution\Data\PlanEdge;

/**
 * Processes the result of a single node execution: updates execution status,
 * seeds child executions, handles retries, and delegates instance transitions
 * to InstanceLifecycleManager.
 */
class WorkflowExecutionEngine
{
    use DispatchesNodes, NodeSeeder;

    public function __construct(
        protected NodeExecutorRegistry $registry,
        protected RetryPolicy $retryPolicy,
        protected MergeCoordinator $mergeCoordinator,
        protected EventBroadcaster $broadcaster,
        protected InstanceLifecycleManager $lifecycle,
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

    public function transitionTo(
        WorkflowInstance $instance,
        WorkflowInstanceStatus $to,
        ?array $error = null,
    ): void {
        $this->lifecycle->transitionTo($instance, $to, $error);
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
            $this->lifecycle->transitionTo($instance, WorkflowInstanceStatus::Running);
            $category = $this->resolveCategory($nodeType);
            ExecuteNodeJob::dispatch($retry->id, $category->value)
                ->onQueue($this->queueFor($category));
        }
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

        if ($instance->isDirty(['context', 'customer_id'])) {
            $instance->save();
        }

        $this->broadcaster->broadcast(new NodeCompleted($instance, $execution));

        if (empty($result->edges)) {
            if (! $this->lifecycle->hasLiveExecutions($instance)) {
                $this->lifecycle->transitionTo($instance, WorkflowInstanceStatus::Completed);
            }

            return;
        }

        foreach ($result->edges as $edge) {
            $this->seedNextExecution($execution, $instance, $edge, $plan, $this->mergeCoordinator);
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
        $this->lifecycle->transitionTo($instance, WorkflowInstanceStatus::Completed);
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

        // A `paused` instance was halted deliberately by its executor (e.g. dynamic-flow awaiting a
        // manager's design) and owns that state — don't clobber it with the generic `waiting` status.
        if ($instance->status !== WorkflowInstanceStatus::Paused
            && ! $this->lifecycle->hasNonWaitingLiveExecutions($instance)) {
            $this->lifecycle->transitionTo($instance, WorkflowInstanceStatus::Waiting);
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
            $this->scheduleRetryExecution($execution, $instance);

            return;
        }

        $errorEdges = array_filter(
            $plan->outgoing($execution->node_key),
            fn (PlanEdge $e) => $e->isErrorEdge(),
        );

        if (! empty($errorEdges)) {
            foreach ($errorEdges as $edge) {
                $this->seedNextExecution($execution, $instance, $edge, $plan, $this->mergeCoordinator);
            }

            return;
        }

        $this->lifecycle->transitionTo($instance, WorkflowInstanceStatus::Failed, $errorPayload);
    }

    protected function onConsume(WorkflowNodeExecution $execution): void
    {
        $execution->update([
            'status' => NodeExecutionStatus::Consumed,
            'finished_at' => now(),
        ]);
    }
}
