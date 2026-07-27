<?php

namespace Modules\Workflows\Services\Verification\Rules\NodeType;

use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;

class DynamicEntryNodeTypeRule implements NodeTypeRule
{
    protected const CODE_PREFIX = 'dynamic_entry';

    public function nodeType(): string
    {
        return 'dynamic-entry';
    }

    public function verify(
        array $node,
        int $index,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
        ?Workflow $workflow = null,
    ): void {
        $nodeId = is_string($node['id'] ?? null) ? $node['id'] : null;

        if ($nodeId === null) {
            return;
        }

        // Must have at least one outgoing edge.
        if ($graph->outgoing($nodeId) === []) {
            $result->addError(
                'dynamic_entry.no_outgoing_edges',
                'An Entry Point node must have at least one outgoing edge.',
                "nodes[{$index}]",
                $nodeId,
            );
        }
    }
}
