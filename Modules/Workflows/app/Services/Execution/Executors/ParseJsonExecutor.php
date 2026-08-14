<?php

namespace Modules\Workflows\Services\Execution\Executors;

use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;
use Modules\Workflows\Services\Execution\Data\NodeExecutionResult;
use Modules\Workflows\Services\Execution\NodeExecutionContext;

class ParseJsonExecutor implements NodeExecutor
{
    public function type(): string
    {
        return 'parse-json';
    }

    public function category(): NodeCategory
    {
        return NodeCategory::Logic;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        $config = $context->config();

        $inputVar = trim((string) ($config['inputVariable'] ?? ''));
        $outputVar = trim((string) ($config['outputVariable'] ?? ''));

        if ($inputVar === '' || $outputVar === '') {
            return NodeExecutionResult::fail('Parse JSON node is missing its input or output variable configuration.', false);
        }

        $raw = $context->evaluate($inputVar);

        if (! is_string($raw)) {
            return NodeExecutionResult::fail("Variable '{$inputVar}' is not a string; Parse JSON requires a stringified JSON value.", false);
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return NodeExecutionResult::fail("Failed to parse '{$inputVar}' as JSON: ".json_last_error_msg(), false);
        }

        $context->setContextValue($outputVar, $decoded);

        return NodeExecutionResult::proceed(
            $context->plan()->outgoing($context->nodeKey()),
            ['output_variable' => $outputVar],
        );
    }
}
