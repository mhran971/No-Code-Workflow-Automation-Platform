<?php

namespace Modules\Workflows\Services;

use Modules\Auth\Models\User;
use Modules\Workflows\Enums\VerificationMode;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Rules\ContextualVerificationRule;
use Modules\Workflows\Services\Verification\Rules\DataFlowVerificationRule;
use Modules\Workflows\Services\Verification\Rules\ExpressionVerificationRule;
use Modules\Workflows\Services\Verification\Rules\FormTriggerVerificationRule;
use Modules\Workflows\Services\Verification\Rules\GraphControlFlowVerificationRule;
use Modules\Workflows\Services\Verification\Rules\NodeType\AiGeneratorNodeTypeRule;
use Modules\Workflows\Services\Verification\Rules\NodeType\DynamicEntryNodeTypeRule;
use Modules\Workflows\Services\Verification\Rules\NodeType\ForkNodeTypeRule;
use Modules\Workflows\Services\Verification\Rules\NodeType\IfNodeTypeRule;
use Modules\Workflows\Services\Verification\Rules\NodeType\MergeNodeTypeRule;
use Modules\Workflows\Services\Verification\Rules\NodeType\SendEmailNodeTypeRule;
use Modules\Workflows\Services\Verification\Rules\NodeType\SubWorkflowNodeTypeRule;
use Modules\Workflows\Services\Verification\Rules\NodeType\SwitchNodeTypeRule;
use Modules\Workflows\Services\Verification\Rules\NodeType\TaskNodeTypeRule;
use Modules\Workflows\Services\Verification\Rules\NodeType\TerminationNodeTypeRule;
use Modules\Workflows\Services\Verification\Rules\NodeTypeVerificationRule;
use Modules\Workflows\Services\Verification\Rules\StructuredControlFlowVerificationRule;
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
        protected StructuredControlFlowVerificationRule $structuredControlFlowRule,
        protected ExpressionVerificationRule $expressionRule,
        protected ContextualVerificationRule $contextualRule,
        protected NodeTypeVerificationRule $nodeTypeRule,
        protected DataFlowVerificationRule $dataFlowRule,
        protected FormTriggerVerificationRule $formTriggerRule,
        protected IfNodeTypeRule $ifNodeTypeRule,
        protected ForkNodeTypeRule $forkNodeTypeRule,
        protected SwitchNodeTypeRule $switchNodeTypeRule,
        protected MergeNodeTypeRule $mergeNodeTypeRule,
        protected TaskNodeTypeRule $taskNodeTypeRule,
        protected SendEmailNodeTypeRule $sendEmailNodeTypeRule,
        protected AiGeneratorNodeTypeRule $aiGeneratorNodeTypeRule,
        protected TerminationNodeTypeRule $terminationNodeTypeRule,
        protected SubWorkflowNodeTypeRule $subWorkflowNodeTypeRule,
        protected DynamicEntryNodeTypeRule $dynamicEntryNodeTypeRule,
    ) {
        $this->nodeTypeRule->register($this->ifNodeTypeRule);
        $this->nodeTypeRule->register($this->forkNodeTypeRule);
        $this->nodeTypeRule->register($this->switchNodeTypeRule);
        $this->nodeTypeRule->register($this->mergeNodeTypeRule);
        $this->nodeTypeRule->register($this->taskNodeTypeRule);
        $this->nodeTypeRule->register($this->sendEmailNodeTypeRule);
        $this->nodeTypeRule->register($this->aiGeneratorNodeTypeRule);
        $this->nodeTypeRule->register($this->terminationNodeTypeRule);
        $this->nodeTypeRule->register($this->subWorkflowNodeTypeRule);
        $this->nodeTypeRule->register($this->dynamicEntryNodeTypeRule);
    }

    public function verify(array $definition, ?Workflow $workflow = null, ?User $actor = null, VerificationMode $mode = VerificationMode::Full): WorkflowVerificationResult
    {
        $normalizedDefinition = $this->normalizer->normalize($definition);
        $graph = new WorkflowDefinitionGraph($normalizedDefinition);
        $result = new WorkflowVerificationResult;

        foreach ($this->rules() as $rule) {
            $rule->verify($normalizedDefinition, $graph, $result, $workflow, $actor, $mode);
        }

        return $result;
    }

    protected function rules(): array
    {
        return [
            $this->syntaxRule,
            $this->graphRule,
            $this->structuredControlFlowRule,
            $this->expressionRule,
            $this->contextualRule,
            $this->nodeTypeRule,
            $this->dataFlowRule,
            $this->formTriggerRule,
        ];
    }
}
