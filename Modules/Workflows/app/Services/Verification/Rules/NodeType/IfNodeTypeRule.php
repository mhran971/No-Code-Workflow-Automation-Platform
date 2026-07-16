<?php

namespace Modules\Workflows\Services\Verification\Rules\NodeType;

use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\ExpressionLanguageValidator;
use Modules\Workflows\Services\Verification\Rules\NodeType\Concerns\VariableAvailability;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
use Modules\Workflows\Services\Verification\WorkflowVerificationResult;

class IfNodeTypeRule implements NodeTypeRule
{
    use VariableAvailability;

    protected const CODE_PREFIX = 'if_node';

    public function __construct(
        protected ExpressionLanguageValidator $expressionValidator,
    ) {}

    public function nodeType(): string
    {
        return 'if-node';
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
        $exprPath = "nodes[{$index}].config.conditionExpression";

        $expression = trim((string) ($config['conditionExpression'] ?? ''));

        if ($expression === '') {
            $result->addError('if_node.expression_missing', 'If node must have a condition expression.', $exprPath, $nodeId);
            $this->verifyOutgoingBranches($nodeId, $graph, $result);

            return;
        }

        $validation = $this->expressionValidator->validateBoolean($expression);

        if (! $validation->valid) {
            $result->addError('if_node.expression_invalid', 'If node condition expression is invalid: '.$validation->message, $exprPath, $nodeId);
            $this->verifyOutgoingBranches($nodeId, $graph, $result);

            return;
        }

        $this->verifyVariableNamespaces($validation->variables, $exprPath, $nodeId, $result);
        $this->verifyContextVariableAvailability($validation->variables, $nodeId, $exprPath, $graph, $result);
        $this->verifyOutgoingBranches($nodeId, $graph, $result);
    }

    protected function verifyVariableNamespaces(
        array $variables,
        string $path,
        ?string $nodeId,
        WorkflowVerificationResult $result,
    ): void {
        foreach ($variables as $variable) {
            $this->validateVariableNamespace($variable, self::CODE_PREFIX, $path, $nodeId, $result);
        }
    }

    protected function verifyContextVariableAvailability(
        array $variables,
        ?string $nodeId,
        string $path,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
    ): void {
        $contextVars = array_values(array_filter(
            $variables,
            fn (string $v): bool => str_starts_with($v, 'context.'),
        ));

        if ($contextVars === []) {
            return;
        }

        $available = $this->collectAvailableContextKeys($nodeId, $graph);

        if ($available === null) {
            return;
        }

        foreach ($contextVars as $variable) {
            $this->validateContextVariableExists($variable, $available, self::CODE_PREFIX, $path, $nodeId, $result);
        }

        if ($nodeId !== null) {
            $this->warnIfParallelPaths($nodeId, self::CODE_PREFIX, $path, $graph, $result);
        }
    }

    protected function verifyOutgoingBranches(
        ?string $nodeId,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
    ): void {
        if ($nodeId === null || ! $graph->hasNode($nodeId)) {
            return;
        }

        $count = count($graph->outgoing($nodeId));

        if ($count !== 2) {
            $result->addError(
                'if_node.branches_invalid',
                "If node must have exactly 2 outgoing branches, found {$count}.",
                null,
                $nodeId,
            );
        }
    }
}
