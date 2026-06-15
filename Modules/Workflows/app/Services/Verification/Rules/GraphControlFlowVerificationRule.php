<?php

namespace Modules\Workflows\Services\Verification\Rules;

use Modules\Auth\Models\User;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
use Modules\Workflows\Services\Verification\WorkflowVerificationResult;

class GraphControlFlowVerificationRule implements VerificationRule
{
    public function verify(
        array $definition,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
        ?Workflow $workflow = null,
        ?User $actor = null,
    ): void {
        if ($graph->nodes() === []) {
            return;
        }

        $triggerNodeId = $graph->triggerNodeId();
        $terminalNodeIds = $graph->terminalNodeIds();

        $this->verifyTriggerConnected($graph, $triggerNodeId, $result);

        if ($terminalNodeIds === []) {
            $result->addError('graph.terminal_missing', 'Workflow must contain at least one terminal node.', 'nodes');
        }

        if ($triggerNodeId !== null) {
            $this->verifyNodeDegrees($graph, $triggerNodeId, $terminalNodeIds, $result);
            $this->verifyReachability($graph, $triggerNodeId, $terminalNodeIds, $result);
        }

        $this->verifyCycles($graph, $result);
        $this->verifyParallelJoinMetadata($graph, $result);
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
                $result->addError('graph.incoming_missing', "The Node must have at least one incoming edge.", null, $nodeId);
            }

            if (! isset($terminalLookup[$nodeId]) && $graph->outgoing($nodeId) === []) {
                $result->addError('graph.outgoing_missing', "The Node must have at least one outgoing edge.", null, $nodeId);
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
                $result->addError('graph.unreachable', "The Node is not reachable from the trigger.", null, $nodeId);
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

    protected function verifyParallelJoinMetadata(WorkflowDefinitionGraph $graph, WorkflowVerificationResult $result): void
    {
        foreach ($graph->edges() as $index => $edge) {
            if (($edge['branch_type'] ?? 'default') !== 'parallel') {
                continue;
            }

            $strategy = $edge['parallel_strategy'] ?? 'fork_join';

            if ($strategy === 'fire_and_forget') {
                continue;
            }

            $joinNodeKey = trim((string) ($edge['join_node_key'] ?? ''));
            $edgeId = $edge['id'] ?? null;
            $path = "edges[{$index}].join_node_key";

            if ($joinNodeKey === '') {
                $result->addError('parallel.join_missing', 'Parallel fork/join edge must declare join_node_key.', $path, null, $edgeId);
                continue;
            }

            if (! $graph->hasNode($joinNodeKey)) {
                $result->addError('parallel.join_unknown', "Parallel join node '{$joinNodeKey}' does not exist.", $path, $joinNodeKey, $edgeId);
                continue;
            }

            if ($graph->nodeType($joinNodeKey) !== 'parallel-join') {
                $result->addError('parallel.join_type_invalid', "Parallel join node '{$joinNodeKey}' must be a parallel-join node.", $path, $joinNodeKey, $edgeId);
            }
        }
    }
}
