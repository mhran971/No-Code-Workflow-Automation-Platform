<?php

namespace Modules\Workflows\Services\Execution\Executors;

use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use Modules\Workflows\Services\Execution\NodeExecutionResult;

/**
 * Entry point for dynamically-designed sub-flows.
 *
 * Seeds the child instance context from the parent's context (passed via payload
 * by DynamicFlowController), then proceeds to the outgoing edge.
 */
class DynamicEntryExecutor implements NodeExecutor
{
    public function type(): string
    {
        return 'dynamic-entry';
    }

    public function category(): NodeCategory
    {
        return NodeCategory::Trigger;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        $payload = $context->instance()->payload ?? [];

        if (! empty($payload)) {
            $context->mergeContext($payload);
        }

        return NodeExecutionResult::proceed(
            $context->plan()->outgoing($context->nodeKey()),
            ['triggered_by' => 'dynamic_entry'],
        );
    }
}
