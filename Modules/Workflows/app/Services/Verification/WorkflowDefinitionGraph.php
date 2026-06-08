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
     * @return list<string>
     */
    public function explicitEntryNodeIds(): array
    {
        return array_values(array_filter(
            $this->nodeIds(),
            fn (string $nodeId): bool => (bool) ($this->nodesById[$nodeId]['is_entry_point'] ?? false)
        ));
    }

    /**
     * @return list<string>
     */
    public function inferredEntryNodeIds(): array
    {
        return array_values(array_filter(
            $this->nodeIds(),
            fn (string $nodeId): bool => $this->incoming($nodeId) === []
        ));
    }

    /**
     * @return list<string>
     */
    public function terminalNodeIds(): array
    {
        return array_values(array_filter(
            $this->nodeIds(),
            fn (string $nodeId): bool => (bool) ($this->nodesById[$nodeId]['is_terminal'] ?? false)
                || $this->outgoing($nodeId) === []
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
     * @param list<string> $terminalNodeIds
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
