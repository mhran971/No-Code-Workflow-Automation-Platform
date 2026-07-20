<?php

namespace Tests\Unit;

use Modules\Workflows\Services\Verification\ControlFlowReducer;
use Modules\Workflows\Services\Verification\ControlFlowReductionResult;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
use PHPUnit\Framework\TestCase;

/**
 * Pure (no-DB) tests for the split/merge graph-reduction algorithm.
 */
class ControlFlowReducerTest extends TestCase
{
    /**
     * @param  list<array{id:string,type:string,config?:array}>  $nodes
     * @param  list<array{source:string,target:string}>  $edges
     */
    private function reduce(array $nodes, array $edges): ControlFlowReductionResult
    {
        $definition = [
            'trigger' => ['type' => 'manual-trigger'],
            'nodes' => $nodes,
            'edges' => array_map(
                fn (array $e): array => ['source_node_key' => $e['source'], 'target_node_key' => $e['target']],
                $edges,
            ),
        ];

        return (new ControlFlowReducer(new WorkflowDefinitionGraph($definition)))->reduce();
    }

    private function merge(string $id, string $mode): array
    {
        return ['id' => $id, 'type' => 'merge', 'config' => ['mergeMode' => $mode]];
    }

    private function node(string $id, string $type): array
    {
        return ['id' => $id, 'type' => $type, 'config' => []];
    }

    public function test_balanced_fork_and_parallel_merge_reduces_cleanly(): void
    {
        $result = $this->reduce(
            [
                $this->node('t', 'manual-trigger'),
                $this->node('f', 'and-node'),
                $this->node('a', 'send-email'),
                $this->node('b', 'send-email'),
                $this->merge('m', 'parallel'),
                $this->node('e', 'send-email'),
            ],
            [
                ['source' => 't', 'target' => 'f'],
                ['source' => 'f', 'target' => 'a'],
                ['source' => 'f', 'target' => 'b'],
                ['source' => 'a', 'target' => 'm'],
                ['source' => 'b', 'target' => 'm'],
                ['source' => 'm', 'target' => 'e'],
            ],
        );

        $this->assertCount(1, $result->matched);
        $this->assertSame([], $result->mismatches);
        $this->assertSame([], $result->unmatchedSplits);
        $this->assertSame([], $result->unmatchedMerges);
    }

    public function test_conditional_split_into_parallel_merge_is_a_deadlock(): void
    {
        $result = $this->reduce(
            [
                $this->node('iff', 'if-node'),
                $this->node('a', 'send-email'),
                $this->node('b', 'send-email'),
                $this->merge('m', 'parallel'),
                $this->node('e', 'send-email'),
            ],
            [
                ['source' => 'iff', 'target' => 'a'],
                ['source' => 'iff', 'target' => 'b'],
                ['source' => 'a', 'target' => 'm'],
                ['source' => 'b', 'target' => 'm'],
                ['source' => 'm', 'target' => 'e'],
            ],
        );

        $this->assertCount(1, $result->mismatches);
        $this->assertSame('deadlock', $result->mismatches[0]['type']);
    }

    public function test_parallel_fork_into_conditional_merge_is_lack_of_synchronization(): void
    {
        $result = $this->reduce(
            [
                $this->node('f', 'and-node'),
                $this->node('a', 'send-email'),
                $this->node('b', 'send-email'),
                $this->merge('m', 'conditional'),
                $this->node('e', 'send-email'),
            ],
            [
                ['source' => 'f', 'target' => 'a'],
                ['source' => 'f', 'target' => 'b'],
                ['source' => 'a', 'target' => 'm'],
                ['source' => 'b', 'target' => 'm'],
                ['source' => 'm', 'target' => 'e'],
            ],
        );

        $this->assertCount(1, $result->mismatches);
        $this->assertSame('lack_of_synchronization', $result->mismatches[0]['type']);
    }

    public function test_fork_without_merge_is_reported_unjoined(): void
    {
        $result = $this->reduce(
            [
                $this->node('f', 'and-node'),
                $this->node('a', 'send-email'),
                $this->node('b', 'send-email'),
            ],
            [
                ['source' => 'f', 'target' => 'a'],
                ['source' => 'f', 'target' => 'b'],
            ],
        );

        $this->assertSame([['node' => 'f', 'kind' => 'p-split']], $result->unmatchedSplits);
    }

    public function test_merge_without_split_is_reported_unmatched(): void
    {
        $result = $this->reduce(
            [
                $this->node('a', 'send-email'),
                $this->node('b', 'send-email'),
                $this->merge('m', 'parallel'),
                $this->node('e', 'send-email'),
            ],
            [
                ['source' => 'a', 'target' => 'm'],
                ['source' => 'b', 'target' => 'm'],
                ['source' => 'm', 'target' => 'e'],
            ],
        );

        $this->assertSame([['node' => 'm', 'kind' => 'p-merge']], $result->unmatchedMerges);
        $this->assertSame([], $result->unmatchedSplits);
    }

    public function test_nested_balanced_blocks_reduce_fully(): void
    {
        $result = $this->reduce(
            [
                $this->node('t', 'manual-trigger'),
                $this->node('f1', 'and-node'),
                $this->node('f2', 'and-node'),
                $this->node('x', 'send-email'),
                $this->node('y', 'send-email'),
                $this->node('z', 'send-email'),
                $this->merge('m2', 'parallel'),
                $this->merge('m1', 'parallel'),
                $this->node('e', 'send-email'),
            ],
            [
                ['source' => 't', 'target' => 'f1'],
                ['source' => 'f1', 'target' => 'f2'],
                ['source' => 'f1', 'target' => 'z'],
                ['source' => 'f2', 'target' => 'x'],
                ['source' => 'f2', 'target' => 'y'],
                ['source' => 'x', 'target' => 'm2'],
                ['source' => 'y', 'target' => 'm2'],
                ['source' => 'm2', 'target' => 'm1'],
                ['source' => 'z', 'target' => 'm1'],
                ['source' => 'm1', 'target' => 'e'],
            ],
        );

        $this->assertCount(2, $result->matched);
        $this->assertSame([], $result->mismatches);
        $this->assertSame([], $result->unmatchedSplits);
        $this->assertSame([], $result->unmatchedMerges);
    }
}
