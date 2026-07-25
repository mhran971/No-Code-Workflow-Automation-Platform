<?php

namespace Modules\Workflows\Services\Verification\Rules\NodeType;

use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;

class TerminationNodeTypeRule implements NodeTypeRule
{
    protected const CODE_PREFIX = 'termination_node';

    public function nodeType(): string
    {
        return 'termination-node';
    }

    public function verify(
        array $node,
        int $index,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
        ?Workflow $workflow = null,
    ): void {
        $nodeId = is_string($node['id'] ?? null) ? $node['id'] : null;

        if ($nodeId !== null && $graph->outgoing($nodeId) !== []) {
            $result->addError(
                'termination_node.has_outgoing_edges',
                'A Termination node must not have outgoing edges.',
                "nodes[{$index}]",
                $nodeId,
            );
        }
    }
}
