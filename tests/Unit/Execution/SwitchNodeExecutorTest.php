<?php

namespace Tests\Unit\Execution;

use Mockery;
use Modules\Workflows\app\Enums\ResultKind;
use Modules\Workflows\Services\Execution\Data\PlanEdge;
use Modules\Workflows\Services\Execution\ExecutionPlan;
use Modules\Workflows\Services\Execution\Executors\SwitchNodeExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SwitchNodeExecutorTest extends TestCase
{
    private SwitchNodeExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->executor = new SwitchNodeExecutor;
    }

    #[Test]
    public function it_routes_to_the_single_matching_branch(): void
    {
        $trueBranch = $this->edge(branchType: 'true');
        $falseBranch = $this->edge(branchType: 'false');
        $defaultBranch = $this->edge(branchType: 'default', isDefaultBranch: true);

        $ctx = $this->mockContext(
            'switch-1',
            [$trueBranch, $falseBranch, $defaultBranch],
            evaluated: true,
            nodeConfig: ['variable' => 'context.approved'],
        );

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Branch, $result->kind);
        $this->assertSame([$trueBranch], $result->edges);
    }

    #[Test]
    public function it_never_returns_more_than_one_edge_even_with_duplicate_branch_types(): void
    {
        // Two edges accidentally share the same branch_type (bad authoring) — only the
        // first match in outgoing order should ever be taken, never both.
        $first = $this->edge(branchType: 'true');
        $duplicate = $this->edge(branchType: 'true');

        $ctx = $this->mockContext(
            'switch-1',
            [$first, $duplicate],
            evaluated: true,
            nodeConfig: ['variable' => 'context.approved'],
        );

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Branch, $result->kind);
        $this->assertCount(1, $result->edges);
        $this->assertSame([$first], $result->edges);
    }

    #[Test]
    public function it_falls_back_to_default_branch_when_nothing_matches(): void
    {
        $trueBranch = $this->edge(branchType: 'true');
        $defaultBranch = $this->edge(branchType: 'default', isDefaultBranch: true);

        $ctx = $this->mockContext(
            'switch-1',
            [$trueBranch, $defaultBranch],
            evaluated: 'unmatched-value',
            nodeConfig: ['variable' => 'context.status'],
        );

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Branch, $result->kind);
        $this->assertSame([$defaultBranch], $result->edges);
    }

    #[Test]
    public function it_prioritizes_a_per_edge_condition_expression_over_canonical_branch_type(): void
    {
        $conditional = $this->edge(branchType: 'other', conditionExpression: 'context.vip === true');
        $canonical = $this->edge(branchType: 'gold');

        $ctx = $this->mockContext(
            'switch-1',
            [$conditional, $canonical],
            evaluated: 'gold',
            nodeConfig: ['variable' => 'context.tier'],
            evaluateBoolean: true,
        );

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Branch, $result->kind);
        $this->assertSame([$conditional], $result->edges);
    }

    #[Test]
    public function it_fails_when_no_branch_matches_and_no_default_is_configured(): void
    {
        $trueBranch = $this->edge(branchType: 'true');
        $falseBranch = $this->edge(branchType: 'false');

        $ctx = $this->mockContext(
            'switch-1',
            [$trueBranch, $falseBranch],
            evaluated: 'unmatched-value',
            nodeConfig: ['variable' => 'context.status'],
        );

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Fail, $result->kind);
        $this->assertFalse($result->retryable);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function edge(
        string $branchType,
        bool $isDefaultBranch = false,
        ?string $conditionExpression = null,
    ): PlanEdge {
        return new PlanEdge(
            id: null,
            source: 'switch-1',
            target: $branchType.'-target',
            branchType: $branchType,
            conditionExpression: $conditionExpression,
            isDefaultBranch: $isDefaultBranch,
            joinNodeKey: null,
            sortOrder: 0,
        );
    }

    /** @param list<PlanEdge> $outgoing */
    private function mockContext(
        string $nodeKey,
        array $outgoing,
        mixed $evaluated,
        array $nodeConfig = [],
        bool $evaluateBoolean = false,
    ): NodeExecutionContext {
        $plan = Mockery::mock(ExecutionPlan::class);
        $plan->allows('outgoing')->with($nodeKey)->andReturn($outgoing);

        $ctx = Mockery::mock(NodeExecutionContext::class);
        $ctx->allows('nodeKey')->andReturn($nodeKey);
        $ctx->allows('plan')->andReturn($plan);
        $ctx->allows('config')->andReturn($nodeConfig);
        $ctx->allows('evaluate')->andReturn($evaluated);
        $ctx->allows('evaluateBoolean')->andReturn($evaluateBoolean);

        return $ctx;
    }
}
