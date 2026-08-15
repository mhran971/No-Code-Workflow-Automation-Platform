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
use Modules\Workflows\Services\Verification\Rules\NodeType\HubSpotCreateContactNodeTypeRule;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
use Tests\TestCase;

class HubSpotCreateContactNodeTypeRuleTest extends TestCase
{
    use RefreshDatabase;

    protected HubSpotCreateContactNodeTypeRule $rule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule = new HubSpotCreateContactNodeTypeRule;
    }

    public function test_node_type_is_hubspot_create_contact(): void
    {
        $this->assertSame('hubspot-create-contact', $this->rule->nodeType());
    }

    public function test_all_fields_blank_adds_identity_missing_error(): void
    {
        $node = ['id' => 'hs1', 'type' => 'hubspot-create-contact', 'config' => []];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertFalse($result->isPublishable());
        $this->assertContains('hubspot_create_contact.identity_missing', array_column($result->issues(), 'code'));
    }

    public function test_phone_alone_is_not_enough_identity(): void
    {
        $node = ['id' => 'hs1', 'type' => 'hubspot-create-contact', 'config' => ['phone' => '555-0100']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertContains('hubspot_create_contact.identity_missing', array_column($result->issues(), 'code'));
    }

    public function test_first_name_alone_satisfies_identity_requirement(): void
    {
        $node = ['id' => 'hs1', 'type' => 'hubspot-create-contact', 'config' => ['firstName' => 'Jane']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertNotContains('hubspot_create_contact.identity_missing', array_column($result->issues(), 'code'));
    }

    public function test_email_alone_satisfies_identity_requirement(): void
    {
        $node = ['id' => 'hs1', 'type' => 'hubspot-create-contact', 'config' => ['email' => 'jane@example.com']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertNotContains('hubspot_create_contact.identity_missing', array_column($result->issues(), 'code'));
    }

    public function test_invalid_literal_email_adds_error(): void
    {
        $node = ['id' => 'hs1', 'type' => 'hubspot-create-contact', 'config' => ['email' => 'not-an-email']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertContains('hubspot_create_contact.email_invalid', array_column($result->issues(), 'code'));
    }

    public function test_valid_template_variable_email_passes(): void
    {
        $node = ['id' => 'hs1', 'type' => 'hubspot-create-contact', 'config' => ['email' => '{{context.email}}']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertNotContains('hubspot_create_contact.email_invalid', array_column($result->issues(), 'code'));
    }

    public function test_invalid_template_variable_syntax_in_email_adds_error(): void
    {
        $node = ['id' => 'hs1', 'type' => 'hubspot-create-contact', 'config' => ['email' => '{{not valid}}']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertContains('hubspot_create_contact.email_invalid', array_column($result->issues(), 'code'));
    }

    public function test_invalid_template_variable_in_first_name_adds_error(): void
    {
        $node = ['id' => 'hs1', 'type' => 'hubspot-create-contact', 'config' => ['firstName' => '{{invalidvar}}']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertContains('hubspot_create_contact.invalid_template_variable', array_column($result->issues(), 'code'));
    }

    public function test_no_workflow_context_skips_connection_check(): void
    {
        $node = ['id' => 'hs1', 'type' => 'hubspot-create-contact', 'config' => ['email' => 'jane@example.com']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertTrue($result->isPublishable());
    }

    public function test_missing_hubspot_connection_adds_error(): void
    {
        [, $workflow] = $this->makeTenantAndWorkflow();

        $node = ['id' => 'hs1', 'type' => 'hubspot-create-contact', 'config' => ['email' => 'jane@example.com']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result, $workflow);

        $this->assertFalse($result->isPublishable());
        $this->assertContains('hubspot_create_contact.connection_missing', array_column($result->issues(), 'code'));
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
            'id' => 'hs1',
            'type' => 'hubspot-create-contact',
            'config' => [
                'firstName' => '{{context.firstName}}',
                'lastName' => '{{context.lastName}}',
                'email' => '{{context.email}}',
                'phone' => '{{context.phone}}',
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
