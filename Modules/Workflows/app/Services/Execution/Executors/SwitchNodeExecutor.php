<?php

namespace Modules\Workflows\Services\Execution\Executors;

use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use Modules\Workflows\Services\Execution\NodeExecutionResult;

class SwitchNodeExecutor implements NodeExecutor
{
    public function type(): string
    {
        return 'switch';
    }

    public function category(): NodeCategory
    {
        return NodeCategory::Logic;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        $outgoing = $context->plan()->outgoing($context->nodeKey());
        $defaultEdge = null;

        // Evaluate in sort_order; first matching condition wins.
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

        return NodeExecutionResult::fail('No matching switch case and no default branch configured.', false);
    }
}
