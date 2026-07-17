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
        $config = $context->config();
        $defaultEdge = null;

        // Resolve the switch variable to its current value. Note: evaluate(), not render() —
        // render() only interpolates {{ }} placeholders and returns bare paths like "context.color"
        // unchanged, which would never match a branch and always fall through to default.
        $varPath = trim((string) ($config['variable'] ?? ''));
        $rawValue = $varPath !== '' ? $context->evaluate($varPath) : null;
        $value = match (true) {
            $rawValue === null => null,
            is_bool($rawValue) => $rawValue ? 'true' : 'false',
            is_scalar($rawValue) => (string) $rawValue,
            default => null,
        };

        foreach ($outgoing as $edge) {
            // Default / fallback branch — keep it as last resort.
            if ($edge->branchType === 'default' || $edge->isDefaultBranch) {
                $defaultEdge = $edge;

                continue;
            }

            // Per-edge condition expression (advanced override) takes priority.
            if ($edge->conditionExpression !== null && $context->evaluateBoolean($edge->conditionExpression)) {
                return NodeExecutionResult::branch($edge);
            }

            // Canonical match: branch_type matches the evaluated variable value.
            if ($value !== null && $edge->branchType === $value) {
                return NodeExecutionResult::branch($edge);
            }
        }

        if ($defaultEdge !== null) {
            return NodeExecutionResult::branch($defaultEdge);
        }

        return NodeExecutionResult::fail('No matching switch case and no default branch configured.', false);
    }
}
