<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Workflows\Database\Seeders\NodeDefinitionSeeder;
use Modules\Workflows\Services\Verification\WorkflowVerificationService;
use Tests\TestCase;

/**
 * End-to-end verification tests for the consolidated `merge` node: config validation, structured
 * control-flow (deadlock / lack-of-synchronization / unmatched), and data-flow hazards. Runs the
 * full pipeline against the real seeded node definitions.
 */
class MergeNodeVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected WorkflowVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NodeDefinitionSeeder::class);
        $this->service = app(WorkflowVerificationService::class);
    }

    /**
     * @return list<string>
     */
    private function codes(array $definition): array
    {
        return array_column($this->service->verify($definition)->toArray()['issues'], 'code');
    }

    private function mergeNode(string $id, string $mode, int $branches = 2, array $extra = []): array
    {
        return [
            'id' => $id,
            'type' => 'merge',
            'config' => array_merge(['mergeMode' => $mode, 'branchCount' => $branches], $extra),
        ];
    }

    private function forkNode(string $id, int $branches = 2): array
    {
        $defs = [];
        for ($i = 1; $i <= $branches; $i++) {
            $defs[] = ['name' => "Branch {$i}", 'key' => "branch_{$i}"];
        }

        return ['id' => $id, 'type' => 'and-node', 'config' => ['branches' => $defs]];
    }

    private function send(string $id, array $config = []): array
    {
        return ['id' => $id, 'type' => 'send-email', 'config' => array_merge(['to' => 'x@example.test', 'subject' => 'Hi', 'body' => 'Hello'], $config)];
    }

    private function edge(string $source, string $target): array
    {
        return ['source_node_key' => $source, 'target_node_key' => $target];
    }

    /** A fork whose two branches converge on a parallel merge — structurally sound. */
    private function balancedParallelDefinition(array $aConfig = [], array $bConfig = []): array
    {
        return [
            'trigger' => ['type' => 'manual-trigger', 'config' => []],
            'nodes' => [
                ['id' => 't', 'type' => 'manual-trigger', 'config' => []],
                $this->forkNode('f'),
                $this->send('a', $aConfig),
                $this->send('b', $bConfig),
                $this->mergeNode('m', 'parallel'),
                $this->send('e'),
            ],
            'edges' => [
                $this->edge('t', 'f'), $this->edge('f', 'a'), $this->edge('f', 'b'),
                $this->edge('a', 'm'), $this->edge('b', 'm'), $this->edge('m', 'e'),
            ],
        ];
    }

    public function test_merge_without_mode_reports_mode_missing(): void
    {
        $definition = $this->balancedParallelDefinition();
        unset($definition['nodes'][4]['config']['mergeMode']);

        $this->assertContains('merge.mode_missing', $this->codes($definition));
    }

    public function test_merge_with_invalid_mode_reports_mode_invalid(): void
    {
        $definition = $this->balancedParallelDefinition();
        $definition['nodes'][4]['config']['mergeMode'] = 'sometimes';

        $this->assertContains('merge.mode_invalid', $this->codes($definition));
    }

    public function test_merge_without_branch_count_reports_count_missing(): void
    {
        $definition = $this->balancedParallelDefinition();
        unset($definition['nodes'][4]['config']['branchCount']);

        $this->assertContains('merge.branch_count_missing', $this->codes($definition));
    }

    public function test_merge_with_fewer_than_two_branches_reports_count_invalid(): void
    {
        $definition = $this->balancedParallelDefinition();
        $definition['nodes'][4]['config']['branchCount'] = 1;

        $this->assertContains('merge.branch_count_invalid', $this->codes($definition));
    }

    public function test_merge_branch_count_not_matching_incoming_edges_reports_mismatch(): void
    {
        $definition = $this->balancedParallelDefinition();
        // Declare 3 branches but only 2 edges arrive.
        $definition['nodes'][4]['config']['branchCount'] = 3;

        $this->assertContains('merge.branches_mismatch', $this->codes($definition));
    }

    public function test_balanced_fork_and_parallel_merge_has_no_control_flow_errors(): void
    {
        $codes = $this->codes($this->balancedParallelDefinition());

        $this->assertNotContains('merge.deadlock', $codes);
        $this->assertNotContains('merge.lack_of_synchronization', $codes);
        $this->assertNotContains('merge.fork_unjoined', $codes);
        $this->assertNotContains('merge.unmatched', $codes);
    }

    public function test_conditional_split_into_parallel_merge_reports_deadlock(): void
    {
        $definition = $this->balancedParallelDefinition();
        $definition['nodes'][1] = ['id' => 'f', 'type' => 'if-node', 'config' => ['conditionExpression' => 'context.age > 18']];

        $this->assertContains('merge.deadlock', $this->codes($definition));
    }

    public function test_parallel_fork_into_conditional_merge_reports_lack_of_synchronization(): void
    {
        $definition = $this->balancedParallelDefinition();
        $definition['nodes'][4]['config']['mergeMode'] = 'conditional';

        $this->assertContains('merge.lack_of_synchronization', $this->codes($definition));
    }

    public function test_fork_without_merge_reports_fork_unjoined(): void
    {
        $definition = [
            'trigger' => ['type' => 'manual-trigger', 'config' => []],
            'nodes' => [
                ['id' => 't', 'type' => 'manual-trigger', 'config' => []],
                $this->forkNode('f'),
                $this->send('a'),
                $this->send('b'),
            ],
            'edges' => [
                $this->edge('t', 'f'), $this->edge('f', 'a'), $this->edge('f', 'b'),
            ],
        ];

        $this->assertContains('merge.fork_unjoined', $this->codes($definition));
    }

    public function test_parallel_branches_writing_same_variable_report_data_conflict(): void
    {
        $definition = $this->balancedParallelDefinition(
            ['outputVariables' => ['status']],
            ['outputVariables' => ['status']],
        );

        $this->assertContains('dataflow.parallel_conflict', $this->codes($definition));
    }

    public function test_variable_only_on_one_conditional_branch_reports_missing_after_merge(): void
    {
        $definition = [
            'trigger' => ['type' => 'manual-trigger', 'config' => []],
            'nodes' => [
                ['id' => 't', 'type' => 'manual-trigger', 'config' => []],
                ['id' => 'iff', 'type' => 'if-node', 'config' => ['conditionExpression' => 'context.age > 18']],
                $this->send('a', ['outputVariables' => ['score']]),
                $this->send('b'),
                $this->mergeNode('m', 'conditional'),
                // consumer reads context.score after the conditional merge
                $this->send('c', ['subject' => 'Score is {{context.score}}']),
            ],
            'edges' => [
                $this->edge('t', 'iff'), $this->edge('iff', 'a'), $this->edge('iff', 'b'),
                $this->edge('a', 'm'), $this->edge('b', 'm'), $this->edge('m', 'c'),
            ],
        ];

        $this->assertContains('dataflow.variable_missing', $this->codes($definition));
    }

    /** A conditional merge output must be produced on every incoming branch. */
    public function test_conditional_merge_output_not_on_all_branches_is_rejected(): void
    {
        // 'score' produced only on branch a; merge declares it as an output.
        $definition = [
            'trigger' => ['type' => 'manual-trigger', 'config' => []],
            'nodes' => [
                ['id' => 't', 'type' => 'manual-trigger', 'config' => []],
                ['id' => 'iff', 'type' => 'if-node', 'config' => ['conditionExpression' => 'context.age > 18']],
                $this->send('a', ['outputVariables' => ['score']]),
                $this->send('b'),
                $this->mergeNode('m', 'conditional', 2, ['outputVariables' => ['score']]),
                $this->send('e'),
            ],
            'edges' => [
                $this->edge('t', 'iff'), $this->edge('iff', 'a'), $this->edge('iff', 'b'),
                $this->edge('a', 'm'), $this->edge('b', 'm'), $this->edge('m', 'e'),
            ],
        ];

        $this->assertContains('merge.output_not_on_all_branches', $this->codes($definition));
    }

    /** A conditional merge output produced on every branch is accepted. */
    public function test_conditional_merge_output_on_all_branches_passes(): void
    {
        $definition = [
            'trigger' => ['type' => 'manual-trigger', 'config' => []],
            'nodes' => [
                ['id' => 't', 'type' => 'manual-trigger', 'config' => []],
                ['id' => 'iff', 'type' => 'if-node', 'config' => ['conditionExpression' => 'context.age > 18']],
                $this->send('a', ['outputVariables' => ['score']]),
                $this->send('b', ['outputVariables' => ['score']]),
                $this->mergeNode('m', 'conditional', 2, ['outputVariables' => ['score']]),
                $this->send('e'),
            ],
            'edges' => [
                $this->edge('t', 'iff'), $this->edge('iff', 'a'), $this->edge('iff', 'b'),
                $this->edge('a', 'm'), $this->edge('b', 'm'), $this->edge('m', 'e'),
            ],
        ];

        $codes = $this->codes($definition);

        $this->assertNotContains('merge.output_not_on_all_branches', $codes);
        $this->assertNotContains('merge.output_not_on_any_branch', $codes);
    }

    /** A parallel merge output need only be produced on one branch. */
    public function test_parallel_merge_output_on_one_branch_passes(): void
    {
        $definition = $this->balancedParallelDefinition(['outputVariables' => ['score']]);
        $definition['nodes'][4]['config']['outputVariables'] = ['score'];

        $codes = $this->codes($definition);

        $this->assertNotContains('merge.output_not_on_any_branch', $codes);
        $this->assertNotContains('merge.output_not_on_all_branches', $codes);
    }

    /** A parallel merge output produced on no branch is rejected. */
    public function test_parallel_merge_output_on_no_branch_is_rejected(): void
    {
        $definition = $this->balancedParallelDefinition();
        $definition['nodes'][4]['config']['outputVariables'] = ['nowhere'];

        $this->assertContains('merge.output_not_on_any_branch', $this->codes($definition));
    }
}
