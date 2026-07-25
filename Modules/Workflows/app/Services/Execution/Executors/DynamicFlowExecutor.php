<?php

namespace Modules\Workflows\Services\Execution\Executors;

use Modules\Workflows\Enums\DynamicFlowStatus;
use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Enums\WaitType;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Events\InstancePaused;
use Modules\Workflows\Models\WorkflowDynamicFlow;
use Modules\Workflows\Models\WorkflowEvent;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use Modules\Workflows\Services\Execution\Data\NodeExecutionResult;

/**
 * Pauses the parent instance when a dynamic-flow node is reached,
 * notifies the creator/Manager to design a sub-flow, then resumes
 * once the child instance completes.
 *
 * Three-entry re-entrant pattern:
 *  1. No row → create awaiting_design, pause, notify, wait.
 *  2. Row awaiting_design → re-park.
 *  3. Row executing + child terminal → merge output, complete row, proceed.
 */
class DynamicFlowExecutor implements NodeExecutor
{
    public function type(): string
    {
        return 'dynamic-flow';
    }

    public function category(): NodeCategory
    {
        return NodeCategory::Flows;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        $executionId = $context->execution()->id;
        $config = $context->config();

        $dynamicFlow = WorkflowDynamicFlow::query()
            ->where('execution_id', $executionId)
            ->first();

        // Entry 1: No row exists — create one and pause.
        if ($dynamicFlow === null) {
            return $this->handleInitialEntry($context, $executionId, $config);
        }

        // Entry 3: Row is executing and child has finished — resume.
        if ($dynamicFlow->status === DynamicFlowStatus::Executing) {
            return $this->handleResumeEntry($context, $dynamicFlow, $config);
        }

        // Entry 2: Row is awaiting_design — re-park.
        return NodeExecutionResult::wait(null, WaitType::DynamicFlowDesign, 'Awaiting dynamic flow design');
    }

    protected function handleInitialEntry(
        NodeExecutionContext $context,
        int $executionId,
        array $config,
    ): NodeExecutionResult {
        $instance = $context->instance();

        $dynamicFlow = WorkflowDynamicFlow::create([
            'tenant_id' => $instance->tenant_id,
            'instance_id' => $instance->id,
            'execution_id' => $executionId,
            'node_key' => $context->nodeKey(),
            'status' => DynamicFlowStatus::AwaitingDesign,
        ]);

        $instance->update([
            'status' => WorkflowInstanceStatus::Paused,
            'paused_reason' => 'dynamic_flow:awaiting_design',
            'is_dynamic' => true,
            'dynamic_flow_id' => $dynamicFlow->id,
        ]);

        event(new InstancePaused($instance, 'dynamic_flow'));

        $this->writeWorkflowEvent($instance, 'dynamic_flow.requested', $dynamicFlow);

        return NodeExecutionResult::wait(null, WaitType::DynamicFlowDesign, 'Waiting for dynamic flow design');
    }

    protected function handleResumeEntry(
        NodeExecutionContext $context,
        WorkflowDynamicFlow $dynamicFlow,
        array $config,
    ): NodeExecutionResult {
        $childInstance = $dynamicFlow->childInstance;

        // Child still running — re-park.
        if ($childInstance === null || ! $childInstance->isTerminal()) {
            return NodeExecutionResult::wait(null, WaitType::DynamicFlowDesign, 'Waiting for child dynamic flow to complete');
        }

        // Child failed — propagate failure to parent.
        if ($childInstance->status === WorkflowInstanceStatus::Failed) {
            $childError = $childInstance->error ?? ['message' => 'Dynamic sub-flow failed'];
            $dynamicFlow->update(['status' => DynamicFlowStatus::Cancelled]);

            return NodeExecutionResult::fail(
                new \RuntimeException($childError['message'] ?? 'Dynamic sub-flow failed'),
                false,
            );
        }

        // Child finished successfully — merge output into parent context.
        $childContext = $childInstance->context ?? [];
        $outputVariable = $config['outputVariable'] ?? null;

        if (is_string($outputVariable) && $outputVariable !== '') {
            $context->setContextValue($outputVariable, $childContext);
        }

        $dynamicFlow->update(['status' => DynamicFlowStatus::Completed]);

        $this->writeWorkflowEvent($context->instance(), 'dynamic_flow.completed', $dynamicFlow);

        return NodeExecutionResult::proceed(
            $context->plan()->outgoing($context->nodeKey()),
            ['dynamic_flow_id' => $dynamicFlow->id, 'child_status' => $childInstance->status->value],
        );
    }

    protected function writeWorkflowEvent(
        WorkflowInstance $instance,
        string $type,
        WorkflowDynamicFlow $dynamicFlow,
    ): void {
        WorkflowEvent::create([
            'instance_id' => $instance->id,
            'tenant_id' => $instance->tenant_id,
            'node_key' => $dynamicFlow->node_key,
            'type' => $type,
            'payload' => [
                'dynamic_flow_id' => $dynamicFlow->id,
                'status' => $dynamicFlow->status->value,
            ],
        ]);
    }
}
