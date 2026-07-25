<?php

namespace Modules\Workflows\Services\Verification\Rules\NodeType;

use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\Rules\NodeType\Concerns\VariableAvailability;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;

class AiGeneratorNodeTypeRule implements NodeTypeRule
{
    use VariableAvailability;

    protected const CODE_PREFIX = 'ai_generator';

    public function nodeType(): string
    {
        return 'ai-generator';
    }

    public function verify(
        array $node,
        int $index,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
        ?Workflow $workflow = null,
    ): void {
        $nodeId = is_string($node['id'] ?? null) ? $node['id'] : null;
        $config = is_array($node['config'] ?? null) ? $node['config'] : [];

        $prompt = trim((string) ($config['prompt'] ?? ''));
        if ($prompt === '') {
            $result->addError(
                'ai_generator.prompt_missing',
                'AI Generator node must have a prompt.',
                "nodes[{$index}].config.prompt",
                $nodeId,
            );
        } else {
            $this->validateTemplateVariables($prompt, "nodes[{$index}].config.prompt", $nodeId, self::CODE_PREFIX, $graph, $result);
        }

        $outputVar = trim((string) ($config['outputVariable'] ?? ''));
        if ($outputVar !== '' && ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $outputVar)) {
            $result->addError(
                'ai_generator.output_variable_invalid',
                "Output variable name '{$outputVar}' must be a valid identifier (letters, numbers, underscore; cannot start with a digit).",
                "nodes[{$index}].config.outputVariable",
                $nodeId,
            );
        }
    }
}
