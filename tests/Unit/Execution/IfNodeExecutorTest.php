<?php

namespace Tests\Unit\Execution;

use Mockery;
use Modules\Workflows\app\Enums\ResultKind;
use Modules\Workflows\Services\Execution\Executors\IfNodeExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use Modules\Workflows\Services\Execution\PlanEdge;
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
    public function it_routes_to_yes_branch_when_condition_is_true(): void
    {
        $yesBranch = $this->edge(branchType: 'true');
        $noBranch  = $this->edge(branchType: 'else');
        $ctx = $this->mockContext(
            'if-1',
            [$yesBranch, $noBranch],
            evaluateBoolean: true,
            nodeConfig: ['conditionExpression' => 'context.age > 18'],
        );

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Branch, $result->kind);
        $this->assertSame([$yesBranch], $result->edges);
    }

    #[Test]
    public function it_routes_to_no_branch_when_condition_is_false(): void
    {
        $yesBranch = $this->edge(branchType: 'true');
        $noBranch  = $this->edge(branchType: 'else');
        $ctx = $this->mockContext(
            'if-1',
            [$yesBranch, $noBranch],
            evaluateBoolean: false,
            nodeConfig: ['conditionExpression' => 'context.vip'],
        );

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Branch, $result->kind);
        $this->assertSame([$noBranch], $result->edges);
    }

    #[Test]
    public function it_fails_when_no_condition_expression_configured(): void
    {
        $yesBranch = $this->edge(branchType: 'true');
        $ctx = $this->mockContext('if-1', [$yesBranch], evaluateBoolean: false, nodeConfig: []);

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Fail, $result->kind);
        $this->assertFalse($result->retryable);
    }

    #[Test]
    public function it_uses_node_config_condition_to_take_true_branch(): void
    {
        // Mirrors the real-world if-node setup: condition on node config, branch_type "default" for else
        $trueBranch    = $this->edge(branchType: 'branch_1');
        $defaultBranch = $this->edge(branchType: 'default');

        $ctx = $this->mockContext(
            'if-1',
            [$trueBranch, $defaultBranch],
            evaluateBoolean: true,
            nodeConfig: ['conditionExpression' => 'context.age < 18'],
        );

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Branch, $result->kind);
        $this->assertSame([$trueBranch], $result->edges);
    }

    #[Test]
    public function it_falls_to_default_branch_when_node_config_condition_is_false(): void
    {
        $trueBranch    = $this->edge(branchType: 'branch_1');
        $defaultBranch = $this->edge(branchType: 'default');

        $ctx = $this->mockContext(
            'if-1',
            [$trueBranch, $defaultBranch],
            evaluateBoolean: false,
            nodeConfig: ['conditionExpression' => 'context.age < 18'],
        );

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Branch, $result->kind);
        $this->assertSame([$defaultBranch], $result->edges);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function edge(
        string $conditionExpression = '',
        bool $isDefaultBranch = false,
        string $branchType = 'conditional',
    ): PlanEdge {
        return new PlanEdge(
            id: null,
            source: 'if-1',
            target: 'next',
            branchType: $isDefaultBranch ? 'default' : $branchType,
            conditionExpression: $conditionExpression !== '' ? $conditionExpression : null,
            isDefaultBranch: $isDefaultBranch,
            joinNodeKey: null,
            sortOrder: 0,
        );
    }

    /** @param list<PlanEdge> $outgoing */
    private function mockContext(
        string $nodeKey,
        array $outgoing,
        bool $evaluateBoolean,
        array $nodeConfig = [],
    ): NodeExecutionContext {
        $plan = Mockery::mock(\Modules\Workflows\Services\Execution\ExecutionPlan::class);
        $plan->allows('outgoing')->with($nodeKey)->andReturn($outgoing);

        $ctx = Mockery::mock(NodeExecutionContext::class);
        $ctx->allows('nodeKey')->andReturn($nodeKey);
        $ctx->allows('plan')->andReturn($plan);
        $ctx->allows('config')->andReturn($nodeConfig);
        $ctx->allows('evaluateBoolean')->andReturn($evaluateBoolean);

        return $ctx;
    }
}
