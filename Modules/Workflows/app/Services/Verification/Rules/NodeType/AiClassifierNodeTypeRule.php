<?php

namespace Modules\Workflows\Services\Verification\Rules\NodeType;

use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\Rules\NodeType\Concerns\VariableAvailability;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;

class AiClassifierNodeTypeRule implements NodeTypeRule
{
    use VariableAvailability;

    protected const CODE_PREFIX = 'ai_classifier';

    public function nodeType(): string
    {
        return 'ai-classifier';
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

        $text = trim((string) ($config['text'] ?? ''));
        if ($text === '') {
            $result->addError(
                'ai_classifier.text_missing',
                'AI Classifier node must have text to classify.',
                "nodes[{$index}].config.text",
                $nodeId,
            );
        } else {
            $this->validateTemplateVariables($text, "nodes[{$index}].config.text", $nodeId, self::CODE_PREFIX, $graph, $result);
        }

        $this->verifyCategories($config, $index, $nodeId, $result);

        $outputVar = trim((string) ($config['outputVariable'] ?? ''));
        if ($outputVar !== '' && ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $outputVar)) {
            $result->addError(
                'ai_classifier.output_variable_invalid',
                "Output variable name '{$outputVar}' must be a valid identifier (letters, numbers, underscore; cannot start with a digit).",
                "nodes[{$index}].config.outputVariable",
                $nodeId,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function verifyCategories(array $config, int $index, ?string $nodeId, WorkflowVerificationResult $result): void
    {
        $path = "nodes[{$index}].config.categories";
        $categories = $config['categories'] ?? null;

        if ($categories === null || $categories === []) {
            $result->addError(
                'ai_classifier.categories_missing',
                'AI Classifier node must define at least 2 categories.',
                $path,
                $nodeId,
            );

            return;
        }

        if (! is_array($categories)) {
            $result->addError(
                'ai_classifier.categories_invalid',
                'Categories must be an array of category labels.',
                $path,
                $nodeId,
            );

            return;
        }

        $labels = array_values(array_filter(
            array_map(static fn (mixed $c): string => trim((string) $c), $categories),
            static fn (string $c): bool => $c !== '',
        ));

        if (count($labels) < 2) {
            $result->addError(
                'ai_classifier.categories_insufficient',
                'AI Classifier node must define at least 2 non-empty categories.',
                $path,
                $nodeId,
            );

            return;
        }

        if (count($labels) !== count(array_unique($labels))) {
            $result->addError(
                'ai_classifier.categories_duplicate',
                'AI Classifier categories must be unique.',
                $path,
                $nodeId,
            );
        }
    }
}
