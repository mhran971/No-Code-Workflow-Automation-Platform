<?php

namespace Modules\Workflows\Services\Execution\Executors;

use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use Modules\Workflows\Services\Execution\Data\NodeExecutionResult;

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
        $payload = $context->instance()->payload ?? [];

        // Seed context from the HTTP trigger payload (if any).
        if (! empty($payload)) {
            $context->mergeContext($payload);
        }

        // Seed context from the static variables defined on the trigger node in the
        // workflow definition (config.variables = [{key, value}, ...]).
        // These take precedence over any same-named HTTP payload values.
        foreach ((array) ($context->config()['variables'] ?? []) as $var) {
            $key = trim((string) ($var['key'] ?? ''));
            if ($key !== '') {
                $context->setContextValue($key, $var['value'] ?? null);
            }
        }

        return NodeExecutionResult::proceed(
            $context->plan()->outgoing($context->nodeKey()),
            ['triggered_by' => $payload],
        );
    }
}
