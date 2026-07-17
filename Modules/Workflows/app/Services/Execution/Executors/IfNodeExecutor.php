<?php

namespace Modules\Workflows\Services\Execution\Executors;

use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use Modules\Workflows\Services\Execution\NodeExecutionResult;
use Modules\Workflows\Services\Execution\PlanEdge;

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
        $config = $context->config();

        $nodeCondition = trim((string) ($config['conditionExpression'] ?? ''));

        if ($nodeCondition === '') {
            return NodeExecutionResult::fail('If-node has no condition expression configured.', false);
        }

        $conditionResult = $context->evaluateBoolean($nodeCondition);

        // Partition outgoing edges into yes-branch and no-branch pools.
        $yesBranch = null;
        $noBranch = null;

        foreach ($outgoing as $edge) {
            if ($this->isTrueBranch($edge)) {
                $yesBranch ??= $edge;
            } else {
                // Everything else (branch_type 'else', 'default', unrecognised) is the false/else branch.
                $noBranch ??= $edge;
            }
        }

        if ($conditionResult && $yesBranch !== null) {
            return NodeExecutionResult::branch($yesBranch);
        }

        if (! $conditionResult && $noBranch !== null) {
            return NodeExecutionResult::branch($noBranch);
        }

        // Graceful fallback: if only one branch exists, always take it.
        if (count($outgoing) === 1) {
            return NodeExecutionResult::branch($outgoing[0]);
        }

        return NodeExecutionResult::fail('If-node is missing one or both outgoing branches.', false);
    }

    /**
     * The yes/true branch is identified by:
     *   'true'     — written by the IfNode 'yes' handle (current)
     *   'branch_1' — written by legacy canvas saves (output-1 before named handles)
     */
    private function isTrueBranch(PlanEdge $edge): bool
    {
        return in_array($edge->branchType, ['true', 'branch_1'], true);
    }
}
