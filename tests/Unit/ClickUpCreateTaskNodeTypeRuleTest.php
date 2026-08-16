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
use Modules\Workflows\Services\Verification\Rules\NodeType\ClickUpCreateTaskNodeTypeRule;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
use Tests\TestCase;

class ClickUpCreateTaskNodeTypeRuleTest extends TestCase
{
    use RefreshDatabase;

    protected ClickUpCreateTaskNodeTypeRule $rule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule = new ClickUpCreateTaskNodeTypeRule;
    }

    public function test_node_type_is_clickup_create_task(): void
    {
        $this->assertSame('clickup-create-task', $this->rule->nodeType());
    }

    public function test_missing_workspace_adds_error(): void
    {
        $node = ['id' => 'cu1', 'type' => 'clickup-create-task', 'config' => ['listId' => '1', 'name' => 'Task']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertFalse($result->isPublishable());
        $this->assertContains('clickup_create_task.workspace_missing', array_column($result->issues(), 'code'));
    }

    public function test_missing_list_adds_error(): void
    {
        $node = ['id' => 'cu1', 'type' => 'clickup-create-task', 'config' => ['workspaceId' => '1', 'name' => 'Task']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertFalse($result->isPublishable());
        $this->assertContains('clickup_create_task.list_missing', array_column($result->issues(), 'code'));
    }

    public function test_missing_name_adds_error(): void
    {
        $node = ['id' => 'cu1', 'type' => 'clickup-create-task', 'config' => ['workspaceId' => '1', 'listId' => '2']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertFalse($result->isPublishable());
        $this->assertContains('clickup_create_task.name_missing', array_column($result->issues(), 'code'));
    }

    public function test_invalid_template_variable_in_name_adds_error(): void
    {
        $node = [
            'id' => 'cu1',
            'type' => 'clickup-create-task',
            'config' => ['workspaceId' => '1', 'listId' => '2', 'name' => '{{invalidvar}}'],
        ];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertFalse($result->isPublishable());
        $this->assertContains('clickup_create_task.invalid_template_variable', array_column($result->issues(), 'code'));
    }

    public function test_no_workflow_context_skips_connection_check(): void
    {
        $node = [
            'id' => 'cu1',
            'type' => 'clickup-create-task',
            'config' => ['workspaceId' => '1', 'listId' => '2', 'name' => 'Task'],
        ];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertTrue($result->isPublishable());
    }

    public function test_missing_clickup_connection_adds_error(): void
    {
        [, $workflow] = $this->makeTenantAndWorkflow();

        $node = [
            'id' => 'cu1',
            'type' => 'clickup-create-task',
            'config' => ['workspaceId' => '1', 'listId' => '2', 'name' => 'Task'],
        ];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result, $workflow);

        $this->assertFalse($result->isPublishable());
        $this->assertContains('clickup_create_task.connection_missing', array_column($result->issues(), 'code'));
    }

    public function test_valid_config_with_connection_passes(): void
    {
        [$tenant, $workflow] = $this->makeTenantAndWorkflow();

        IntegrationProvider::query()->create([
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

        $node = [
            'id' => 'cu1',
            'type' => 'clickup-create-task',
            'config' => ['workspaceId' => '1', 'listId' => '2', 'name' => 'Follow up with {{context.customerName}}'],
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
