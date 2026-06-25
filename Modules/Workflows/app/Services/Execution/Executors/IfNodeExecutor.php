<?php

namespace Modules\Workflows\Services\Execution\Executors;

use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use Modules\Workflows\Services\Execution\NodeExecutionResult;

class IfNodeExecutor implements NodeExecutor
{
    public function type(): string
    {
        return 'if-node';
    }

    public function category(): NodeCategory
    {
        return NodeCategory::Logic;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        $outgoing = $context->plan()->outgoing($context->nodeKey());
        $defaultEdge = null;

        // Evaluate conditions in sort_order; first match wins.
        foreach ($outgoing as $edge) {
            if ($edge->isDefaultBranch) {
                $defaultEdge = $edge;

                continue;
            }

            if ($edge->conditionExpression !== null && $context->evaluateBoolean($edge->conditionExpression)) {
                return NodeExecutionResult::branch($edge);
            }
        }

        if ($defaultEdge !== null) {
            return NodeExecutionResult::branch($defaultEdge);
        }

        return NodeExecutionResult::fail('No matching branch and no default branch configured.', false);
    }
}
