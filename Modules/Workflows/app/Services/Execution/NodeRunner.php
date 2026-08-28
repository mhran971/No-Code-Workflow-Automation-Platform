<?php

namespace Modules\Workflows\Services\Execution;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Workflows\Enums\NodeExecutionStatus;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Events\NodeStarted;
use Modules\Workflows\Models\WorkflowDynamicFlow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Services\Execution\Data\NodeExecutionResult;
use Modules\Workflows\Services\Execution\Expression\ExpressionEvaluator;
use Modules\Workflows\Services\Execution\Expression\TemplateInterpolator;
use Throwable;

/**
 * Orchestrates a single node execution cycle: claim → resolve plan → run executor → commit result.
 *
 * This is the entry point called by ExecuteNodeJob for each queued node execution.
 */
class NodeRunner
{
    public function __construct(
        protected ExecutionPlanCompiler $compiler,
        protected NodeExecutorRegistry $registry,
        protected ExpressionEvaluator $evaluator,
        protected TemplateInterpolator $interpolator,
        protected FailureClassifier $classifier,
        protected WorkflowExecutionEngine $engine,
        protected EventBroadcaster $broadcaster,
    ) {}

    protected function resolvePlan(WorkflowInstance $instance): ExecutionPlan
    {
        if ($instance->workflowVersion) {
            return $this->compiler->compileVersion($instance->workflowVersion);
        }

        // Match on the parent_execution_id link first — it is written atomically when the child is
        // dispatched, whereas child_instance_id is only backfilled after dispatch returns and may
        // not be visible yet when a synchronously-run child executes its first node.
        $dynamicFlow = WorkflowDynamicFlow::query()
            ->where(function ($query) use ($instance): void {
                $query->where('child_instance_id', $instance->id);

                if ($instance->parent_execution_id !== null) {
                    $query->orWhere('execution_id', $instance->parent_execution_id);
                }
            })
            ->firstOrFail();

        return $this->compiler->compile($dynamicFlow->definition ?? []);
    }

    public function advance(int $executionId): void
    {
        $execution = $this->engine->claim($executionId);
        if ($execution === null) {
            return;
        }

        $instance = WorkflowInstance::query()
            ->with(['workflowVersion', 'customer'])
            ->findOrFail($execution->instance_id);

        $plan = $this->resolvePlan($instance);
        $context = new NodeExecutionContext($instance, $execution, $plan, $this->evaluator, $this->interpolator);

        $this->broadcaster->broadcast(new NodeStarted($instance, $execution));

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

            $this->engine->handleResult($execution, $instance, $plan, $context, $result);
        });
    }

    public function cancel(WorkflowInstance $instance): void
    {
        $this->engine->transitionTo($instance, WorkflowInstanceStatus::Cancelled);
    }

    public function retryFromNode(WorkflowInstance $instance, string $nodeKey): void
    {
        DB::transaction(function () use ($instance, $nodeKey): void {
            $plan = $this->resolvePlan($instance);
            $this->engine->retryFromNode($instance, $nodeKey, $plan);
        });
    }

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

            $this->engine->transitionTo($instance, WorkflowInstanceStatus::Failed, [
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
}
