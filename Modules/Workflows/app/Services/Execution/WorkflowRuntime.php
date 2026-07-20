<?php

namespace Modules\Workflows\Services\Execution;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Modules\Workflows\app\Enums\ResultKind;
use Modules\Workflows\Enums\DynamicFlowStatus;
use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Enums\NodeExecutionStatus;
use Modules\Workflows\Enums\WaitType;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Events\ChildInstanceEventForwarded;
use Modules\Workflows\Events\InstanceCancelled;
use Modules\Workflows\Events\InstanceCompleted;
use Modules\Workflows\Events\InstanceFailed;
use Modules\Workflows\Events\NodeCompleted;
use Modules\Workflows\Events\NodeFailed;
use Modules\Workflows\Events\NodeRetrying;
use Modules\Workflows\Events\NodeStarted;
use Modules\Workflows\Events\NodeWaiting;
use Modules\Workflows\Jobs\ExecuteNodeJob;
use Modules\Workflows\Models\WorkflowDynamicFlow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Models\WorkflowTask;
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
     * Resolve the execution plan for an instance.
     *
     * Normal instances compile from their published version. Dynamic-flow children have no
     * version — look up the user-defined definition stored on the WorkflowDynamicFlow row.
     */
    protected function resolvePlan(WorkflowInstance $instance): ExecutionPlan
    {
        if ($instance->workflowVersion) {
            return $this->compiler->compileVersion($instance->workflowVersion);
        }

        $dynamicFlow = WorkflowDynamicFlow::where('child_instance_id', $instance->id)
            ->where('status', DynamicFlowStatus::Executing)
            ->firstOrFail();

        return $this->compiler->compile($dynamicFlow->definition ?? []);
    }

    /**
     * Broadcast events now fire synchronously (ShouldBroadcastNow), so defer dispatch until
     * the enclosing transaction commits — otherwise clients could see a status over the socket
     * before it's visible via the API, or see one that gets rolled back.
     */
    protected function broadcast(object $event): void
    {
        DB::afterCommit(function () use ($event) {
            Event::dispatch($event);

            // Forward child instance events to parent's channel
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

    /**
     * Advance a single execution: claim → run → commit result.
     */
    public function advance(int $executionId): void
    {
        $execution = $this->claim($executionId);
        if ($execution === null) {
            return;
        }

        $instance = WorkflowInstance::query()
            ->with('workflowVersion')
            ->findOrFail($execution->instance_id);

        $plan = $this->resolvePlan($instance);
        $context = new NodeExecutionContext($instance, $execution, $plan, $this->evaluator, $this->interpolator);

        $this->broadcast(new NodeStarted($instance, $execution));

        $executor = $this->registry->for($execution->node_type);

        try {
            $result = $executor->execute($context);
        } catch (Throwable $e) {
            Log::error('workflow.node.exception', [
                'instance_id' => $instance->id,
                'execution_id' => $executionId,
                'node_key' => $execution->node_key,
                'node_type' => $execution->node_type,
                'attempt' => $execution->attempt,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            $result = NodeExecutionResult::fail($e, $this->classifier->isRetryable($e));
        }

        DB::transaction(function () use ($executionId, $instance, $plan, $context, $result): void {
            $execution = WorkflowNodeExecution::query()->lockForUpdate()->findOrFail($executionId);

            if ($execution->status !== NodeExecutionStatus::Running) {
                return;
            }

            $this->applyResult($execution, $instance, $plan, $context, $result);
        });
    }

    /**
     * Manually cancel a running instance: consume all live tokens and mark cancelled.
     */
    public function cancel(WorkflowInstance $instance): void
    {
        DB::transaction(function () use ($instance): void {
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

            $instance->update([
                'status' => WorkflowInstanceStatus::Cancelled,
                'finished_at' => now(),
            ]);
        });

        $this->broadcast(new InstanceCancelled($instance));
    }

    /**
     * Re-activate execution at a given node (operator-triggered recovery after a permanent failure).
     */
    public function retryFromNode(WorkflowInstance $instance, string $nodeKey): void
    {
        DB::transaction(function () use ($instance, $nodeKey): void {
            $plan = $this->resolvePlan($instance);
            $nodeType = $plan->nodeType($nodeKey) ?? 'unknown';

            $lastExecution = WorkflowNodeExecution::query()
                ->where('instance_id', $instance->id)
                ->where('node_key', $nodeKey)
                ->orderByDesc('attempt')
                ->first();

            $nextAttempt = ($lastExecution?->attempt ?? 0) + 1;
            $idempotencyKey = hash('sha256', $instance->id.':'.$nodeKey.':'.$nextAttempt);

            $retry = WorkflowNodeExecution::firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'instance_id' => $instance->id,
                    'tenant_id' => $instance->tenant_id,
                    'node_key' => $nodeKey,
                    'node_type' => $nodeType,
                    'status' => NodeExecutionStatus::Pending,
                    'attempt' => $nextAttempt,
                    'parent_execution_id' => $lastExecution?->parent_execution_id,
                    'input' => $lastExecution?->input ?? [],
                ],
            );

            if ($retry->wasRecentlyCreated) {
                $instance->update(['status' => WorkflowInstanceStatus::Running]);
                $category = $this->resolveCategory($nodeType);
                ExecuteNodeJob::dispatch($retry->id, $category->value)
                    ->onQueue($this->queueFor($category));
            }
        });
    }

    // ─── Claim ───────────────────────────────────────────────────────────────

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

    // ─── Result dispatch ─────────────────────────────────────────────────────

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
            ResultKind::Fail => $this->onFail($execution, $instance, $plan, $result),
            ResultKind::Noop => $this->onConsume($execution),
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

        if (! empty($result->output)) {
            $context->setContextValue((string) $execution->node_key, $result->output);
        }

        if ($instance->isDirty('context')) {
            $instance->save();
        }

        $this->broadcast(new NodeCompleted($instance, $execution));

        if (empty($result->edges)) {
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

        $this->broadcast(new NodeCompleted($instance, $execution));
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

        if (! $this->hasNonWaitingLiveExecutions($instance)) {
            $instance->update(['status' => WorkflowInstanceStatus::Waiting]);
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

        // Check for an error-routing edge before failing the instance.
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

        $this->failInstance($instance, $errorPayload);
    }

    protected function onConsume(WorkflowNodeExecution $execution): void
    {
        $execution->update([
            'status' => NodeExecutionStatus::Consumed,
            'finished_at' => now(),
        ]);
    }

    // ─── Fork-join: merge arrival ─────────────────────────────────────────────

    /**
     * Called (inside the result-commit transaction) when a branch token edges into a merge node.
     *
     * Strategy:
     *  - firstOrCreate the single shared merge row per (instance, merge_node).
     *  - lockForUpdate + increment arrived_count.
     *  - For parallel (merge-and): dispatch when arrived == expected.
     *  - For conditional (merge-or): dispatch on first arrival; skip if already terminal.
     *  - Merge timeout is seeded via wait_until/wait_type; ScanWorkflowTimersCommand wakes it up.
     */
    protected function arriveAtMerge(
        WorkflowNodeExecution $parent,
        WorkflowInstance $instance,
        string $mergeKey,
        string $mergeType,
        ExecutionPlan $plan,
    ): void {
        $joinSpec = $plan->joinFor($mergeKey);
        if ($joinSpec === null) {
            return;
        }

        $mergeIdempotencyKey = hash('sha256', $instance->id.':'.$mergeKey.':merge:1');

        // Ensure the single shared merge coordination row exists.
        $merge = WorkflowNodeExecution::firstOrCreate(
            ['idempotency_key' => $mergeIdempotencyKey],
            [
                'instance_id' => $instance->id,
                'tenant_id' => $instance->tenant_id,
                'node_key' => $mergeKey,
                'node_type' => $mergeType,
                'status' => NodeExecutionStatus::Waiting,
                'attempt' => 1,
                'expected_count' => $joinSpec->expectedCount,
                'arrived_count' => 0,
                'input' => $parent->output ?? [],
                'wait_type' => $joinSpec->timeoutSeconds ? WaitType::MergeTimeout : null,
                'wait_until' => $joinSpec->timeoutSeconds ? now()->addSeconds($joinSpec->timeoutSeconds) : null,
            ],
        );

        // Lock the merge row; another branch may be arriving concurrently.
        $merge = WorkflowNodeExecution::query()->lockForUpdate()->findOrFail($merge->id);

        // Conditional merge: first branch already won — this arrival is a no-op.
        if ($merge->status->isTerminal()) {
            return;
        }

        // Increment: safe under lockForUpdate (no other transaction holds this row).
        $arrivedAfterIncrement = $merge->arrived_count + 1;
        DB::table('workflow_node_executions')
            ->where('id', $merge->id)
            ->increment('arrived_count');

        $shouldProceed = $joinSpec->isParallel()
            ? ($arrivedAfterIncrement >= $joinSpec->expectedCount)
            : ($arrivedAfterIncrement === 1);

        if ($shouldProceed) {
            $merge->update(['status' => NodeExecutionStatus::Pending]);
            ExecuteNodeJob::dispatch($merge->id, NodeCategory::Logic->value)
                ->onQueue($this->queueFor(NodeCategory::Logic));
        }
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

        // Merge nodes use a shared coordination row — handled by arriveAtMerge.
        if ($plan->isMergeNode($nodeKey)) {
            $this->arriveAtMerge($parent, $instance, $nodeKey, $nodeType, $plan);

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

    protected function completeInstance(WorkflowInstance $instance): void
    {
        $instance->update([
            'status' => WorkflowInstanceStatus::Completed,
            'finished_at' => $instance->finished_at ?? now(),
        ]);

        $instance->workflow()->decrement('active_instances');

        $this->broadcast(new InstanceCompleted($instance));

        $this->wakeParentExecution($instance);
    }

    protected function failInstance(WorkflowInstance $instance, array $errorPayload): void
    {
        // Cancel all remaining waiting tokens so timers don't fire after the instance is dead.
        WorkflowNodeExecution::query()
            ->where('instance_id', $instance->id)
            ->where('status', NodeExecutionStatus::Waiting->value)
            ->update(['status' => NodeExecutionStatus::Consumed->value, 'finished_at' => now()]);

        $instance->update([
            'status' => WorkflowInstanceStatus::Failed,
            'error' => $errorPayload,
            'finished_at' => now(),
        ]);

        $this->broadcast(new InstanceFailed($instance));

        $this->wakeParentExecution($instance);
    }

    /**
     * If this instance was spawned as a child (sub-workflow or dynamic-flow),
     * wake the parked parent execution so it can resume.
     */
    protected function wakeParentExecution(WorkflowInstance $instance): void
    {
        // Path 1: Sub-workflow — parent_execution_id set directly on instance.
        $parentExecutionId = $instance->parent_execution_id;

        // Path 2: Dynamic-flow — look up via workflow_dynamic_flows table.
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

    /**
     * Called by ExecuteNodeJob when advance() throws an unhandled exception.
     * Ensures the child instance is marked failed and the parent is woken,
     * so failures are never silently swallowed.
     */
    public function handleAdvanceFailure(int $executionId, Throwable $exception): void
    {
        try {
            $execution = WorkflowNodeExecution::find($executionId);
            if ($execution === null || $execution->status !== NodeExecutionStatus::Running) {
                return;
            }

            $instance = WorkflowInstance::find($execution->instance_id);
            if ($instance === null || $instance->isTerminal()) {
                return;
            }

            $this->failInstance($instance, [
                'message' => $exception->getMessage(),
                'exception' => get_class($exception),
            ]);
        } catch (Throwable $inner) {
            Log::error('workflow.advance_failure_handler_error', [
                'execution_id' => $executionId,
                'original' => $exception->getMessage(),
                'handler_error' => $inner->getMessage(),
            ]);
        }
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
