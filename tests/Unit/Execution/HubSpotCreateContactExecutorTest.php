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
use Modules\Workflows\Services\Execution\Executors\HubSpotCreateContactExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HubSpotCreateContactExecutorTest extends TestCase
{
    use RefreshDatabase;

    private HubSpotClient&Mockery\MockInterface $hubSpot;

    private HubSpotCreateContactExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hubSpot = Mockery::mock(HubSpotClient::class);
        $this->executor = new HubSpotCreateContactExecutor($this->hubSpot);
    }

    #[Test]
    public function it_creates_contact_and_proceeds(): void
    {
        $tenant = $this->makeTenantWithHubSpotConnection();
        $edge = $this->edge();
        $ctx = $this->mockContext($tenant->id, [$edge], [
            'firstName' => 'Jane', 'lastName' => 'Doe', 'email' => 'jane@example.com', 'phone' => '555-0100',
        ]);

        $this->hubSpot->shouldReceive('createContact')
            ->once()
            ->with(Mockery::type(IntegrationConnection::class), [
                'firstname' => 'Jane', 'lastname' => 'Doe', 'email' => 'jane@example.com', 'phone' => '555-0100',
            ])
            ->andReturn(['id' => 'contact1']);

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Proceed, $result->kind);
        $this->assertSame([$edge], $result->edges);
        $this->assertSame('contact1', $result->output['hubspot_contact_id']);
    }

    #[Test]
    public function it_omits_blank_fields_from_properties(): void
    {
        $tenant = $this->makeTenantWithHubSpotConnection();
        $ctx = $this->mockContext($tenant->id, [$this->edge()], ['email' => 'jane@example.com']);

        $this->hubSpot->shouldReceive('createContact')
            ->once()
            ->with(Mockery::type(IntegrationConnection::class), ['email' => 'jane@example.com'])
            ->andReturn(['id' => 'contact1']);

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Proceed, $result->kind);
    }

    #[Test]
    public function it_fails_non_retryably_when_no_identifying_field_present(): void
    {
        $ctx = $this->mockContext(1, [$this->edge()], ['phone' => '555-0100']);

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Fail, $result->kind);
        $this->assertFalse($result->retryable);
    }

    #[Test]
    public function it_fails_non_retryably_when_no_hubspot_connection(): void
    {
        $tenant = Tenant::query()->create(['business_name' => 'Acme', 'business_type' => BusinessType::SaaS->value]);
        $ctx = $this->mockContext($tenant->id, [$this->edge()], ['email' => 'jane@example.com']);

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Fail, $result->kind);
        $this->assertFalse($result->retryable);
    }

    #[Test]
    public function it_fails_non_retryably_on_duplicate_email(): void
    {
        $tenant = $this->makeTenantWithHubSpotConnection();
        $ctx = $this->mockContext($tenant->id, [$this->edge()], ['email' => 'jane@example.com']);

        $this->hubSpot->shouldReceive('createContact')
            ->once()
            ->andThrow(IntegrationException::apiCallFailed('hubspot', 'Contact already exists', 409));

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Fail, $result->kind);
        $this->assertFalse($result->retryable);
    }

    #[Test]
    public function it_fails_retryably_on_server_error(): void
    {
        $tenant = $this->makeTenantWithHubSpotConnection();
        $ctx = $this->mockContext($tenant->id, [$this->edge()], ['email' => 'jane@example.com']);

        $this->hubSpot->shouldReceive('createContact')
            ->once()
            ->andThrow(IntegrationException::apiCallFailed('hubspot', 'Service unavailable', 503));

        $result = $this->executor->execute($ctx);

        $this->assertSame(ResultKind::Fail, $result->kind);
        $this->assertTrue($result->retryable);
    }

    #[Test]
    public function it_fails_retryably_on_rate_limit(): void
    {
        $tenant = $this->makeTenantWithHubSpotConnection();
        $ctx = $this->mockContext($tenant->id, [$this->edge()], ['email' => 'jane@example.com']);

        $this->hubSpot->shouldReceive('createContact')
            ->once()
            ->andThrow(IntegrationException::apiCallFailed('hubspot', 'Rate limited', 429));

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
            source: 'hs-1',
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
        $plan->allows('outgoing')->with('hs-1')->andReturn($outgoing);

        $instance = new WorkflowInstance;
        $instance->tenant_id = $tenantId;

        $ctx = Mockery::mock(NodeExecutionContext::class);
        $ctx->allows('nodeKey')->andReturn('hs-1');
        $ctx->allows('plan')->andReturn($plan);
        $ctx->allows('config')->andReturn($nodeConfig);
        $ctx->allows('render')->andReturnUsing(fn (string $template) => $template);
        $ctx->allows('instance')->andReturn($instance);

        return $ctx;
    }
}
