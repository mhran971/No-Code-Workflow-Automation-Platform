<?php

use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;

class SubWorkflowExecutor implements NodeExecutor
{
    public function type(): string
    {
        return 'subworkflow';
    }

    public function category(): NodeCategory
    {
        return NodeCategory::Flows;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        
    }
}
