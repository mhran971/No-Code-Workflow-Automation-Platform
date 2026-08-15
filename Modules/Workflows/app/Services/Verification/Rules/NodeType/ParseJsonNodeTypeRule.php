<?php

namespace Modules\Workflows\Services\Verification\Rules\NodeType;

use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\Rules\NodeType\Concerns\VariableAvailability;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;

class ParseJsonNodeTypeRule implements NodeTypeRule
{
    use VariableAvailability;

    protected const CODE_PREFIX = 'parse_json';

    public function nodeType(): string
    {
        return 'parse-json';
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

        $inputVar = trim((string) ($config['inputVariable'] ?? ''));
        if ($inputVar === '') {
            $result->addError(
                'parse_json.input_variable_missing',
                'Parse JSON node must specify an input variable to parse.',
                "nodes[{$index}].config.inputVariable",
                $nodeId,
            );
        } elseif (! preg_match('/^(context|customer)\.[a-zA-Z_][a-zA-Z0-9_]*$/', $inputVar)) {
            $result->addError(
                'parse_json.input_variable_invalid',
                "Input variable '{$inputVar}' must be a single reference like context.<key> or customer.<key>.",
                "nodes[{$index}].config.inputVariable",
                $nodeId,
            );
        } elseif (str_starts_with($inputVar, 'context.')) {
            $available = $this->collectAvailableContextKeys($nodeId, $graph);
            if ($available !== null) {
                $this->validateContextVariableExists($inputVar, $available, self::CODE_PREFIX, "nodes[{$index}].config.inputVariable", $nodeId, $result);
            }
        }

        $outputVar = trim((string) ($config['outputVariable'] ?? ''));
        if ($outputVar === '') {
            $result->addError(
                'parse_json.output_variable_missing',
                'Parse JSON node must specify an output variable name.',
                "nodes[{$index}].config.outputVariable",
                $nodeId,
            );
        } elseif (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $outputVar)) {
            $result->addError(
                'parse_json.output_variable_invalid',
                "Output variable name '{$outputVar}' must be a valid identifier (letters, numbers, underscore; cannot start with a digit).",
                "nodes[{$index}].config.outputVariable",
                $nodeId,
            );
        }
    }
}
