<?php

namespace Modules\Workflows\Services\Execution\Executors;

use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Enums\TriggerType;
use Modules\Workflows\Enums\WaitType;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use Modules\Workflows\Services\Execution\NodeExecutionResult;
use Modules\Workflows\Services\Execution\WorkflowDispatcher;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Spawns a child workflow instance, parks the parent until the child finishes,
 * then resumes with the child's output mapped into the parent context.
 *
 * Re-entrant: runs twice per execution — once to create the child and park,
 * once to read the completed child's output and proceed.
 */
class SubWorkflowExecutor implements NodeExecutor
{
    public function __construct(
        protected WorkflowDispatcher $dispatcher,
    ) {}

    public function type(): string
    {
        return 'sub-workflow';
    }

    public function category(): NodeCategory
    {
        return NodeCategory::Flows;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        $executionId = $context->execution()->id;
        $config = $context->config();

        // Check if a child instance already exists for this execution.
        $childInstance = WorkflowInstance::query()
            ->where('parent_execution_id', $executionId)
            ->first();

        // Resume path: child has finished.
        if ($childInstance !== null && $childInstance->isTerminal()) {
            $childContext = $childInstance->context ?? [];
            $outputVariable = $config['outputVariable'] ?? null;

            if (is_string($outputVariable) && $outputVariable !== '') {
                $context->setContextValue($outputVariable, $childContext);
            }

            return NodeExecutionResult::proceed(
                $context->plan()->outgoing($context->nodeKey()),
                ['child_instance_id' => $childInstance->id, 'child_status' => $childInstance->status->value],
            );
        }

        // If child exists but is not terminal yet (still running/waiting), re-park.
        if ($childInstance !== null && ! $childInstance->isTerminal()) {
            return NodeExecutionResult::wait(null, WaitType::SubWorkflow, 'Waiting for child instance to complete');
        }

        // Initial creation path: no child exists yet — spawn one.
        $childWorkflowId = $config['workflowId'] ?? null;
        if ($childWorkflowId === null || $childWorkflowId === '') {
            return NodeExecutionResult::fail(
                new \InvalidArgumentException('Sub-workflow node config is missing workflowId.'),
                false,
            );
        }

        $childWorkflow = Workflow::query()->find($childWorkflowId);
        if ($childWorkflow === null) {
            return NodeExecutionResult::fail(
                new NotFoundHttpException("Workflow #{$childWorkflowId} not found."),
                false,
            );
        }

        if ($childWorkflow->current_version_id === null) {
            return NodeExecutionResult::fail(
                new \RuntimeException("Workflow #{$childWorkflowId} has not been published."),
                false,
            );
        }

        // Tenant isolation: child workflow must belong to the same tenant.
        if ((int) $childWorkflow->tenant_id !== (int) $context->instance()->tenant_id) {
            return NodeExecutionResult::fail(
                new \RuntimeException('Cannot trigger a sub-workflow from another tenant.'),
                false,
            );
        }

        // Evaluate inputMapping against the parent's context.
        $inputMapping = $config['inputMapping'] ?? [];
        $payload = [];
        if (is_array($inputMapping)) {
            foreach ($inputMapping as $childKey => $template) {
                $payload[$childKey] = $context->render((string) $template);
            }
        }

        $childInstance = $this->dispatcher->dispatch(
            $childWorkflow,
            TriggerType::SubWorkflow,
            $payload,
            null,
            (int) $context->instanceId(),
            (int) $executionId,
        );

        return NodeExecutionResult::wait(null, WaitType::SubWorkflow, 'Waiting for child instance to complete');
    }
}
