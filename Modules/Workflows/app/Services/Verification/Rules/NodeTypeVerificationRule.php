<?php

namespace Modules\Workflows\Services\Verification\Rules;

use Modules\Auth\Models\User;
use Modules\Workflows\Enums\VerificationMode;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\Rules\NodeType\NodeTypeRule;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;

class NodeTypeVerificationRule implements VerificationRule
{
    use Concerns\SegmentSkipDisabled;

    /** @var array<string, NodeTypeRule> */
    protected array $rules = [];

    public function __construct(iterable $rules)
    {
        foreach ($rules as $rule) {
            $this->rules[$rule->nodeType()] = $rule;
        }
    }

    public function verify(
        array $definition,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
        ?Workflow $workflow = null,
        ?User $actor = null,
        VerificationMode $mode = VerificationMode::Full,
    ): void {
        foreach ($graph->nodes() as $index => $node) {
            $type = $node['type'] ?? '';

            if (isset($this->rules[$type])) {
                $this->rules[$type]->verify($node, (int) $index, $graph, $result, $workflow);
            }
        }
    }
}
