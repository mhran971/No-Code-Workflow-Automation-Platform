<?php

namespace Tests\Unit;

use Modules\Workflows\Services\Verification\DataFlowAnalyzer;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
use PHPUnit\Framework\TestCase;

/**
 * Pure (no-DB) tests for the forward data-flow analysis.
 */
class DataFlowAnalyzerTest extends TestCase
{
    /**
     * @param  list<array{id:string,type:string,config?:array}>  $nodes
     * @param  list<array{source:string,target:string}>  $edges
     */
    private function analyze(array $nodes, array $edges): \Modules\Workflows\Services\Verification\DataFlowResult
    {
        $definition = [
            'trigger' => ['type' => 'manual-trigger', 'config' => []],
            'nodes' => $nodes,
            'edges' => array_map(
                fn (array $e): array => ['source_node_key' => $e['source'], 'target_node_key' => $e['target']],
                $edges,
            ),
        ];

        return (new DataFlowAnalyzer(new WorkflowDefinitionGraph($definition)))->analyze();
    }

    public function test_concurrent_writes_to_same_variable_on_parallel_branches_conflict(): void
    {
        $result = $this->analyze(
            [
                ['id' => 't', 'type' => 'manual-trigger', 'config' => []],
                ['id' => 'f', 'type' => 'and-node', 'config' => []],
                ['id' => 'a', 'type' => 'send-email', 'config' => ['outputVariables' => ['status']]],
                ['id' => 'b', 'type' => 'send-email', 'config' => ['outputVariables' => ['status']]],
                ['id' => 'm', 'type' => 'merge', 'config' => ['mergeMode' => 'parallel']],
                ['id' => 'e', 'type' => 'send-email', 'config' => []],
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

        $this->assertContains(['node' => 'm', 'variable' => 'context.status'], $result->conflicts);
        // A parallel merge combines all branch outputs, so the variable is available downstream.
        $this->assertContains('context.status', $result->guaranteedAt('e'));
    }

    public function test_variable_produced_on_one_conditional_branch_is_not_guaranteed_after_merge(): void
    {
        $result = $this->analyze(
            [
                ['id' => 't', 'type' => 'manual-trigger', 'config' => []],
                ['id' => 'iff', 'type' => 'if-node', 'config' => []],
                ['id' => 'a', 'type' => 'send-email', 'config' => ['outputVariables' => ['score']]],
                ['id' => 'b', 'type' => 'send-email', 'config' => []],
                ['id' => 'm', 'type' => 'merge', 'config' => ['mergeMode' => 'conditional']],
                ['id' => 'c', 'type' => 'send-email', 'config' => []],
            ],
            [
                ['source' => 't', 'target' => 'iff'],
                ['source' => 'iff', 'target' => 'a'],
                ['source' => 'iff', 'target' => 'b'],
                ['source' => 'a', 'target' => 'm'],
                ['source' => 'b', 'target' => 'm'],
                ['source' => 'm', 'target' => 'c'],
            ],
        );

        $this->assertTrue($result->isProduced('context.score'));
        $this->assertNotContains('context.score', $result->guaranteedAt('c'));
        $this->assertContains('context.score', $result->possible['c']);
        $this->assertSame([], $result->conflicts);
    }

    public function test_trigger_variables_are_guaranteed_downstream(): void
    {
        $definition = [
            'trigger' => ['type' => 'manual-trigger', 'config' => ['variables' => [['key' => 'age']]]],
            'nodes' => [
                ['id' => 't', 'type' => 'manual-trigger', 'config' => []],
                ['id' => 'a', 'type' => 'send-email', 'config' => []],
            ],
            'edges' => [
                ['source_node_key' => 't', 'target_node_key' => 'a'],
            ],
        ];

        $result = (new DataFlowAnalyzer(new WorkflowDefinitionGraph($definition)))->analyze();

        $this->assertContains('context.age', $result->guaranteedAt('a'));
    }
}
