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

        $entryNodeId = $this->resolveEntryNodeId($graph, $result);
        $terminalNodeIds = $graph->terminalNodeIds();

        if ($terminalNodeIds === []) {
            $result->addError('graph.terminal_missing', 'Workflow must contain at least one terminal node.', 'nodes');
        }

        if ($entryNodeId !== null) {
            $this->verifyNodeDegrees($graph, $entryNodeId, $terminalNodeIds, $result);
            $this->verifyReachability($graph, $entryNodeId, $terminalNodeIds, $result);
        }

        $this->verifyCycles($graph, $result);
        $this->verifyParallelJoinMetadata($graph, $result);
    }

    protected function resolveEntryNodeId(WorkflowDefinitionGraph $graph, WorkflowVerificationResult $result): ?string
    {
        $explicitEntries = $graph->explicitEntryNodeIds();

        if (count($explicitEntries) === 1) {
            return $explicitEntries[0];
        }

        if (count($explicitEntries) > 1) {
            $result->addError('graph.entry_multiple', 'Workflow must have exactly one entry node.', 'nodes');

            return null;
        }

        $inferredEntries = $graph->inferredEntryNodeIds();

        if (count($inferredEntries) === 1) {
            return $inferredEntries[0];
        }

        $result->addError(
            count($inferredEntries) === 0 ? 'graph.entry_missing' : 'graph.entry_ambiguous',
            count($inferredEntries) === 0
                ? 'Workflow must have exactly one entry node.'
                : 'Workflow has multiple nodes without incoming edges; mark one as the entry point.',
            'nodes'
        );

        return null;
    }

    protected function verifyNodeDegrees(
        WorkflowDefinitionGraph $graph,
        string $entryNodeId,
        array $terminalNodeIds,
        WorkflowVerificationResult $result,
    ): void {
        $terminalLookup = array_fill_keys($terminalNodeIds, true);

        foreach ($graph->nodeIds() as $nodeId) {
            if ($nodeId !== $entryNodeId && $graph->incoming($nodeId) === []) {
                $result->addError('graph.incoming_missing', "Node '{$nodeId}' must have at least one incoming edge.", null, $nodeId);
            }

            if (! isset($terminalLookup[$nodeId]) && $graph->outgoing($nodeId) === []) {
                $result->addError('graph.outgoing_missing', "Node '{$nodeId}' must have at least one outgoing edge.", null, $nodeId);
            }
        }
    }

    protected function verifyReachability(
        WorkflowDefinitionGraph $graph,
        string $entryNodeId,
        array $terminalNodeIds,
        WorkflowVerificationResult $result,
    ): void {
        $reachable = array_fill_keys($graph->reachableFrom($entryNodeId), true);

        foreach ($graph->nodeIds() as $nodeId) {
            if (! isset($reachable[$nodeId])) {
                $result->addError('graph.unreachable', "Node '{$nodeId}' is not reachable from the entry node.", null, $nodeId);
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
