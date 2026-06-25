<?php

namespace Tests\Unit\Execution;

use Mockery;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Services\Execution\ExecutionPlan;
use Modules\Workflows\Services\Execution\Executors\MergeNodeExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use Modules\Workflows\Services\Execution\PlanEdge;
use Modules\Workflows\Services\Execution\ResultKind;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MergeNodeExecutorTest extends TestCase
{
    private MergeNodeExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->executor = new MergeNodeExecutor;
    }

    #[Test]
    public function it_proceeds_to_all_outgoing_edges(): void
    {
        $next = $this->edge('next-node');
        $ctx = $this->mockContext('merge-1', [$next]);

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Proceed, $result->kind);
        $this->assertSame([$next], $result->edges);
    }

    #[Test]
    public function it_carries_execution_output_forward(): void
    {
        $mockExecution = Mockery::mock(WorkflowNodeExecution::class);
        $mockExecution->allows('offsetExists')->andReturn(true);
        $mockExecution->allows('getAttribute')->with('output')->andReturn(['key' => 'value']);

        $plan = Mockery::mock(ExecutionPlan::class);
        $plan->allows('outgoing')->with('merge-1')->andReturn([]);

        $ctx = Mockery::mock(NodeExecutionContext::class);
        $ctx->allows('nodeKey')->andReturn('merge-1');
        $ctx->allows('plan')->andReturn($plan);
        $ctx->allows('execution')->andReturn($mockExecution);

        $result = $this->executor->execute($ctx);

        $this->assertSame(['key' => 'value'], $result->output);
    }

    #[Test]
    public function it_reports_logic_category(): void
    {
        $this->assertSame(\Modules\Workflows\Enums\NodeCategory::Logic, $this->executor->category());
    }

    #[Test]
    public function canonical_type_is_merge_and(): void
    {
        $this->assertSame('merge-and', $this->executor->type());
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function edge(string $target): PlanEdge
    {
        return new PlanEdge(
            id: null,
            source: 'merge-1',
            target: $target,
            branchType: 'default',
            conditionExpression: null,
            isDefaultBranch: true,
            joinNodeKey: null,
            sortOrder: 0,
        );
    }

    /** @param list<PlanEdge> $outgoing */
    private function mockContext(string $nodeKey, array $outgoing): NodeExecutionContext
    {
        $mockExecution = Mockery::mock(WorkflowNodeExecution::class);
        $mockExecution->allows('offsetExists')->andReturn(false);
        $mockExecution->allows('getAttribute')->with('output')->andReturn([]);

        $plan = Mockery::mock(ExecutionPlan::class);
        $plan->allows('outgoing')->with($nodeKey)->andReturn($outgoing);

        $ctx = Mockery::mock(NodeExecutionContext::class);
        $ctx->allows('nodeKey')->andReturn($nodeKey);
        $ctx->allows('plan')->andReturn($plan);
        $ctx->allows('execution')->andReturn($mockExecution);

        return $ctx;
    }
}
