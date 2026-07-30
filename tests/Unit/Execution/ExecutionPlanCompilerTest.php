<?php

namespace Tests\Unit\Execution;

use Modules\Workflows\Services\Execution\Data\JoinSpec;
use Modules\Workflows\Services\Execution\ExecutionPlanCompiler;
use Modules\Workflows\Services\Verification\WorkflowDefinitionNormalizer;
use Tests\TestCase;

class ExecutionPlanCompilerTest extends TestCase
{
    protected ExecutionPlanCompiler $compiler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->compiler = new ExecutionPlanCompiler(new WorkflowDefinitionNormalizer);
    }

    public function test_compiles_fork_join_graph(): void
    {
        $plan = $this->compiler->compile($this->forkJoinDefinition());

        // Trigger node is on the canvas → it is the single entry point.
        $this->assertSame('start', $plan->triggerNodeKey());
        $this->assertSame(['start'], $plan->entryNodeKeys());

        // Fork has two outgoing branches.
        $this->assertCount(2, $plan->forkBranches('fork'));
        $targets = array_map(fn ($e) => $e->target, $plan->outgoing('fork'));
        $this->assertEqualsCanonicalizing(['a', 'b'], $targets);

        // Join is computed from incoming edges.
        $join = $plan->joinFor('m');
        $this->assertNotNull($join);
        $this->assertSame(JoinSpec::MODE_PARALLEL, $join->mode);
        $this->assertSame(2, $join->expectedCount);

        // Non-merge nodes have no join spec.
        $this->assertNull($plan->joinFor('a'));

        // Terminals.
        $this->assertTrue($plan->isTerminal('t'));
        $this->assertFalse($plan->isTerminal('m'));
        $this->assertSame('send-email', $plan->nodeType('a'));
    }

    public function test_edges_are_sorted_by_sort_order(): void
    {
        $plan = $this->compiler->compile([
            'trigger' => ['type' => 'manual-trigger'],
            'nodes' => [
                ['id' => 'start', 'type' => 'manual-trigger', 'config' => []],
                ['id' => 'x', 'type' => 'send-email', 'config' => []],
                ['id' => 'y', 'type' => 'send-email', 'config' => []],
            ],
            'edges' => [
                ['id' => 'e2', 'source' => 'start', 'target' => 'y', 'sort_order' => 2],
                ['id' => 'e1', 'source' => 'start', 'target' => 'x', 'sort_order' => 1],
            ],
        ]);

        $targets = array_map(fn ($e) => $e->target, $plan->outgoing('start'));
        $this->assertSame(['x', 'y'], $targets);
    }

    /**
     * @return array<string, mixed>
     */
    protected function forkJoinDefinition(): array
    {
        return [
            'trigger' => ['type' => 'manual-trigger', 'config' => []],
            'nodes' => [
                ['id' => 'start', 'type' => 'manual-trigger', 'config' => []],
                ['id' => 'fork', 'type' => 'and-node', 'config' => []],
                ['id' => 'a', 'type' => 'send-email', 'config' => ['to' => 'a@x.test']],
                ['id' => 'b', 'type' => 'send-email', 'config' => ['to' => 'b@x.test']],
                ['id' => 'm', 'type' => 'merge', 'config' => ['mergeMode' => 'parallel']],
                ['id' => 't', 'type' => 'termination-node', 'config' => []],
            ],
            'edges' => [
                ['id' => 'e0', 'source' => 'start', 'target' => 'fork'],
                ['id' => 'e1', 'source' => 'fork', 'target' => 'a', 'branch_type' => 'parallel'],
                ['id' => 'e2', 'source' => 'fork', 'target' => 'b', 'branch_type' => 'parallel'],
                ['id' => 'e3', 'source' => 'a', 'target' => 'm'],
                ['id' => 'e4', 'source' => 'b', 'target' => 'm'],
                ['id' => 'e5', 'source' => 'm', 'target' => 't'],
            ],
        ];
    }
}
