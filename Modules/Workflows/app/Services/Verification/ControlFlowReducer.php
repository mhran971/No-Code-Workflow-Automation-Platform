<?php

namespace Modules\Workflows\Services\Verification;

use Modules\Workflows\Services\Verification\Data\ControlFlowReductionResult;

/**
 * Verifies that a workflow's splits and merges form a well-structured (sound) graph by
 * applying classic workflow-graph **reduction rules** to a fixpoint:
 *
 *   1. Sequence rule — a plain node with one predecessor and one successor is collapsed away.
 *   2. Block rule (SESE) — a split whose branches all converge on a single merge, where that
 *      merge's only predecessors are those branches, is collapsed into one plain node.
 *
 * What cannot be reduced reveals an unsound structure: a split with no matching merge, a merge
 * with no originating split, or a split paired with a merge of the wrong mode — which is either a
 * deadlock (conditional split → parallel/AND merge) or a lack of synchronization (parallel split →
 * conditional/XOR merge).
 *
 * Node kinds:
 *   p-split  parallel split   (and-node)
 *   c-split  conditional split (if-node, switch)
 *   p-merge  parallel merge    (merge node, mergeMode=parallel)
 *   c-merge  conditional merge (merge node, mergeMode=conditional)
 *   plain    everything else
 */
class ControlFlowReducer
{
    private const SPLITS = ['p-split', 'c-split'];

    private const MERGES = ['p-merge', 'c-merge'];

    /** @var array<string, string> nodeId => kind */
    private array $kind = [];

    /** @var array<string, list<string>> nodeId => successor node ids (multiset) */
    private array $succ = [];

    /** @var array<string, list<string>> nodeId => predecessor node ids (multiset) */
    private array $pred = [];

    /** @var array<string, bool> */
    private array $alive = [];

    public function __construct(WorkflowDefinitionGraph $graph)
    {
        foreach ($graph->nodeIds() as $nodeId) {
            $this->kind[$nodeId] = $this->classify($graph, $nodeId);
            $this->succ[$nodeId] = [];
            $this->pred[$nodeId] = [];
            $this->alive[$nodeId] = true;
        }

        foreach ($graph->edges() as $edge) {
            $source = $edge['source_node_key'] ?? null;
            $target = $edge['target_node_key'] ?? null;

            if (is_string($source) && is_string($target)
                && isset($this->alive[$source]) && isset($this->alive[$target])) {
                $this->succ[$source][] = $target;
                $this->pred[$target][] = $source;
            }
        }
    }

    public function reduce(): ControlFlowReductionResult
    {
        $matched = [];
        $mismatches = [];

        while ($this->reduceOnce($matched, $mismatches)) {
            // keep reducing until no rule applies
        }

        $unmatchedSplits = [];
        $unmatchedMerges = [];

        foreach ($this->alive as $nodeId => $isAlive) {
            if (! $isAlive) {
                continue;
            }

            $kind = $this->kind[$nodeId];

            if (in_array($kind, self::SPLITS, true) && count($this->succ[$nodeId]) >= 2) {
                $unmatchedSplits[] = ['node' => $nodeId, 'kind' => $kind];
            } elseif (in_array($kind, self::MERGES, true) && count($this->pred[$nodeId]) >= 2) {
                $unmatchedMerges[] = ['node' => $nodeId, 'kind' => $kind];
            }
        }

        return new ControlFlowReductionResult($matched, $mismatches, $unmatchedSplits, $unmatchedMerges);
    }

    private function classify(WorkflowDefinitionGraph $graph, string $nodeId): string
    {
        $type = $graph->nodeType($nodeId);

        if ($type === 'and-node') {
            return 'p-split';
        }

        if ($type === 'if-node' || $type === 'switch') {
            return 'c-split';
        }

        if ($type === 'merge-and') {
            return 'p-merge';
        }

        if ($type === 'merge-or') {
            return 'c-merge';
        }

        if ($type === 'merge') {
            $node = $graph->node($nodeId) ?? [];
            $mode = is_array($node['config'] ?? null) ? trim((string) ($node['config']['mergeMode'] ?? '')) : '';

            return match ($mode) {
                'parallel' => 'p-merge',
                'conditional' => 'c-merge',
                default => 'plain', // invalid mode is reported by MergeNodeTypeRule; don't double-flag
            };
        }

        return 'plain';
    }

    /**
     * @param  list<array{split:string,merge:string,splitKind:string,mergeKind:string}>  $matched
     * @param  list<array{split:string,merge:string,type:string}>  $mismatches
     */
    private function reduceOnce(array &$matched, array &$mismatches): bool
    {
        return $this->applySequenceRule() || $this->applyBlockRule($matched, $mismatches);
    }

    private function applySequenceRule(): bool
    {
        foreach ($this->alive as $nodeId => $isAlive) {
            if (! $isAlive || $this->kind[$nodeId] !== 'plain') {
                continue;
            }

            if (count($this->pred[$nodeId]) !== 1 || count($this->succ[$nodeId]) !== 1) {
                continue;
            }

            $u = $this->pred[$nodeId][0];
            $w = $this->succ[$nodeId][0];

            // Skip self-loops or a collapse that would create one — those belong to explicit loops.
            if ($u === $nodeId || $w === $nodeId || $u === $w) {
                continue;
            }

            $this->removeEdge($u, $nodeId);
            $this->removeEdge($nodeId, $w);
            $this->addEdge($u, $w);
            $this->kill($nodeId);

            return true;
        }

        return false;
    }

    /**
     * @param  list<array{split:string,merge:string,splitKind:string,mergeKind:string}>  $matched
     * @param  list<array{split:string,merge:string,type:string}>  $mismatches
     */
    private function applyBlockRule(array &$matched, array &$mismatches): bool
    {
        foreach ($this->alive as $splitId => $isAlive) {
            if (! $isAlive || ! in_array($this->kind[$splitId], self::SPLITS, true)) {
                continue;
            }

            $successors = $this->succ[$splitId];
            if (count($successors) < 2) {
                continue;
            }

            $mergeId = $successors[0];

            // All branches must converge on the same single merge node...
            if (array_unique($successors) !== [$mergeId] || ! in_array($this->kind[$mergeId], self::MERGES, true)) {
                continue;
            }

            // ...and that merge's predecessors must be exactly (and only) this split.
            if (array_unique($this->pred[$mergeId]) !== [$splitId]
                || count($this->pred[$mergeId]) !== count($successors)) {
                continue;
            }

            $this->recordPairing($splitId, $mergeId, $matched, $mismatches);
            $this->collapseBlock($splitId, $mergeId);

            return true;
        }

        return false;
    }

    /**
     * @param  list<array{split:string,merge:string,splitKind:string,mergeKind:string}>  $matched
     * @param  list<array{split:string,merge:string,type:string}>  $mismatches
     */
    private function recordPairing(string $splitId, string $mergeId, array &$matched, array &$mismatches): void
    {
        $splitKind = $this->kind[$splitId];
        $mergeKind = $this->kind[$mergeId];

        $compatible = ($splitKind === 'p-split' && $mergeKind === 'p-merge')
            || ($splitKind === 'c-split' && $mergeKind === 'c-merge');

        if ($compatible) {
            $matched[] = ['split' => $splitId, 'merge' => $mergeId, 'splitKind' => $splitKind, 'mergeKind' => $mergeKind];

            return;
        }

        $mismatches[] = [
            'split' => $splitId,
            'merge' => $mergeId,
            // conditional split joined by a parallel/AND merge → the join waits forever.
            'type' => $splitKind === 'c-split' ? 'deadlock' : 'lack_of_synchronization',
        ];
    }

    /**
     * Collapse a matched split/merge block: the split absorbs the merge's outgoing edges and
     * becomes a plain node; the merge is removed.
     */
    private function collapseBlock(string $splitId, string $mergeId): void
    {
        // Detach all split→merge edges.
        $this->succ[$splitId] = [];
        $this->pred[$mergeId] = [];

        // The split takes over the merge's successors.
        foreach ($this->succ[$mergeId] as $w) {
            $this->succ[$splitId][] = $w;
            $this->replacePredecessor($w, $mergeId, $splitId);
        }

        $this->kind[$splitId] = 'plain';
        $this->kill($mergeId);
    }

    private function addEdge(string $from, string $to): void
    {
        $this->succ[$from][] = $to;
        $this->pred[$to][] = $from;
    }

    private function removeEdge(string $from, string $to): void
    {
        $this->succ[$from] = $this->removeOnce($this->succ[$from], $to);
        $this->pred[$to] = $this->removeOnce($this->pred[$to], $from);
    }

    private function replacePredecessor(string $node, string $oldPred, string $newPred): void
    {
        $index = array_search($oldPred, $this->pred[$node], true);

        if ($index !== false) {
            $this->pred[$node][$index] = $newPred;
        }
    }

    /**
     * @param  list<string>  $list
     * @return list<string>
     */
    private function removeOnce(array $list, string $value): array
    {
        $index = array_search($value, $list, true);

        if ($index !== false) {
            array_splice($list, $index, 1);
        }

        return $list;
    }

    private function kill(string $nodeId): void
    {
        $this->alive[$nodeId] = false;
        $this->succ[$nodeId] = [];
        $this->pred[$nodeId] = [];
    }
}
