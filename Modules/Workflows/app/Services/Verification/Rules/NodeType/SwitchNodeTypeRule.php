<?php

namespace Modules\Workflows\Services\Verification\Rules\NodeType;

use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Rules\NodeType\Concerns\VariableAvailability;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
use Modules\Workflows\Services\Verification\WorkflowVerificationResult;

class SwitchNodeTypeRule implements NodeTypeRule
{
    use VariableAvailability;

    protected const CODE_PREFIX = 'switch';

    public function nodeType(): string
    {
        return 'switch';
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
        $varPath = "nodes[{$index}].config.variable";

        $variable = trim((string) ($config['variable'] ?? ''));

        if ($variable === '') {
            $result->addError('switch.variable_missing', 'Switch node must specify a variable to evaluate.', $varPath, $nodeId);
        } else {
            $namespaceOk = $this->validateVariableNamespace($variable, self::CODE_PREFIX, $varPath, $nodeId, $result);

            if ($namespaceOk && str_starts_with($variable, 'context.')) {
                $available = $this->collectAvailableContextKeys($nodeId, $graph);

                if ($available !== null) {
                    $this->validateContextVariableExists($variable, $available, self::CODE_PREFIX, $varPath, $nodeId, $result);
                }
            }

            if ($namespaceOk && $nodeId !== null) {
                $this->warnIfParallelPaths($nodeId, self::CODE_PREFIX, $varPath, $graph, $result);
            }
        }

        $this->verifyOptions($config, $index, $nodeId, $graph, $result);
    }

    protected function verifyOptions(
        array $config,
        int $index,
        ?string $nodeId,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
    ): void {
        $optionsPath = "nodes[{$index}].config.options";
        $raw = is_array($config['options'] ?? null) ? $config['options'] : [];

        $validOptions = array_values(array_filter(
            $raw,
            fn ($v): bool => is_string($v) && trim($v) !== '',
        ));

        if ($validOptions === []) {
            $result->addError(
                'switch.options_missing',
                'Switch node must have at least one option to route on.',
                $optionsPath,
                $nodeId,
            );

            return;
        }

        if ($nodeId !== null && $graph->hasNode($nodeId)) {
            $expected = count($validOptions) + 1; // options + 1 default branch
            $actual = count($graph->outgoing($nodeId));

            if ($actual !== $expected) {
                $optionCount = count($validOptions);
                $result->addError(
                    'switch.branches_mismatch',
                    "Switch node has {$actual} outgoing branch(es) but needs {$expected} ({$optionCount} option(s) + 1 default branch).",
                    null,
                    $nodeId,
                );
            }
        }
    }
}
