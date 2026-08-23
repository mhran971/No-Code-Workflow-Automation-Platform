<?php

namespace Modules\Workflows\Services\Execution\Executors;

use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\Contracts\AiTextClassifier;
use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;
use Modules\Workflows\Services\Execution\Data\NodeExecutionResult;
use Modules\Workflows\Services\Execution\Exceptions\AiServiceException;
use Modules\Workflows\Services\Execution\NodeExecutionContext;

class AiClassifierExecutor implements NodeExecutor
{
    public function __construct(protected AiTextClassifier $ai) {}

    public function type(): string
    {
        return 'ai-classifier';
    }

    public function category(): NodeCategory
    {
        return NodeCategory::Ai;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        $config = $context->config();
        $text = $context->render((string) ($config['text'] ?? ''));
        $categories = $this->categories($config);
        $outputVar = trim((string) ($config['outputVariable'] ?? 'ai_classification'));

        try {
            $classification = $this->ai->classify($text, $categories, (string) $context->instance()->tenant_id);
        } catch (AiServiceException $e) {
            return NodeExecutionResult::fail("AI Classifier: {$e->getMessage()}", retryable: $e->isRetryable());
        }

        if ($outputVar !== '') {
            $context->setContextValue($outputVar, $classification);
        }

        return NodeExecutionResult::proceed(
            $context->plan()->outgoing($context->nodeKey()),
            [
                'output_variable' => $outputVar,
                'classification' => $classification['classification'],
                'confidence' => $classification['confidence'],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    protected function categories(array $config): array
    {
        $raw = $config['categories'] ?? [];

        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $c): string => trim((string) $c), $raw),
            static fn (string $c): bool => $c !== '',
        ));
    }
}
