<?php

namespace Modules\Workflows\Services\Verification;

class WorkflowDefinitionGraph
{
    /**
     * @var array<string, array>
     */
    protected array $nodesById = [];

    /**
     * @var array<string, list<array>>
     */
    protected array $outgoing = [];

    /**
     * @var array<string, list<array>>
     */
    protected array $incoming = [];

    public function __construct(protected array $definition)
    {
        foreach ($this->nodes() as $node) {
            $id = $node['id'] ?? null;

            if (is_string($id) && $id !== '' && ! isset($this->nodesById[$id])) {
                $this->nodesById[$id] = $node;
                $this->outgoing[$id] = [];
                $this->incoming[$id] = [];
            }
        }

        foreach ($this->edges() as $edge) {
            $source = $edge['source_node_key'] ?? null;
            $target = $edge['target_node_key'] ?? null;

            if (is_string($source) && isset($this->nodesById[$source])) {
                $this->outgoing[$source][] = $edge;
            }

            if (is_string($target) && isset($this->nodesById[$target])) {
                $this->incoming[$target][] = $edge;
            }
        }
    }

    public function definition(): array
    {
        return $this->definition;
    }

    public function trigger(): ?array
    {
        return $this->definition['trigger'] ?? null;
    }

    /**
     * Returns the node id of the trigger node (the canvas node whose type
     * matches definition.trigger.type), or null if none is present.
     */
    public function triggerNodeId(): ?string
    {
        $triggerType = $this->definition['trigger']['type'] ?? null;

        if (! is_string($triggerType) || $triggerType === '') {
            return null;
        }

        foreach ($this->nodesById as $id => $node) {
            if (($node['type'] ?? null) === $triggerType) {
                return $id;
            }
        }

        return null;
    }

    public function nodes(): array
    {
        return $this->definition['nodes'] ?? [];
    }

    public function edges(): array
    {
        return $this->definition['edges'] ?? [];
    }

    public function hasNode(string $nodeId): bool
    {
        return isset($this->nodesById[$nodeId]);
    }

    public function node(string $nodeId): ?array
    {
        return $this->nodesById[$nodeId] ?? null;
    }

    public function nodeType(string $nodeId): ?string
    {
        return $this->nodesById[$nodeId]['type'] ?? null;
    }

    /**
     * @return list<string>
     */
    public function nodeIds(): array
    {
        return array_keys($this->nodesById);
    }

    /**
     * @return list<array>
     */
    public function outgoing(string $nodeId): array
    {
        return $this->outgoing[$nodeId] ?? [];
    }

    /**
     * @return list<array>
     */
    public function incoming(string $nodeId): array
    {
        return $this->incoming[$nodeId] ?? [];
    }

    /**
     * Returns nodes with no incoming edges, excluding the trigger node.
     * These are the structural entry points into the main flow.
     *
     * @return list<string>
     */
    public function entryNodeIds(): array
    {
        $triggerNodeId = $this->triggerNodeId();

        return array_values(array_filter(
            $this->nodeIds(),
            fn (string $nodeId): bool => $nodeId !== $triggerNodeId
                && $this->incoming($nodeId) === []
        ));
    }

    /**
     * Returns nodes with no outgoing edges — structural terminals.
     *
     * @return list<string>
     */
    public function terminalNodeIds(): array
    {
        return array_values(array_filter(
            $this->nodeIds(),
            fn (string $nodeId): bool => $this->outgoing($nodeId) === []
        ));
    }

    /**
     * @return list<string>
     */
    public function reachableFrom(string $startNodeId): array
    {
        $visited = [];
        $stack = [$startNodeId];

        while ($stack !== []) {
            $nodeId = array_pop($stack);

            if (! is_string($nodeId) || isset($visited[$nodeId]) || ! $this->hasNode($nodeId)) {
                continue;
            }

            $visited[$nodeId] = true;

            foreach ($this->outgoing($nodeId) as $edge) {
                $target = $edge['target_node_key'] ?? null;

                if (is_string($target) && ! isset($visited[$target])) {
                    $stack[] = $target;
                }
            }
        }

        return array_keys($visited);
    }

    /**
     * Returns all nodes that are upstream of the given node (can reach it), excluding itself.
     *
     * @return list<string>
     */
    public function ancestorNodeIds(string $nodeId): array
    {
        return array_values(array_filter(
            $this->nodesThatCanReachAny([$nodeId]),
            fn (string $id): bool => $id !== $nodeId,
        ));
    }

    /**
     * @param  list<string>  $terminalNodeIds
     * @return list<string>
     */
    public function nodesThatCanReachAny(array $terminalNodeIds): array
    {
        $visited = [];
        $stack = $terminalNodeIds;

        while ($stack !== []) {
            $nodeId = array_pop($stack);

            if (! is_string($nodeId) || isset($visited[$nodeId]) || ! $this->hasNode($nodeId)) {
                continue;
            }

            $visited[$nodeId] = true;

            foreach ($this->incoming($nodeId) as $edge) {
                $source = $edge['source_node_key'] ?? null;

                if (is_string($source) && ! isset($visited[$source])) {
                    $stack[] = $source;
                }
            }
        }

        return array_keys($visited);
    }

    /**
     * Returns node ids in a topological order (reverse DFS post-order). For a DAG this is a
     * valid topological sort; cycles (e.g. explicit loops) are tolerated — back edges simply
     * don't reorder their endpoints, so the result is always a complete, deterministic ordering.
     *
     * @return list<string>
     */
    public function topologicalOrder(): array
    {
        $visited = [];
        $postorder = [];

        $start = $this->triggerNodeId();
        $roots = $start !== null ? [$start] : [];
        foreach ($this->nodeIds() as $nodeId) {
            $roots[] = $nodeId;
        }

        foreach ($roots as $root) {
            $this->visitForTopoOrder($root, $visited, $postorder);
        }

        return array_reverse($postorder);
    }

    /**
     * @param  array<string, bool>  $visited
     * @param  list<string>  $postorder
     */
    protected function visitForTopoOrder(string $nodeId, array &$visited, array &$postorder): void
    {
        if (isset($visited[$nodeId]) || ! $this->hasNode($nodeId)) {
            return;
        }

        $visited[$nodeId] = true;

        foreach ($this->outgoing($nodeId) as $edge) {
            $target = $edge['target_node_key'] ?? null;

            if (is_string($target)) {
                $this->visitForTopoOrder($target, $visited, $postorder);
            }
        }

        $postorder[] = $nodeId;
    }

    /**
     * @return list<array{source: string, target: string, edge_id: ?string}>
     */
    public function invalidCycleEdges(): array
    {
        $invalidEdges = [];
        $visited = [];
        $visiting = [];

        foreach ($this->nodeIds() as $nodeId) {
            $this->visitForCycles($nodeId, $visited, $visiting, $invalidEdges);
        }

        return array_values($invalidEdges);
    }

    protected function visitForCycles(string $nodeId, array &$visited, array &$visiting, array &$invalidEdges): void
    {
        if (isset($visited[$nodeId])) {
            return;
        }

        $visiting[$nodeId] = true;

        foreach ($this->outgoing($nodeId) as $edge) {
            $target = $edge['target_node_key'] ?? null;

            if (! is_string($target) || ! $this->hasNode($target)) {
                continue;
            }

            if (isset($visiting[$target])) {
                if (! $this->edgeCanBelongToExplicitLoop($nodeId, $target)) {
                    $key = $nodeId.'->'.$target;
                    $invalidEdges[$key] = [
                        'source' => $nodeId,
                        'target' => $target,
                        'edge_id' => $edge['id'] ?? null,
                    ];
                }

                continue;
            }

            $this->visitForCycles($target, $visited, $visiting, $invalidEdges);
        }

        unset($visiting[$nodeId]);
        $visited[$nodeId] = true;
    }

    protected function edgeCanBelongToExplicitLoop(string $sourceNodeId, string $targetNodeId): bool
    {
        return $this->nodeType($sourceNodeId) === 'loop' || $this->nodeType($targetNodeId) === 'loop';
    }
}
