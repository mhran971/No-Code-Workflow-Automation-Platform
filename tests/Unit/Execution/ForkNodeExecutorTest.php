<?php

namespace Tests\Unit\Execution;

use Mockery;
use Modules\Workflows\app\Enums\ResultKind;
use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\ExecutionPlan;
use Modules\Workflows\Services\Execution\Executors\ForkNodeExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use Modules\Workflows\Services\Execution\Data\PlanEdge;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ForkNodeExecutorTest extends TestCase
{
    private ForkNodeExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->executor = new ForkNodeExecutor;
    }

    #[Test]
    public function it_fans_out_to_all_parallel_outgoing_edges(): void
    {
        $edge1 = $this->edge(target: 'branch-a');
        $edge2 = $this->edge(target: 'branch-b');

        $ctx = $this->mockContext('fork-1', [$edge1, $edge2]);
        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Proceed, $result->kind);
        $this->assertCount(2, $result->edges);
        $this->assertSame($edge1, $result->edges[0]);
        $this->assertSame($edge2, $result->edges[1]);
    }

    #[Test]
    public function it_proceeds_with_empty_output_by_default(): void
    {
        $ctx = $this->mockContext('fork-1', [$this->edge(target: 'branch-a')]);
        $result = $this->executor->execute($ctx);

        $this->assertSame([], $result->output);
    }

    #[Test]
    public function it_reports_logic_category(): void
    {
        $this->assertSame(NodeCategory::Logic, $this->executor->category());
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function edge(string $target): PlanEdge
    {
        return new PlanEdge(
            id: null,
            source: 'fork-1',
            target: $target,
            branchType: 'parallel',
            conditionExpression: null,
            isDefaultBranch: false,
            joinNodeKey: 'merge-1',
            sortOrder: 0,
        );
    }

    /** @param list<PlanEdge> $outgoing */
    private function mockContext(string $nodeKey, array $outgoing): NodeExecutionContext
    {
        $plan = Mockery::mock(ExecutionPlan::class);
        $plan->allows('outgoing')->with($nodeKey)->andReturn($outgoing);

        $ctx = Mockery::mock(NodeExecutionContext::class);
        $ctx->allows('nodeKey')->andReturn($nodeKey);
        $ctx->allows('plan')->andReturn($plan);

        return $ctx;
    }
}
