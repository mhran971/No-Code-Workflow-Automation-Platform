<?php

namespace Modules\Workflows\Services\Verification\Rules;

use Modules\Auth\Models\User;
use Modules\Workflows\Enums\VerificationMode;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;

class GraphControlFlowVerificationRule implements VerificationRule
{
    public function verify(
        array $definition,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
        ?Workflow $workflow = null,
        ?User $actor = null,
        VerificationMode $mode = VerificationMode::Full,
    ): void {
        if ($graph->nodes() === []) {
            return;
        }

        $triggerNodeId = $graph->triggerNodeId();
        $terminalNodeIds = $graph->terminalNodeIds();

        if ($mode !== VerificationMode::Segment) {
            $this->verifyTriggerConnected($graph, $triggerNodeId, $result);
        }

        if ($terminalNodeIds === []) {
            $result->addError('graph.terminal_missing', 'Workflow must contain at least one terminal node.', 'nodes');
        }

        if ($triggerNodeId !== null) {
            $this->verifyNodeDegrees($graph, $triggerNodeId, $terminalNodeIds, $result);
            $this->verifyReachability($graph, $triggerNodeId, $terminalNodeIds, $result);
        } elseif ($mode === VerificationMode::Segment) {
            $entryNodeIds = $graph->entryNodeIds();
            if ($entryNodeIds !== []) {
                $this->verifySegmentReachability($graph, $entryNodeIds, $terminalNodeIds, $result);
            }
        }

        $this->verifyCycles($graph, $result);
    }

    protected function verifyTriggerConnected(
        WorkflowDefinitionGraph $graph,
        ?string $triggerNodeId,
        WorkflowVerificationResult $result,
    ): void {
        if ($triggerNodeId === null) {
            $result->addError('graph.trigger_missing', 'Workflow must have a trigger node.', 'nodes');

            return;
        }

        if ($graph->outgoing($triggerNodeId) === []) {
            $result->addError('graph.trigger_disconnected', 'Trigger node must be connected to at least one other node.', null, $triggerNodeId);
        }
    }

    protected function verifyNodeDegrees(
        WorkflowDefinitionGraph $graph,
        string $triggerNodeId,
        array $terminalNodeIds,
        WorkflowVerificationResult $result,
    ): void {
        $terminalLookup = array_fill_keys($terminalNodeIds, true);

        foreach ($graph->nodeIds() as $nodeId) {
            // Trigger node has no incoming edges and is not required to be a terminal.
            if ($nodeId === $triggerNodeId) {
                continue;
            }

            if ($graph->incoming($nodeId) === []) {
                $result->addError('graph.incoming_missing', 'The Node must have at least one incoming edge.', null, $nodeId);
            }

            if (! isset($terminalLookup[$nodeId]) && $graph->outgoing($nodeId) === []) {
                $result->addError('graph.outgoing_missing', 'The Node must have at least one outgoing edge.', null, $nodeId);
            }
        }
    }

    protected function verifyReachability(
        WorkflowDefinitionGraph $graph,
        string $triggerNodeId,
        array $terminalNodeIds,
        WorkflowVerificationResult $result,
    ): void {
        $reachable = array_fill_keys($graph->reachableFrom($triggerNodeId), true);

        foreach ($graph->nodeIds() as $nodeId) {
            // The trigger itself is always the start — skip self-check.
            if ($nodeId === $triggerNodeId) {
                continue;
            }

            if (! isset($reachable[$nodeId])) {
                $result->addError('graph.unreachable', 'The Node is not reachable from the trigger.', null, $nodeId);
            }
        }

        if ($terminalNodeIds === []) {
            return;
        }

        $canReachTerminal = array_fill_keys($graph->nodesThatCanReachAny($terminalNodeIds), true);

        foreach ($graph->nodeIds() as $nodeId) {
            if ($nodeId === $triggerNodeId) {
                continue;
            }

            if (! isset($canReachTerminal[$nodeId])) {
                $result->addError('graph.dead_end', "Node '{$nodeId}' is not on a path to a terminal node.", null, $nodeId);
            }
        }
    }

    protected function verifyCycles(WorkflowDefinitionGraph $graph, WorkflowVerificationResult $result): void
    {
        foreach ($graph->invalidCycleEdges() as $cycleEdge) {
            $result->addError(
                'graph.unstructured_cycle',
                "Unstructured cycle detected from '{$cycleEdge['source']}' to '{$cycleEdge['target']}'.",
                null,
                $cycleEdge['source'],
                $cycleEdge['edge_id']
            );
        }
    }

    /**
     * Segment-mode reachability: all nodes must be reachable from at least one entry node
     * (nodes with no incoming edges), and all non-terminal nodes must reach a terminal node.
     */
    protected function verifySegmentReachability(
        WorkflowDefinitionGraph $graph,
        array $entryNodeIds,
        array $terminalNodeIds,
        WorkflowVerificationResult $result,
    ): void {
        $reachable = [];
        foreach ($entryNodeIds as $entryId) {
            foreach ($graph->reachableFrom($entryId) as $nodeId) {
                $reachable[$nodeId] = true;
            }
        }

        foreach ($graph->nodeIds() as $nodeId) {
            if (! isset($reachable[$nodeId])) {
                $result->addError('graph.unreachable', 'The Node is not reachable from any entry node.', null, $nodeId);
            }
        }

        if ($terminalNodeIds === []) {
            return;
        }

        $canReachTerminal = array_fill_keys($graph->nodesThatCanReachAny($terminalNodeIds), true);

        foreach ($graph->nodeIds() as $nodeId) {
            if (! isset($canReachTerminal[$nodeId])) {
                $result->addError('graph.dead_end', "Node '{$nodeId}' is not on a path to a terminal node.", null, $nodeId);
            }
        }
    }
}
