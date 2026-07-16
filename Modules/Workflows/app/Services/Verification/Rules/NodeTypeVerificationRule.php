<?php

namespace Modules\Workflows\Services\Verification\Rules;

use Modules\Auth\Models\User;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Rules\NodeType\NodeTypeRule;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
use Modules\Workflows\Services\Verification\WorkflowVerificationResult;

class NodeTypeVerificationRule implements VerificationRule
{
    /** @var array<string, NodeTypeRule> */
    protected array $rules = [];

    public function register(NodeTypeRule $rule): void
    {
        $this->rules[$rule->nodeType()] = $rule;
    }

    public function verify(
        array $definition,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
        ?Workflow $workflow = null,
        ?User $actor = null,
    ): void {
        foreach ($graph->nodes() as $index => $node) {
            $type = $node['type'] ?? '';

            if (isset($this->rules[$type])) {
                $this->rules[$type]->verify($node, (int) $index, $graph, $result, $workflow);
            }
        }
    }
}
