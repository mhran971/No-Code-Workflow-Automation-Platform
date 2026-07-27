<?php

namespace Modules\Workflows\Services\Execution\Executors;

use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;
use Modules\Workflows\Services\Execution\Data\NodeExecutionResult;
use Modules\Workflows\Services\Execution\NodeExecutionContext;

class FormTriggerExecutor implements NodeExecutor
{
    public function type(): string
    {
        return 'form-trigger';
    }

    public function category(): NodeCategory
    {
        return NodeCategory::Trigger;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        $formData = $context->instance()->payload ?? [];

        // Seed the instance context with the form submission so downstream nodes
        // can reference fields with {{context.fieldName}}.
        $context->mergeContext($formData);

        return NodeExecutionResult::proceed(
            $context->plan()->outgoing($context->nodeKey()),
            ['form_data' => $formData],
        );
    }
}
