<?php

namespace Modules\Workflows\Services\Execution\Concerns;

use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\NodeExecutorRegistry;

/**
 * Shared node-type resolution and queue routing used by WorkflowExecutionEngine,
 * WorkflowDispatcher, and MergeCoordinator.
 *
 * @requires NodeExecutorRegistry $registry
 */
trait DispatchesNodes
{
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
