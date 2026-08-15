<?php

namespace Tests\Unit\Execution;

use Mockery;
use Modules\Workflows\app\Enums\ResultKind;
use Modules\Workflows\Services\Execution\Data\PlanEdge;
use Modules\Workflows\Services\Execution\ExecutionPlan;
use Modules\Workflows\Services\Execution\Executors\ParseJsonExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ParseJsonExecutorTest extends TestCase
{
    private ParseJsonExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->executor = new ParseJsonExecutor;
    }

    #[Test]
    public function it_parses_valid_json_and_writes_output_variable(): void
    {
        $edge = $this->edge();
        $ctx = $this->mockContext(
            'pj-1',
            [$edge],
            evaluate: '{"name":"Ada","age":36}',
            nodeConfig: ['inputVariable' => 'context.rawJson', 'outputVariable' => 'parsed'],
        );
        $ctx->shouldReceive('setContextValue')->once()->with('parsed', ['name' => 'Ada', 'age' => 36]);

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Proceed, $result->kind);
        $this->assertSame([$edge], $result->edges);
    }

    #[Test]
    public function it_fails_non_retryably_on_malformed_json(): void
    {
        $ctx = $this->mockContext(
            'pj-1',
            [$this->edge()],
            evaluate: '{not valid json',
            nodeConfig: ['inputVariable' => 'context.rawJson', 'outputVariable' => 'parsed'],
        );

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Fail, $result->kind);
        $this->assertFalse($result->retryable);
    }

    #[Test]
    public function it_fails_non_retryably_when_input_is_not_a_string(): void
    {
        $ctx = $this->mockContext(
            'pj-1',
            [$this->edge()],
            evaluate: ['already' => 'an array'],
            nodeConfig: ['inputVariable' => 'context.rawJson', 'outputVariable' => 'parsed'],
        );

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Fail, $result->kind);
        $this->assertFalse($result->retryable);
    }

    #[Test]
    public function it_fails_non_retryably_when_variable_config_is_missing(): void
    {
        $ctx = $this->mockContext('pj-1', [$this->edge()], evaluate: null, nodeConfig: []);

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Fail, $result->kind);
        $this->assertFalse($result->retryable);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function edge(): PlanEdge
    {
        return new PlanEdge(
            id: null,
            source: 'pj-1',
            target: 'next',
            branchType: 'default',
            conditionExpression: null,
            isDefaultBranch: true,
            joinNodeKey: null,
            sortOrder: 0,
        );
    }

    /** @param list<PlanEdge> $outgoing */
    private function mockContext(
        string $nodeKey,
        array $outgoing,
        mixed $evaluate,
        array $nodeConfig = [],
    ): NodeExecutionContext {
        $plan = Mockery::mock(ExecutionPlan::class);
        $plan->allows('outgoing')->with($nodeKey)->andReturn($outgoing);

        $ctx = Mockery::mock(NodeExecutionContext::class);
        $ctx->allows('nodeKey')->andReturn($nodeKey);
        $ctx->allows('plan')->andReturn($plan);
        $ctx->allows('config')->andReturn($nodeConfig);
        $ctx->allows('evaluate')->andReturn($evaluate);

        return $ctx;
    }
}
