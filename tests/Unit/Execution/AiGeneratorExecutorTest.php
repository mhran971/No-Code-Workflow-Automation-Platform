<?php

namespace Tests\Unit\Execution;

use Mockery;
use Modules\Workflows\app\Enums\ResultKind;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Services\Execution\Contracts\AiContentGenerator;
use Modules\Workflows\Services\Execution\Data\PlanEdge;
use Modules\Workflows\Services\Execution\Exceptions\AiServiceException;
use Modules\Workflows\Services\Execution\ExecutionPlan;
use Modules\Workflows\Services\Execution\Executors\AiGeneratorExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiGeneratorExecutorTest extends TestCase
{
    private AiContentGenerator&Mockery\MockInterface $ai;

    private AiGeneratorExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ai = Mockery::mock(AiContentGenerator::class);
        $this->executor = new AiGeneratorExecutor($this->ai);
    }

    #[Test]
    public function it_generates_content_writes_output_variable_and_proceeds(): void
    {
        $edge = $this->edge();
        $ctx = $this->mockContext(42, [$edge], ['prompt' => 'Write a follow-up', 'outputVariable' => 'draft']);

        $this->ai->shouldReceive('generate')
            ->once()
            ->with('Write a follow-up', '42', ['prompt' => 'Write a follow-up', 'outputVariable' => 'draft'])
            ->andReturn('Hi there! Following up...');

        $ctx->shouldReceive('setContextValue')->once()->with('draft', 'Hi there! Following up...');

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Proceed, $result->kind);
        $this->assertSame([$edge], $result->edges);
        $this->assertSame('draft', $result->output['output_variable']);
    }

    #[Test]
    public function it_defaults_output_variable_to_ai_output(): void
    {
        $ctx = $this->mockContext(1, [$this->edge()], ['prompt' => 'hi']);

        $this->ai->shouldReceive('generate')->once()->andReturn('content');
        $ctx->shouldReceive('setContextValue')->once()->with('ai_output', 'content');

        $result = $this->executor->execute($ctx);

        $this->assertSame('ai_output', $result->output['output_variable']);
    }

    #[Test]
    public function it_fails_retryably_on_soft_failure(): void
    {
        $ctx = $this->mockContext(1, [$this->edge()], ['prompt' => 'hi']);

        $this->ai->shouldReceive('generate')
            ->once()
            ->andThrow(new AiServiceException('LLM provider error: Connection timed out', 200, true));

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Fail, $result->kind);
        $this->assertTrue($result->retryable);
    }

    #[Test]
    public function it_fails_non_retryably_on_hard_validation_failure(): void
    {
        $ctx = $this->mockContext(1, [$this->edge()], ['prompt' => 'hi']);

        $this->ai->shouldReceive('generate')
            ->once()
            ->andThrow(new AiServiceException('tenant_id is required', 400, false));

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Fail, $result->kind);
        $this->assertFalse($result->retryable);
    }

    #[Test]
    public function it_fails_retryably_on_server_error(): void
    {
        $ctx = $this->mockContext(1, [$this->edge()], ['prompt' => 'hi']);

        $this->ai->shouldReceive('generate')
            ->once()
            ->andThrow(new AiServiceException('Service unavailable', 503, true));

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Fail, $result->kind);
        $this->assertTrue($result->retryable);
    }

    private function edge(): PlanEdge
    {
        return new PlanEdge(
            id: null,
            source: 'ai-1',
            target: 'next',
            branchType: 'default',
            conditionExpression: null,
            isDefaultBranch: true,
            joinNodeKey: null,
            sortOrder: 0,
        );
    }

    /** @param list<PlanEdge> $outgoing */
    private function mockContext(int $tenantId, array $outgoing, array $nodeConfig): NodeExecutionContext&Mockery\MockInterface
    {
        $plan = Mockery::mock(ExecutionPlan::class);
        $plan->allows('outgoing')->with('ai-1')->andReturn($outgoing);

        $instance = new WorkflowInstance;
        $instance->tenant_id = $tenantId;

        $ctx = Mockery::mock(NodeExecutionContext::class);
        $ctx->allows('nodeKey')->andReturn('ai-1');
        $ctx->allows('plan')->andReturn($plan);
        $ctx->allows('config')->andReturn($nodeConfig);
        $ctx->allows('render')->andReturnUsing(fn (string $template) => $template);
        $ctx->allows('instance')->andReturn($instance);

        return $ctx;
    }
}
