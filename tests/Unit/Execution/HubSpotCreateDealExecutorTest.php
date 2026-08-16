<?php

namespace Tests\Unit\Execution;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Models\Tenant;
use Modules\Integrations\Exceptions\IntegrationException;
use Modules\Integrations\Models\IntegrationConnection;
use Modules\Integrations\Models\IntegrationProvider;
use Modules\Integrations\Services\HubSpot\HubSpotClient;
use Modules\Workflows\app\Enums\ResultKind;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Services\Execution\Data\PlanEdge;
use Modules\Workflows\Services\Execution\ExecutionPlan;
use Modules\Workflows\Services\Execution\Executors\HubSpotCreateDealExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HubSpotCreateDealExecutorTest extends TestCase
{
    use RefreshDatabase;

    private HubSpotClient&Mockery\MockInterface $hubSpot;

    private HubSpotCreateDealExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hubSpot = Mockery::mock(HubSpotClient::class);
        $this->executor = new HubSpotCreateDealExecutor($this->hubSpot);
    }

    #[Test]
    public function it_creates_deal_and_proceeds(): void
    {
        $tenant = $this->makeTenantWithHubSpotConnection();
        $edge = $this->edge();
        $ctx = $this->mockContext($tenant->id, [$edge], [
            'dealName' => 'Big Deal', 'dealStage' => 'contractsent', 'pipeline' => 'default',
            'amount' => '1500.00', 'closeDate' => '2026-12-07T16:50:06.678Z', 'ownerId' => '910901',
        ]);

        $this->hubSpot->shouldReceive('createDeal')
            ->once()
            ->with(Mockery::type(IntegrationConnection::class), [
                'dealname' => 'Big Deal', 'dealstage' => 'contractsent', 'pipeline' => 'default',
                'amount' => '1500.00', 'closedate' => '2026-12-07T16:50:06.678Z', 'hubspot_owner_id' => '910901',
            ], [])
            ->andReturn(['id' => 'deal1']);

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Proceed, $result->kind);
        $this->assertSame([$edge], $result->edges);
        $this->assertSame('deal1', $result->output['hubspot_deal_id']);
    }

    #[Test]
    public function it_omits_blank_optional_fields(): void
    {
        $tenant = $this->makeTenantWithHubSpotConnection();
        $ctx = $this->mockContext($tenant->id, [$this->edge()], ['dealName' => 'Deal', 'dealStage' => 'stage1']);

        $this->hubSpot->shouldReceive('createDeal')
            ->once()
            ->with(Mockery::type(IntegrationConnection::class), ['dealname' => 'Deal', 'dealstage' => 'stage1'], [])
            ->andReturn(['id' => 'deal1']);

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Proceed, $result->kind);
    }

    #[Test]
    public function it_associates_a_numeric_contact_id(): void
    {
        $tenant = $this->makeTenantWithHubSpotConnection();
        $ctx = $this->mockContext($tenant->id, [$this->edge()], [
            'dealName' => 'Deal', 'dealStage' => 'stage1', 'contactId' => '12345',
        ]);

        $this->hubSpot->shouldReceive('createDeal')
            ->once()
            ->with(
                Mockery::type(IntegrationConnection::class),
                ['dealname' => 'Deal', 'dealstage' => 'stage1'],
                [['to' => ['id' => 12345], 'types' => [['associationCategory' => 'HUBSPOT_DEFINED', 'associationTypeId' => 3]]]],
            )
            ->andReturn(['id' => 'deal1']);

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Proceed, $result->kind);
    }

    #[Test]
    public function it_ignores_a_non_numeric_contact_id(): void
    {
        $tenant = $this->makeTenantWithHubSpotConnection();
        $ctx = $this->mockContext($tenant->id, [$this->edge()], [
            'dealName' => 'Deal', 'dealStage' => 'stage1', 'contactId' => 'not-a-number',
        ]);

        $this->hubSpot->shouldReceive('createDeal')
            ->once()
            ->with(Mockery::type(IntegrationConnection::class), ['dealname' => 'Deal', 'dealstage' => 'stage1'], [])
            ->andReturn(['id' => 'deal1']);

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Proceed, $result->kind);
    }

    #[Test]
    public function it_fails_non_retryably_when_deal_name_or_stage_missing(): void
    {
        $ctx = $this->mockContext(1, [$this->edge()], ['dealName' => '', 'dealStage' => '']);

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Fail, $result->kind);
        $this->assertFalse($result->retryable);
    }

    #[Test]
    public function it_fails_non_retryably_when_no_hubspot_connection(): void
    {
        $tenant = Tenant::query()->create(['business_name' => 'Acme', 'business_type' => BusinessType::SaaS->value]);
        $ctx = $this->mockContext($tenant->id, [$this->edge()], ['dealName' => 'Deal', 'dealStage' => 'stage1']);

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Fail, $result->kind);
        $this->assertFalse($result->retryable);
    }

    #[Test]
    public function it_fails_non_retryably_on_invalid_dealstage(): void
    {
        $tenant = $this->makeTenantWithHubSpotConnection();
        $ctx = $this->mockContext($tenant->id, [$this->edge()], ['dealName' => 'Deal', 'dealStage' => 'bad-stage']);

        $this->hubSpot->shouldReceive('createDeal')
            ->once()
            ->andThrow(IntegrationException::apiCallFailed('hubspot', 'Property values were not valid', 400));

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Fail, $result->kind);
        $this->assertFalse($result->retryable);
    }

    #[Test]
    public function it_fails_retryably_on_server_error(): void
    {
        $tenant = $this->makeTenantWithHubSpotConnection();
        $ctx = $this->mockContext($tenant->id, [$this->edge()], ['dealName' => 'Deal', 'dealStage' => 'stage1']);

        $this->hubSpot->shouldReceive('createDeal')
            ->once()
            ->andThrow(IntegrationException::apiCallFailed('hubspot', 'Service unavailable', 503));

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Fail, $result->kind);
        $this->assertTrue($result->retryable);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeTenantWithHubSpotConnection(): Tenant
    {
        $tenant = Tenant::query()->create(['business_name' => 'Acme', 'business_type' => BusinessType::SaaS->value]);

        IntegrationProvider::query()->firstOrCreate(['id' => 'hubspot'], [
            'id' => 'hubspot',
            'name' => 'HubSpot',
            'auth_type' => 'oauth2',
            'config_schema' => [],
            'auth_schema' => [],
            'is_active' => true,
        ]);

        IntegrationConnection::query()->create([
            'integration_provider_id' => 'hubspot',
            'tenant_id' => $tenant->id,
            'auth_config' => ['access_token' => 'encrypted-token', 'refresh_token' => 'encrypted-refresh'],
            'config' => ['portal_id' => '12345'],
        ]);

        return $tenant;
    }

    private function edge(): PlanEdge
    {
        return new PlanEdge(
            id: null,
            source: 'hd-1',
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
        $plan->allows('outgoing')->with('hd-1')->andReturn($outgoing);

        $instance = new WorkflowInstance;
        $instance->tenant_id = $tenantId;

        $ctx = Mockery::mock(NodeExecutionContext::class);
        $ctx->allows('nodeKey')->andReturn('hd-1');
        $ctx->allows('plan')->andReturn($plan);
        $ctx->allows('config')->andReturn($nodeConfig);
        $ctx->allows('render')->andReturnUsing(fn (string $template) => $template);
        $ctx->allows('instance')->andReturn($instance);

        return $ctx;
    }
}
