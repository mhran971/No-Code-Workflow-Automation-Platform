<?php

namespace Modules\Workflows\Services\Execution\Executors;

use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;
use Modules\Workflows\Services\Execution\Data\NodeExecutionResult;
use Modules\Workflows\Services\Execution\NodeExecutionContext;

class WebhookTriggerExecutor implements NodeExecutor
{
    public function type(): string
    {
        return 'webhook-trigger';
    }

    public function category(): NodeCategory
    {
        return NodeCategory::Trigger;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        return NodeExecutionResult::proceed(
            $context->plan()->outgoing($context->nodeKey()),
            ['webhook_payload' => $context->instance()->payload ?? []],
        );
    }
}
