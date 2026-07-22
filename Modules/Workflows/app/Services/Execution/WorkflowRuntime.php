<?php

namespace Modules\Workflows\Services\Execution;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Modules\Workflows\Enums\DynamicFlowStatus;
use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Enums\NodeExecutionStatus;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Events\ChildInstanceEventForwarded;
use Modules\Workflows\Events\NodeStarted;
use Modules\Workflows\Jobs\ExecuteNodeJob;
use Modules\Workflows\Models\WorkflowDynamicFlow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Services\Execution\Expression\ExpressionEvaluator;
use Modules\Workflows\Services\Execution\Expression\TemplateInterpolator;
use Throwable;

/**
 * Orchestrator: claim → resolve plan → execute → delegate result.
 *
 * Delegates node-level work (execution rows, broadcasting, seed/schedule) to NodeStateManager
 * and instance-level work (status transitions, counters, parent wake) to InstanceStateManager.
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
        protected NodeStateManager $nodeStateManager,
        protected InstanceStateManager $instanceStateManager,
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
        $execution = $this->nodeStateManager->claim($executionId);
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

            $this->nodeStateManager->handleResult($execution, $instance, $plan, $context, $result);
        });
    }

    /**
     * Manually cancel a running instance: consume all live tokens and mark cancelled.
     */
    public function cancel(WorkflowInstance $instance): void
    {
        $this->instanceStateManager->transitionTo($instance, WorkflowInstanceStatus::Cancelled);
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
                $this->instanceStateManager->transitionTo($instance, WorkflowInstanceStatus::Running);
                $category = $this->resolveCategory($nodeType);
                ExecuteNodeJob::dispatch($retry->id, $category->value)
                    ->onQueue($this->queueFor($category));
            }
        });
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

            $this->instanceStateManager->transitionTo($instance, WorkflowInstanceStatus::Failed, [
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
