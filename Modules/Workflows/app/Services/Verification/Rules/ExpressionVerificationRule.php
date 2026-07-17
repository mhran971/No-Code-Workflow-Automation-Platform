<?php

namespace Modules\Workflows\Services\Verification\Rules;

use Modules\Auth\Models\User;
use Modules\Workflows\Enums\VerificationMode;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\ExpressionLanguageValidator;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
use Modules\Workflows\Services\Verification\WorkflowVerificationResult;

class ExpressionVerificationRule implements VerificationRule
{
    public function __construct(
        protected ExpressionLanguageValidator $expressionValidator
    ) {}

    public function verify(
        array $definition,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
        ?Workflow $workflow = null,
        ?User $actor = null,
        VerificationMode $mode = VerificationMode::Full,
    ): void {
        $this->verifyEdgeExpressions($graph, $result);
        $this->verifyNodeExpressions($graph, $result);
    }

    protected function verifyEdgeExpressions(WorkflowDefinitionGraph $graph, WorkflowVerificationResult $result): void
    {
        foreach ($graph->edges() as $index => $edge) {
            if (($edge['branch_type'] ?? 'default') !== 'conditional' || (bool) ($edge['is_default_branch'] ?? false)) {
                continue;
            }

            $expression = trim((string) ($edge['condition_expression'] ?? ''));
            $edgeId = $edge['id'] ?? null;
            $path = "edges[{$index}].condition_expression";

            if ($expression === '') {
                $result->addError('expression.condition_missing', 'Conditional edge must declare condition_expression.', $path, null, $edgeId);

                continue;
            }

            $validation = $this->expressionValidator->validateBoolean($expression);

            if (! $validation->valid) {
                $result->addError(
                    'expression.condition_invalid',
                    'Conditional edge expression is invalid: '.$validation->message,
                    $path,
                    null,
                    $edgeId
                );
            }
        }
    }

    protected function verifyNodeExpressions(WorkflowDefinitionGraph $graph, WorkflowVerificationResult $result): void
    {
        foreach ($graph->nodes() as $index => $node) {
            $config = $node['config'] ?? [];
            $nodeId = $node['id'] ?? null;

            foreach (['expression', 'condition'] as $configKey) {
                if (! isset($config[$configKey]) || ! is_string($config[$configKey]) || trim($config[$configKey]) === '') {
                    continue;
                }

                $validation = $this->expressionValidator->validateBoolean($config[$configKey]);

                if (! $validation->valid) {
                    $result->addError(
                        'expression.node_invalid',
                        "Node expression '{$configKey}' is invalid: ".$validation->message,
                        "nodes[{$index}].config.{$configKey}",
                        is_string($nodeId) ? $nodeId : null
                    );
                }
            }
        }
    }
}
