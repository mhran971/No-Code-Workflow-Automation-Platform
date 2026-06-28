<?php

namespace Modules\Workflows\Services\Execution\Executors;

use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use Modules\Workflows\Services\Execution\NodeExecutionResult;

class ManualTriggerExecutor implements NodeExecutor
{
    public function type(): string
    {
        return 'manual-trigger';
    }

    public function category(): NodeCategory
    {
        return NodeCategory::Trigger;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        return NodeExecutionResult::proceed(
            $context->plan()->outgoing($context->nodeKey()),
            ['triggered_by' => $context->instance()->payload ?? []],
        );
    }
}
