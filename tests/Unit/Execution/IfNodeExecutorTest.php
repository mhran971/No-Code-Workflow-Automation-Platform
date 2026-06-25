<?php

namespace Tests\Unit\Execution;

use Mockery;
use Modules\Workflows\Services\Execution\Executors\IfNodeExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use Modules\Workflows\Services\Execution\PlanEdge;
use Modules\Workflows\Services\Execution\ResultKind;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IfNodeExecutorTest extends TestCase
{
    private IfNodeExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->executor = new IfNodeExecutor;
    }

    #[Test]
    public function it_takes_first_true_conditional_edge(): void
    {
        $matchedEdge = $this->edge(conditionExpression: 'context.age > 18');
        $ctx = $this->mockContext('if-1', [$matchedEdge], evaluateBoolean: true);

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Branch, $result->kind);
        $this->assertSame([$matchedEdge], $result->edges);
    }

    #[Test]
    public function it_falls_through_to_default_when_no_condition_matches(): void
    {
        $conditional = $this->edge(conditionExpression: 'context.vip');
        $default = $this->edge(isDefaultBranch: true);
        $ctx = $this->mockContext('if-1', [$conditional, $default], evaluateBoolean: false);

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Branch, $result->kind);
        $this->assertSame([$default], $result->edges);
    }

    #[Test]
    public function it_fails_when_no_match_and_no_default(): void
    {
        $conditional = $this->edge(conditionExpression: 'context.vip');
        $ctx = $this->mockContext('if-1', [$conditional], evaluateBoolean: false);

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Fail, $result->kind);
        $this->assertFalse($result->retryable);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function edge(
        string $conditionExpression = '',
        bool $isDefaultBranch = false,
    ): PlanEdge {
        return new PlanEdge(
            id: null,
            source: 'if-1',
            target: 'next',
            branchType: $isDefaultBranch ? 'default' : 'conditional',
            conditionExpression: $conditionExpression !== '' ? $conditionExpression : null,
            isDefaultBranch: $isDefaultBranch,
            joinNodeKey: null,
            sortOrder: 0,
        );
    }

    /** @param list<PlanEdge> $outgoing */
    private function mockContext(string $nodeKey, array $outgoing, bool $evaluateBoolean): NodeExecutionContext
    {
        $plan = Mockery::mock(\Modules\Workflows\Services\Execution\ExecutionPlan::class);
        $plan->allows('outgoing')->with($nodeKey)->andReturn($outgoing);

        $ctx = Mockery::mock(NodeExecutionContext::class);
        $ctx->allows('nodeKey')->andReturn($nodeKey);
        $ctx->allows('plan')->andReturn($plan);
        $ctx->allows('evaluateBoolean')->andReturn($evaluateBoolean);

        return $ctx;
    }
}
