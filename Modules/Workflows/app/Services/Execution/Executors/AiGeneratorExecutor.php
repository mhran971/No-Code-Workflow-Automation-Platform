<?php

namespace Modules\Workflows\Services\Execution\Executors;

use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\Contracts\AiContentGenerator;
use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use Modules\Workflows\Services\Execution\NodeExecutionResult;

class AiGeneratorExecutor implements NodeExecutor
{
    public function __construct(protected AiContentGenerator $ai) {}

    public function type(): string
    {
        return 'ai-generator';
    }

    public function category(): NodeCategory
    {
        return NodeCategory::Ai;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        $config = $context->config();
        $prompt = $context->render((string) ($config['prompt'] ?? ''));
        $outputVar = trim((string) ($config['outputVariable'] ?? 'ai_output'));

        $content = $this->ai->generate($prompt, $config);

        if ($outputVar !== '') {
            $context->setContextValue($outputVar, $content);
        }

        return NodeExecutionResult::proceed(
            $context->plan()->outgoing($context->nodeKey()),
            ['output_variable' => $outputVar, 'content_length' => strlen($content)],
        );
    }
}
