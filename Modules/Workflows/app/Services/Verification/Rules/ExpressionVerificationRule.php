<?php

namespace Modules\Workflows\Services\Verification\Rules;

use Modules\Auth\Models\User;
use Modules\Workflows\Enums\VerificationMode;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\ExpressionLanguageValidator;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;

class ExpressionVerificationRule implements VerificationRule
{
    use Concerns\SegmentSkipDisabled;

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
            $expression = trim((string) ($edge['condition_expression'] ?? ''));
            $edgeId = $edge['id'] ?? null;

            if ($expression === '' || (bool) ($edge['is_default_branch'] ?? false)) {
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
