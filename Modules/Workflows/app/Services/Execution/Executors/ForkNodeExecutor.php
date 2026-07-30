<?php

namespace Modules\Workflows\Services\Execution\Executors;

use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;
use Modules\Workflows\Services\Execution\Data\NodeExecutionResult;
use Modules\Workflows\Services\Execution\NodeExecutionContext;

/**
 * Fan-out: spawn one execution token per outgoing parallel branch. The merge coordination
 * row is created lazily when the first branch token arrives (via MergeCoordinator::arrive()).
 * Every parallel edge from an `and-node` must carry a join_node_key — enforced at verification.
 */
class ForkNodeExecutor implements NodeExecutor
{
    public function type(): string
    {
        return 'and-node';
    }

    public function category(): NodeCategory
    {
        return NodeCategory::Logic;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        return NodeExecutionResult::proceed($context->plan()->outgoing($context->nodeKey()));
    }
}
