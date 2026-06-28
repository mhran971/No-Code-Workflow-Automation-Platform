<?php

namespace Modules\Workflows\Services\Execution\Executors;

use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use Modules\Workflows\Services\Execution\NodeExecutionResult;

class TerminationNodeExecutor implements NodeExecutor
{
    public function type(): string
    {
        return 'termination-node';
    }

    public function category(): NodeCategory
    {
        return NodeCategory::Logic;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        return NodeExecutionResult::terminate();
    }
}
