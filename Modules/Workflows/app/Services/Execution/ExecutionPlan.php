<?php

namespace Modules\Workflows\Services\Execution;

use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;

/**
 * Immutable, execution-shaped view of a (normalized) workflow definition: indexed nodes, ordered adjacency,
 * entry points, and precomputed join specs. Built once per immutable workflow version.
 *
 * Reuses {@see WorkflowDefinitionGraph} for traversal so authoring and execution share one graph model.
 */
class ExecutionPlan
{
    protected WorkflowDefinitionGraph $graph;

    /** @var array<string, array<string, mixed>> */
    protected array $nodesByKey = [];

    /** @var array<string, list<PlanEdge>> */
    protected array $outgoing = [];

    /** @var array<string, list<PlanEdge>> */
    protected array $incoming = [];

    /**
     * @param  array<string, mixed>  $normalizedDefinition  output of WorkflowDefinitionNormalizer::normalize()
     */
    public function __construct(protected array $normalizedDefinition)
    {
        $this->graph = new WorkflowDefinitionGraph($normalizedDefinition);

        foreach ($normalizedDefinition['nodes'] ?? [] as $node) {
            $id = $node['id'] ?? null;

            if (is_string($id) && $id !== '') {
                $this->nodesByKey[$id] = $node;
                $this->outgoing[$id] = [];
                $this->incoming[$id] = [];
            }
        }

        foreach ($normalizedDefinition['edges'] ?? [] as $edge) {
            $planEdge = PlanEdge::fromNormalized($edge);

            if (isset($this->nodesByKey[$planEdge->source])) {
                $this->outgoing[$planEdge->source][] = $planEdge;
            }

            if (isset($this->nodesByKey[$planEdge->target])) {
                $this->incoming[$planEdge->target][] = $planEdge;
            }
        }

        foreach ($this->outgoing as &$edges) {
            usort($edges, fn (PlanEdge $a, PlanEdge $b): int => $a->sortOrder <=> $b->sortOrder);
        }
        unset($edges);
    }

    public function triggerNodeKey(): ?string
    {
        return $this->graph->triggerNodeId();
    }

    /**
     * The node key(s) where execution begins: the canvas trigger node if present, otherwise the structural
     * entry nodes (no incoming edges).
     *
     * @return list<string>
     */
    public function entryNodeKeys(): array
    {
        $trigger = $this->triggerNodeKey();

        if ($trigger !== null) {
            return [$trigger];
        }

        return $this->graph->entryNodeIds();
    }

    /**
     * @return list<string>
     */
    public function nodeKeys(): array
    {
        return array_keys($this->nodesByKey);
    }

    public function hasNode(string $key): bool
    {
        return isset($this->nodesByKey[$key]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function node(string $key): ?array
    {
        return $this->nodesByKey[$key] ?? null;
    }

    public function nodeType(string $key): ?string
    {
        return $this->nodesByKey[$key]['type'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function config(string $key): array
    {
        $config = $this->nodesByKey[$key]['config'] ?? [];

        return is_array($config) ? $config : [];
    }

    /**
     * @return list<PlanEdge>
     */
    public function outgoing(string $key): array
    {
        return $this->outgoing[$key] ?? [];
    }

    /**
     * @return list<PlanEdge>
     */
    public function incoming(string $key): array
    {
        return $this->incoming[$key] ?? [];
    }

    public function isTerminal(string $key): bool
    {
        return $this->nodeType($key) === 'termination-node' || $this->outgoing($key) === [];
    }

    /**
     * Branches a `and-node` (fork) splits into.
     *
     * @return list<PlanEdge>
     */
    public function forkBranches(string $key): array
    {
        return $this->outgoing($key);
    }

    /**
     * True for any merge node type (merge-or, merge-and, or legacy 'merge').
     */
    public function isMergeNode(string $key): bool
    {
        return in_array($this->nodeType($key), ['merge', 'merge-or', 'merge-and'], true);
    }

    /**
     * The synchronization spec for a merge node, or null for any other node type.
     *
     * - `merge-and` → wait-all (parallel); `merge-or` → first-arrival (conditional)
     * - `branchCount` config overrides the incoming-edge count when the plan graph is partial.
     * - `timeoutMinutes` config expresses an optional upper bound after which the merge proceeds
     *   regardless of arrived count (enforced by ScanWorkflowTimersCommand).
     */
    public function joinFor(string $key): ?JoinSpec
    {
        if (! $this->isMergeNode($key)) {
            return null;
        }

        $type = $this->nodeType($key);
        $config = $this->config($key);

        // merge-and = wait-all; merge-or = first-arrival; legacy 'merge' reads config.
        $isParallel = $type === 'merge-and'
            || ($type === 'merge' && ($config['mergeMode'] ?? JoinSpec::MODE_PARALLEL) === JoinSpec::MODE_PARALLEL);

        $mode = $isParallel ? JoinSpec::MODE_PARALLEL : JoinSpec::MODE_CONDITIONAL;

        $expected = count($this->incoming($key));
        if ($expected === 0 && isset($config['branchCount'])) {
            $expected = (int) $config['branchCount'];
        }

        $timeoutSeconds = isset($config['timeoutMinutes'])
            ? (int) $config['timeoutMinutes'] * 60
            : null;

        return new JoinSpec($mode, max($expected, 1), $timeoutSeconds);
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return $this->normalizedDefinition;
    }
}
