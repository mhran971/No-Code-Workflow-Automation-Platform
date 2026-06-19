<?php

namespace Modules\Workflows\Services\Verification;

/**
 * Forward data-flow analysis over a workflow graph. For every node it computes the set of
 * `context.*` variables that are **guaranteed** (available on every incoming path) and **possible**
 * (available on at least one). It also detects, at parallel merges, variables written by two or more
 * concurrent branches — a data-loss conflict, since one branch's value silently overwrites another's.
 *
 * Producers of variables: the trigger (manual-trigger `variables`, form-trigger `formFields`) and any
 * node that declares `outputVariables` (or a single `outputVariable`).
 */
class DataFlowAnalyzer
{
    private const PARALLEL_MERGE_MODE = 'parallel';

    /** @var array<string, array<string, true>> */
    private array $guaranteed = [];

    /** @var array<string, array<string, true>> */
    private array $possible = [];

    /** @var array<string, array<string, true>> */
    private array $outputs = [];

    public function __construct(private readonly WorkflowDefinitionGraph $graph) {}

    public function analyze(): DataFlowResult
    {
        $triggerNodeId = $this->graph->triggerNodeId();

        foreach ($this->graph->nodeIds() as $nodeId) {
            $this->outputs[$nodeId] = $this->keySet($this->outputsOf($nodeId, $triggerNodeId));
        }

        $conflicts = [];

        foreach ($this->graph->topologicalOrder() as $nodeId) {
            $this->computeAvailability($nodeId, $conflicts);
        }

        $producers = [];
        foreach ($this->outputs as $set) {
            $producers += $set;
        }

        return new DataFlowResult(
            $this->toLists($this->guaranteed),
            $this->toLists($this->possible),
            $conflicts,
            array_keys($producers),
        );
    }

    /**
     * @param  list<array{node:string,variable:string}>  $conflicts
     */
    private function computeAvailability(string $nodeId, array &$conflicts): void
    {
        $predecessors = $this->predecessorIds($nodeId);

        if ($predecessors === []) {
            $this->guaranteed[$nodeId] = [];
            $this->possible[$nodeId] = [];

            return;
        }

        // What each predecessor passes downstream = what was available on it plus what it produced.
        $leaving = [];
        foreach ($predecessors as $predId) {
            $leaving[] = ($this->guaranteed[$predId] ?? []) + $this->outputs[$predId];
        }

        $leavingPossible = [];
        foreach ($predecessors as $predId) {
            $leavingPossible[] = ($this->possible[$predId] ?? []) + $this->outputs[$predId];
        }

        $this->possible[$nodeId] = $this->unionAll($leavingPossible);

        if ($this->isParallelMerge($nodeId)) {
            // All branches execute: their variables combine, and overlapping writes are conflicts.
            $this->guaranteed[$nodeId] = $this->unionAll($leaving);
            $this->collectConflicts($nodeId, $leaving, $conflicts);

            return;
        }

        // Exactly one branch executes: only variables present on every path are guaranteed.
        $this->guaranteed[$nodeId] = $this->intersectAll($leaving);
    }

    /**
     * @param  list<array<string, true>>  $leaving
     * @param  list<array{node:string,variable:string}>  $conflicts
     */
    private function collectConflicts(string $nodeId, array $leaving, array &$conflicts): void
    {
        $counts = [];
        foreach ($leaving as $set) {
            foreach (array_keys($set) as $key) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        foreach ($counts as $variable => $count) {
            if ($count >= 2) {
                $conflicts[] = ['node' => $nodeId, 'variable' => $variable];
            }
        }
    }

    private function isParallelMerge(string $nodeId): bool
    {
        if ($this->graph->nodeType($nodeId) !== 'merge') {
            return false;
        }

        $node = $this->graph->node($nodeId) ?? [];
        $config = is_array($node['config'] ?? null) ? $node['config'] : [];

        return trim((string) ($config['mergeMode'] ?? '')) === self::PARALLEL_MERGE_MODE;
    }

    /**
     * @return list<string>
     */
    private function outputsOf(string $nodeId, ?string $triggerNodeId): array
    {
        if ($nodeId === $triggerNodeId) {
            return $this->triggerProducedKeys();
        }

        $node = $this->graph->node($nodeId) ?? [];
        $config = is_array($node['config'] ?? null) ? $node['config'] : [];

        $keys = [];

        $declared = $config['outputVariables'] ?? [];
        if (is_array($declared)) {
            foreach ($declared as $key) {
                if (is_string($key) && trim($key) !== '') {
                    $keys[] = $this->normalizeKey($key);
                }
            }
        }

        if (isset($config['outputVariable']) && is_string($config['outputVariable']) && trim($config['outputVariable']) !== '') {
            $keys[] = $this->normalizeKey($config['outputVariable']);
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function triggerProducedKeys(): array
    {
        $trigger = $this->graph->trigger() ?? [];
        $type = $trigger['type'] ?? null;
        $config = is_array($trigger['config'] ?? null) ? $trigger['config'] : [];

        $source = match ($type) {
            'manual-trigger' => $config['variables'] ?? [],
            'form-trigger' => $config['formFields'] ?? [],
            default => [],
        };

        if (! is_array($source)) {
            return [];
        }

        $keys = [];
        foreach ($source as $entry) {
            if (is_array($entry) && isset($entry['key']) && is_string($entry['key']) && trim($entry['key']) !== '') {
                $keys[] = 'context.'.trim($entry['key']);
            }
        }

        return $keys;
    }

    private function normalizeKey(string $key): string
    {
        $key = trim($key);
        $bare = str_starts_with($key, 'context.') ? substr($key, strlen('context.')) : $key;

        return 'context.'.$bare;
    }

    /**
     * @return list<string>
     */
    private function predecessorIds(string $nodeId): array
    {
        $ids = [];
        foreach ($this->graph->incoming($nodeId) as $edge) {
            $source = $edge['source_node_key'] ?? null;
            if (is_string($source)) {
                $ids[] = $source;
            }
        }

        return $ids;
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, true>
     */
    private function keySet(array $keys): array
    {
        $set = [];
        foreach ($keys as $key) {
            $set[$key] = true;
        }

        return $set;
    }

    /**
     * @param  list<array<string, true>>  $sets
     * @return array<string, true>
     */
    private function unionAll(array $sets): array
    {
        $union = [];
        foreach ($sets as $set) {
            $union += $set;
        }

        return $union;
    }

    /**
     * @param  list<array<string, true>>  $sets
     * @return array<string, true>
     */
    private function intersectAll(array $sets): array
    {
        if ($sets === []) {
            return [];
        }

        $result = array_shift($sets);
        foreach ($sets as $set) {
            $result = array_intersect_key($result, $set);
        }

        return $result;
    }

    /**
     * @param  array<string, array<string, true>>  $sets
     * @return array<string, list<string>>
     */
    private function toLists(array $sets): array
    {
        return array_map(static fn (array $set): array => array_keys($set), $sets);
    }
}
