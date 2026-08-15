<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;
use Modules\Integrations\Models\IntegrationConnection;
use Modules\Integrations\Models\IntegrationProvider;
use Modules\Team\Models\Team;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\Rules\NodeType\HubSpotCreateDealNodeTypeRule;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
use Tests\TestCase;

class HubSpotCreateDealNodeTypeRuleTest extends TestCase
{
    use RefreshDatabase;

    protected HubSpotCreateDealNodeTypeRule $rule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule = new HubSpotCreateDealNodeTypeRule;
    }

    public function test_node_type_is_hubspot_create_deal(): void
    {
        $this->assertSame('hubspot-create-deal', $this->rule->nodeType());
    }

    public function test_missing_deal_name_adds_error(): void
    {
        $node = ['id' => 'hd1', 'type' => 'hubspot-create-deal', 'config' => ['dealStage' => 'stage1']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertFalse($result->isPublishable());
        $this->assertContains('hubspot_create_deal.dealname_missing', array_column($result->issues(), 'code'));
    }

    public function test_missing_deal_stage_adds_error(): void
    {
        $node = ['id' => 'hd1', 'type' => 'hubspot-create-deal', 'config' => ['dealName' => 'Big Deal']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertFalse($result->isPublishable());
        $this->assertContains('hubspot_create_deal.dealstage_missing', array_column($result->issues(), 'code'));
    }

    public function test_invalid_template_variable_in_deal_name_adds_error(): void
    {
        $node = ['id' => 'hd1', 'type' => 'hubspot-create-deal', 'config' => ['dealName' => '{{invalidvar}}', 'dealStage' => 'stage1']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertContains('hubspot_create_deal.invalid_template_variable', array_column($result->issues(), 'code'));
    }

    public function test_invalid_template_variable_in_amount_adds_error(): void
    {
        $node = ['id' => 'hd1', 'type' => 'hubspot-create-deal', 'config' => ['dealName' => 'Deal', 'dealStage' => 'stage1', 'amount' => '{{bad syntax}}']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertContains('hubspot_create_deal.invalid_template_variable', array_column($result->issues(), 'code'));
    }

    public function test_no_workflow_context_skips_connection_check(): void
    {
        $node = ['id' => 'hd1', 'type' => 'hubspot-create-deal', 'config' => ['dealName' => 'Deal', 'dealStage' => 'stage1']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertTrue($result->isPublishable());
    }

    public function test_missing_hubspot_connection_adds_error(): void
    {
        [, $workflow] = $this->makeTenantAndWorkflow();

        $node = ['id' => 'hd1', 'type' => 'hubspot-create-deal', 'config' => ['dealName' => 'Deal', 'dealStage' => 'stage1']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result, $workflow);

        $this->assertFalse($result->isPublishable());
        $this->assertContains('hubspot_create_deal.connection_missing', array_column($result->issues(), 'code'));
    }

    public function test_valid_config_with_connection_passes(): void
    {
        [$tenant, $workflow] = $this->makeTenantAndWorkflow();

        IntegrationProvider::query()->create([
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

        $node = [
            'id' => 'hd1',
            'type' => 'hubspot-create-deal',
            'config' => [
                'dealName' => '{{context.dealName}}',
                'dealStage' => 'contractsent',
                'pipeline' => 'default',
                'amount' => '{{context.amount}}',
                'closeDate' => '{{context.closeDate}}',
                'ownerId' => '910901',
                'contactId' => '{{context.contactId}}',
            ],
        ];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result, $workflow);

        $this->assertTrue($result->isPublishable());
    }

    /**
     * @return array{0: Tenant, 1: Workflow}
     */
    private function makeTenantAndWorkflow(): array
    {
        $tenant = Tenant::query()->create([
            'business_name' => 'Acme Inc',
            'business_type' => BusinessType::SaaS->value,
        ]);

        $manager = User::query()->create([
            'first_name' => 'Manager',
            'last_name' => 'User',
            'name' => 'Manager User',
            'email' => 'manager-'.uniqid().'@example.test',
            'password' => Hash::make('Pass1234!'),
            'tenant_id' => $tenant->id,
            'role' => Role::Manager,
            'is_active' => true,
        ]);

        $team = Team::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Operations',
            'manager_id' => $manager->id,
        ]);

        $workflow = Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'created_by_id' => $manager->id,
            'name' => 'Parent Workflow',
            'status' => WorkflowStatus::Active->value,
            'draft_definition' => ['trigger' => null, 'nodes' => [], 'edges' => []],
            'draft_revision' => 1,
        ]);

        return [$tenant, $workflow];
    }
}
