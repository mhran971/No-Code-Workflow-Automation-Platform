<?php

namespace Modules\Workflows\Services;

use Modules\Auth\Models\User;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Rules\ContextualVerificationRule;
use Modules\Workflows\Services\Verification\Rules\ExpressionVerificationRule;
use Modules\Workflows\Services\Verification\Rules\GraphControlFlowVerificationRule;
use Modules\Workflows\Services\Verification\Rules\SyntaxVerificationRule;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
use Modules\Workflows\Services\Verification\WorkflowDefinitionNormalizer;
use Modules\Workflows\Services\Verification\WorkflowVerificationResult;

class WorkflowVerificationService
{
    public function __construct(
        protected WorkflowDefinitionNormalizer $normalizer,
        protected SyntaxVerificationRule $syntaxRule,
        protected GraphControlFlowVerificationRule $graphRule,
        protected ExpressionVerificationRule $expressionRule,
        protected ContextualVerificationRule $contextualRule,
    ) {}

    public function verify(array $definition, ?Workflow $workflow = null, ?User $actor = null): WorkflowVerificationResult
    {
        $normalizedDefinition = $this->normalizer->normalize($definition);
        $graph = new WorkflowDefinitionGraph($normalizedDefinition);
        $result = new WorkflowVerificationResult;

        foreach ($this->rules() as $rule) {
            $rule->verify($normalizedDefinition, $graph, $result, $workflow, $actor);
        }

        return $result;
    }

    protected function rules(): array
    {
        return [
            $this->syntaxRule,
            $this->graphRule,
            $this->expressionRule,
            $this->contextualRule,
        ];
    }
}
