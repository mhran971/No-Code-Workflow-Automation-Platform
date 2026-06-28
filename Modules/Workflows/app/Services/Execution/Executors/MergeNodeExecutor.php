<?php

namespace Modules\Workflows\Services\Execution\Executors;

use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use Modules\Workflows\Services\Execution\NodeExecutionResult;

/**
 * Handles both `merge-and` (wait-all) and `merge-or` (first-arrival) node types.
 *
 * The gating logic (arrive-count tracking, idempotent dispatch) lives in
 * WorkflowRuntime::arriveAtMerge(). By the time this executor runs, the merge row
 * was promoted to `pending` — so it always proceeds to its outgoing edges.
 *
 * Registered under both 'merge-and' and 'merge-or' in WorkflowsServiceProvider.
 */
class MergeNodeExecutor implements NodeExecutor
{
    public function type(): string
    {
        return 'merge-and';
    }

    public function category(): NodeCategory
    {
        return NodeCategory::Logic;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        return NodeExecutionResult::proceed(
            $context->plan()->outgoing($context->nodeKey()),
            $context->execution()->output ?? [],
        );
    }
}
