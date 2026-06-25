<?php

namespace Modules\Workflows\Services\Execution;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Enums\NodeExecutionStatus;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Events\InstanceCompleted;
use Modules\Workflows\Events\InstanceFailed;
use Modules\Workflows\Events\NodeCompleted;
use Modules\Workflows\Events\NodeFailed;
use Modules\Workflows\Events\NodeRetrying;
use Modules\Workflows\Events\NodeStarted;
use Modules\Workflows\Jobs\ExecuteNodeJob;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Services\Execution\Expression\ExpressionEvaluator;
use Modules\Workflows\Services\Execution\Expression\TemplateInterpolator;
use Throwable;

/**
 * The advance loop: claim an execution, run its executor, persist the result, fan out to successors.
 *
 * Every step is a persisted state transition — any worker can resume any instance after a crash.
 * Side effects (executor->execute()) happen outside the database lock; the lock is held only to
 * read/transition status and to commit the result.
 */
class WorkflowRuntime
{
    public function __construct(
        protected ExecutionPlanCompiler $compiler,
        protected NodeExecutorRegistry $registry,
        protected ExpressionEvaluator $evaluator,
        protected TemplateInterpolator $interpolator,
        protected FailureClassifier $classifier,
        protected RetryPolicy $retryPolicy,
    ) {}

    /**
     * Advance a single execution: claim → run → commit result.
     */
    public function advance(int $executionId): void
    {
        // Step 1: Atomically claim the execution (pending/waiting → running).
        $execution = $this->claim($executionId);
        if ($execution === null) {
            return; // already running, succeeded, or failed — idempotency guard
        }

        // Step 2: Compile the plan and build the execution context.
        $instance = WorkflowInstance::query()
            ->with('workflowVersion')
            ->findOrFail($execution->instance_id);

        $plan = $this->compiler->compileVersion($instance->workflowVersion);
        $context = new NodeExecutionContext($instance, $execution, $plan, $this->evaluator, $this->interpolator);

        Event::dispatch(new NodeStarted($instance, $execution));

        // Step 3: Run the executor outside the lock (side effects may be slow / network I/O).
        $executor = $this->registry->for($execution->node_type);

        try {
            $result = $executor->execute($context);
        } catch (Throwable $e) {
            $result = NodeExecutionResult::fail($e, $this->classifier->isRetryable($e));
        }

        // Step 4: Re-lock and commit the result atomically.
        DB::transaction(function () use ($executionId, $instance, $plan, $context, $result): void {
            $execution = WorkflowNodeExecution::query()->lockForUpdate()->findOrFail($executionId);

            // Concurrent-safety guard: if another worker already committed, skip.
            if ($execution->status !== NodeExecutionStatus::Running) {
                return;
            }

            $this->applyResult($execution, $instance, $plan, $context, $result);
        });
    }

    /**
     * Atomically transition a runnable execution to `running`. Returns null if not claimable.
     */
    protected function claim(int $executionId): ?WorkflowNodeExecution
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

    protected function applyResult(
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
            ResultKind::Fail => $this->onFail($execution, $instance, $context, $result),
            ResultKind::Noop => null,
        };
    }

    // ─── Outcome handlers ────────────────────────────────────────────────────

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

        // Flush any context mutations the executor buffered on the instance.
        if ($instance->isDirty('context')) {
            $instance->save();
        }

        Event::dispatch(new NodeCompleted($instance, $execution));

        if (empty($result->edges)) {
            // No successors — this is a structural terminal or the workflow ends here.
            if (! $this->hasLiveExecutions($instance)) {
                $this->completeInstance($instance);
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

        Event::dispatch(new NodeCompleted($instance, $execution));
        $this->completeInstance($instance);
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

        // If all live executions are now waiting, reflect that on the instance.
        if (! $this->hasNonWaitingLiveExecutions($instance)) {
            $instance->update(['status' => WorkflowInstanceStatus::Waiting]);
        }
    }

    protected function onFail(
        WorkflowNodeExecution $execution,
        WorkflowInstance $instance,
        NodeExecutionContext $context,
        NodeExecutionResult $result,
    ): void {
        $errorPayload = [
            'message' => $result->errorMessage ?? 'Unknown error',
            'node_key' => $execution->node_key,
            'attempt' => $execution->attempt,
        ];

        $execution->update([
            'status' => NodeExecutionStatus::Failed,
            'error' => $errorPayload,
            'finished_at' => now(),
        ]);

        $willRetry = $result->retryable && $this->retryPolicy->shouldRetry($execution->attempt);

        Event::dispatch(new NodeFailed($instance, $execution, $willRetry));

        if ($willRetry) {
            $this->scheduleRetry($execution, $instance, $context);

            return;
        }

        // No more retries — fail the instance.
        $instance->update([
            'status' => WorkflowInstanceStatus::Failed,
            'error' => $errorPayload,
            'finished_at' => now(),
        ]);

        Event::dispatch(new InstanceFailed($instance));
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    protected function seedExecution(
        WorkflowNodeExecution $parent,
        WorkflowInstance $instance,
        PlanEdge $edge,
        ExecutionPlan $plan,
    ): void {
        $nodeKey = $edge->target;
        $nodeType = $plan->nodeType($nodeKey) ?? 'unknown';
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
        NodeExecutionContext $context,
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
            Event::dispatch(new NodeRetrying($instance, $failed, $retry));

            $delay = $this->retryPolicy->delaySeconds($nextAttempt);
            $category = $this->resolveCategory($failed->node_type);
            ExecuteNodeJob::dispatch($retry->id, $category->value)
                ->onQueue($this->queueFor($category))
                ->delay(now()->addSeconds($delay));
        }
    }

    protected function completeInstance(WorkflowInstance $instance): void
    {
        $instance->update([
            'status' => WorkflowInstanceStatus::Completed,
            'finished_at' => $instance->finished_at ?? now(),
        ]);

        $instance->workflow()->decrement('active_instances');

        Event::dispatch(new InstanceCompleted($instance));
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
            ? (string) config('workflows.execution.queues.actions', 'workflow-actions')
            : (string) config('workflows.execution.queues.control', 'workflow-control');
    }
}
