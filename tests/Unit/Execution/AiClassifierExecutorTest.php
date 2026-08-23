<?php

namespace Tests\Unit\Execution;

use Mockery;
use Modules\Workflows\app\Enums\ResultKind;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Services\Execution\Contracts\AiTextClassifier;
use Modules\Workflows\Services\Execution\Data\PlanEdge;
use Modules\Workflows\Services\Execution\Exceptions\AiServiceException;
use Modules\Workflows\Services\Execution\ExecutionPlan;
use Modules\Workflows\Services\Execution\Executors\AiClassifierExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiClassifierExecutorTest extends TestCase
{
    private AiTextClassifier&Mockery\MockInterface $ai;

    private AiClassifierExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ai = Mockery::mock(AiTextClassifier::class);
        $this->executor = new AiClassifierExecutor($this->ai);
    }

    #[Test]
    public function it_classifies_text_writes_output_variable_and_proceeds(): void
    {
        $edge = $this->edge();
        $ctx = $this->mockContext(42, [$edge], [
            'text' => 'I want a refund',
            'categories' => ['billing', 'shipping', 'product_defect'],
            'outputVariable' => 'classification',
        ]);

        $this->ai->shouldReceive('classify')
            ->once()
            ->with('I want a refund', ['billing', 'shipping', 'product_defect'], '42')
            ->andReturn(['classification' => 'shipping', 'confidence' => 0.87]);

        $ctx->shouldReceive('setContextValue')
            ->once()
            ->with('classification', ['classification' => 'shipping', 'confidence' => 0.87]);

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Proceed, $result->kind);
        $this->assertSame([$edge], $result->edges);
        $this->assertSame('shipping', $result->output['classification']);
        $this->assertSame(0.87, $result->output['confidence']);
    }

    #[Test]
    public function it_defaults_output_variable_to_ai_classification(): void
    {
        $ctx = $this->mockContext(1, [$this->edge()], [
            'text' => 'hello', 'categories' => ['a', 'b'],
        ]);

        $this->ai->shouldReceive('classify')->once()->andReturn(['classification' => 'a', 'confidence' => 0.5]);
        $ctx->shouldReceive('setContextValue')->once()->with('ai_classification', ['classification' => 'a', 'confidence' => 0.5]);

        $result = $this->executor->execute($ctx);

        $this->assertSame('ai_classification', $result->output['output_variable']);
    }

    #[Test]
    public function it_trims_blank_categories_before_calling_the_classifier(): void
    {
        $ctx = $this->mockContext(1, [$this->edge()], [
            'text' => 'hello', 'categories' => ['billing', '', ' shipping '],
        ]);

        $this->ai->shouldReceive('classify')
            ->once()
            ->with('hello', ['billing', 'shipping'], '1')
            ->andReturn(['classification' => 'billing', 'confidence' => 0.9]);

        $ctx->shouldReceive('setContextValue')->once();

        $this->executor->execute($ctx);
    }

    #[Test]
    public function it_fails_retryably_on_soft_failure(): void
    {
        $ctx = $this->mockContext(1, [$this->edge()], ['text' => 'hello', 'categories' => ['a', 'b']]);

        $this->ai->shouldReceive('classify')
            ->once()
            ->andThrow(new AiServiceException('Unable to classify: no matching category', 200, true));

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Fail, $result->kind);
        $this->assertTrue($result->retryable);
    }

    #[Test]
    public function it_fails_non_retryably_on_hard_validation_failure(): void
    {
        $ctx = $this->mockContext(1, [$this->edge()], ['text' => 'hello', 'categories' => ['a', 'b']]);

        $this->ai->shouldReceive('classify')
            ->once()
            ->andThrow(new AiServiceException('categories must contain at least 2 values', 400, false));

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Fail, $result->kind);
        $this->assertFalse($result->retryable);
    }

    private function edge(): PlanEdge
    {
        return new PlanEdge(
            id: null,
            source: 'ac-1',
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
        $plan->allows('outgoing')->with('ac-1')->andReturn($outgoing);

        $instance = new WorkflowInstance;
        $instance->tenant_id = $tenantId;

        $ctx = Mockery::mock(NodeExecutionContext::class);
        $ctx->allows('nodeKey')->andReturn('ac-1');
        $ctx->allows('plan')->andReturn($plan);
        $ctx->allows('config')->andReturn($nodeConfig);
        $ctx->allows('render')->andReturnUsing(fn (string $template) => $template);
        $ctx->allows('instance')->andReturn($instance);

        return $ctx;
    }
}
