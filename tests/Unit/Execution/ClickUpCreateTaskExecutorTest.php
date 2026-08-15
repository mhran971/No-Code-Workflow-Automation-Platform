<?php

namespace Tests\Unit\Execution;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Models\Tenant;
use Modules\Integrations\Exceptions\IntegrationException;
use Modules\Integrations\Models\IntegrationConnection;
use Modules\Integrations\Models\IntegrationProvider;
use Modules\Integrations\Services\ClickUp\ClickUpClient;
use Modules\Workflows\app\Enums\ResultKind;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Services\Execution\Data\PlanEdge;
use Modules\Workflows\Services\Execution\ExecutionPlan;
use Modules\Workflows\Services\Execution\Executors\ClickUpCreateTaskExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClickUpCreateTaskExecutorTest extends TestCase
{
    use RefreshDatabase;

    private ClickUpClient&Mockery\MockInterface $clickUp;

    private ClickUpCreateTaskExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clickUp = Mockery::mock(ClickUpClient::class);
        $this->executor = new ClickUpCreateTaskExecutor($this->clickUp);
    }

    #[Test]
    public function it_creates_task_and_proceeds(): void
    {
        $tenant = $this->makeTenantWithClickUpConnection();
        $edge = $this->edge();
        $ctx = $this->mockContext($tenant->id, [$edge], ['listId' => '901', 'name' => 'Follow up', 'markdownContent' => '## Notes']);

        $this->clickUp->shouldReceive('createTask')
            ->once()
            ->with(Mockery::type(IntegrationConnection::class), '901', 'Follow up', '## Notes')
            ->andReturn(['id' => 'task1', 'url' => 'https://app.clickup.com/t/task1']);

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Proceed, $result->kind);
        $this->assertSame([$edge], $result->edges);
        $this->assertSame('task1', $result->output['clickup_task_id']);
        $this->assertSame('https://app.clickup.com/t/task1', $result->output['clickup_task_url']);
    }

    #[Test]
    public function it_omits_markdown_content_when_blank(): void
    {
        $tenant = $this->makeTenantWithClickUpConnection();
        $ctx = $this->mockContext($tenant->id, [$this->edge()], ['listId' => '901', 'name' => 'Task']);

        $this->clickUp->shouldReceive('createTask')
            ->once()
            ->with(Mockery::type(IntegrationConnection::class), '901', 'Task', null)
            ->andReturn(['id' => 'task1']);

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Proceed, $result->kind);
    }

    #[Test]
    public function it_fails_non_retryably_when_list_or_name_missing(): void
    {
        $ctx = $this->mockContext(1, [$this->edge()], ['listId' => '', 'name' => '']);

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Fail, $result->kind);
        $this->assertFalse($result->retryable);
    }

    #[Test]
    public function it_fails_non_retryably_when_no_clickup_connection(): void
    {
        $tenant = Tenant::query()->create(['business_name' => 'Acme', 'business_type' => BusinessType::SaaS->value]);
        $ctx = $this->mockContext($tenant->id, [$this->edge()], ['listId' => '901', 'name' => 'Task']);

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Fail, $result->kind);
        $this->assertFalse($result->retryable);
    }

    #[Test]
    public function it_fails_non_retryably_on_client_error(): void
    {
        $tenant = $this->makeTenantWithClickUpConnection();
        $ctx = $this->mockContext($tenant->id, [$this->edge()], ['listId' => 'bad', 'name' => 'Task']);

        $this->clickUp->shouldReceive('createTask')
            ->once()
            ->andThrow(IntegrationException::apiCallFailed('clickup', 'List not found', 404));

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Fail, $result->kind);
        $this->assertFalse($result->retryable);
    }

    #[Test]
    public function it_fails_retryably_on_server_error(): void
    {
        $tenant = $this->makeTenantWithClickUpConnection();
        $ctx = $this->mockContext($tenant->id, [$this->edge()], ['listId' => '901', 'name' => 'Task']);

        $this->clickUp->shouldReceive('createTask')
            ->once()
            ->andThrow(IntegrationException::apiCallFailed('clickup', 'Service unavailable', 503));

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Fail, $result->kind);
        $this->assertTrue($result->retryable);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeTenantWithClickUpConnection(): Tenant
    {
        $tenant = Tenant::query()->create(['business_name' => 'Acme', 'business_type' => BusinessType::SaaS->value]);

        IntegrationProvider::query()->firstOrCreate(['id' => 'clickup'], [
            'id' => 'clickup',
            'name' => 'ClickUp',
            'auth_type' => 'oauth2',
            'config_schema' => [],
            'auth_schema' => [],
            'is_active' => true,
        ]);

        IntegrationConnection::query()->create([
            'integration_provider_id' => 'clickup',
            'tenant_id' => $tenant->id,
            'auth_config' => ['access_token' => 'encrypted-token'],
            'config' => ['teams' => []],
        ]);

        return $tenant;
    }

    private function edge(): PlanEdge
    {
        return new PlanEdge(
            id: null,
            source: 'cu-1',
            target: 'next',
            branchType: 'default',
            conditionExpression: null,
            isDefaultBranch: true,
            joinNodeKey: null,
            sortOrder: 0,
        );
    }

    /** @param list<PlanEdge> $outgoing */
    private function mockContext(int $tenantId, array $outgoing, array $nodeConfig): NodeExecutionContext
    {
        $plan = Mockery::mock(ExecutionPlan::class);
        $plan->allows('outgoing')->with('cu-1')->andReturn($outgoing);

        $instance = new WorkflowInstance;
        $instance->tenant_id = $tenantId;

        $ctx = Mockery::mock(NodeExecutionContext::class);
        $ctx->allows('nodeKey')->andReturn('cu-1');
        $ctx->allows('plan')->andReturn($plan);
        $ctx->allows('config')->andReturn($nodeConfig);
        $ctx->allows('render')->andReturnUsing(fn (string $template) => $template);
        $ctx->allows('instance')->andReturn($instance);

        return $ctx;
    }
}
