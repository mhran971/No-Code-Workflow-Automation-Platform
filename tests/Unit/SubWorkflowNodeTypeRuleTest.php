<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;
use Modules\Team\Models\Team;
use Modules\Team\Models\TeamMembership;
use Modules\Workflows\Database\Seeders\NodeDefinitionSeeder;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowVersion;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
use Modules\Workflows\Services\Verification\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\Rules\NodeType\SubWorkflowNodeTypeRule;
use Tests\TestCase;

class SubWorkflowNodeTypeRuleTest extends TestCase
{
    use RefreshDatabase;

    protected SubWorkflowNodeTypeRule $rule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NodeDefinitionSeeder::class);
        $this->rule = new SubWorkflowNodeTypeRule;
    }

    public function test_node_type_is_sub_workflow(): void
    {
        $this->assertSame('sub-workflow', $this->rule->nodeType());
    }

    public function test_missing_workflow_id_adds_error(): void
    {
        $node = ['id' => 'sw1', 'type' => 'sub-workflow', 'config' => []];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertFalse($result->isPublishable());
        $this->assertContains('sub_workflow.workflow_id_missing', array_column($result->issues(), 'code'));
    }

    public function test_nonexistent_workflow_adds_error(): void
    {
        $node = ['id' => 'sw1', 'type' => 'sub-workflow', 'config' => ['workflowId' => 99999]];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertFalse($result->isPublishable());
        $this->assertContains('sub_workflow.workflow_not_found', array_column($result->issues(), 'code'));
    }

    public function test_unpublished_workflow_adds_error(): void
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
            'name' => 'Child Workflow',
            'status' => WorkflowStatus::Active->value,
            'draft_definition' => ['trigger' => null, 'nodes' => [], 'edges' => []],
            'draft_revision' => 1,
        ]);

        $node = ['id' => 'sw1', 'type' => 'sub-workflow', 'config' => ['workflowId' => $workflow->id]];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertFalse($result->isPublishable());
        $this->assertContains('sub_workflow.workflow_not_published', array_column($result->issues(), 'code'));
    }

    public function test_published_workflow_passes_validation(): void
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

        $parentWorkflow = Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'created_by_id' => $manager->id,
            'name' => 'Parent Workflow',
            'status' => WorkflowStatus::Active->value,
            'draft_definition' => ['trigger' => null, 'nodes' => [], 'edges' => []],
            'draft_revision' => 1,
        ]);

        $childWorkflow = Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'created_by_id' => $manager->id,
            'name' => 'Child Workflow',
            'status' => WorkflowStatus::Active->value,
            'draft_definition' => ['trigger' => null, 'nodes' => [], 'edges' => []],
            'draft_revision' => 1,
        ]);

        $version = WorkflowVersion::query()->create([
            'workflow_id' => $childWorkflow->id,
            'tenant_id' => $tenant->id,
            'version_number' => 1,
            'version_label' => 'v1.0.0',
            'definition' => [
                'trigger' => ['type' => 'manual-trigger'],
                'nodes' => [['id' => 'manual-trigger', 'type' => 'manual-trigger', 'config' => []]],
                'edges' => [],
            ],
            'published_by_id' => $manager->id,
            'published_at' => now(),
        ]);

        $childWorkflow->update(['current_version_id' => $version->id]);

        $node = ['id' => 'sw1', 'type' => 'sub-workflow', 'config' => ['workflowId' => $childWorkflow->id]];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result, $parentWorkflow);

        $this->assertTrue($result->isPublishable());
    }

    public function test_tenant_mismatch_adds_error(): void
    {
        $tenantA = Tenant::query()->create([
            'business_name' => 'Acme Inc',
            'business_type' => BusinessType::SaaS->value,
        ]);
        $tenantB = Tenant::query()->create([
            'business_name' => 'Other Inc',
            'business_type' => BusinessType::SaaS->value,
        ]);

        $managerA = User::query()->create([
            'first_name' => 'Manager',
            'last_name' => 'A',
            'name' => 'Manager A',
            'email' => 'manager-a-'.uniqid().'@example.test',
            'password' => Hash::make('Pass1234!'),
            'tenant_id' => $tenantA->id,
            'role' => Role::Manager,
            'is_active' => true,
        ]);

        $teamA = Team::query()->create([
            'tenant_id' => $tenantA->id,
            'name' => 'Operations',
            'manager_id' => $managerA->id,
        ]);

        $managerB = User::query()->create([
            'first_name' => 'Manager',
            'last_name' => 'B',
            'name' => 'Manager B',
            'email' => 'manager-b-'.uniqid().'@example.test',
            'password' => Hash::make('Pass1234!'),
            'tenant_id' => $tenantB->id,
            'role' => Role::Manager,
            'is_active' => true,
        ]);

        $teamB = Team::query()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Operations',
            'manager_id' => $managerB->id,
        ]);

        // Parent workflow belongs to tenant A
        $parentWorkflow = Workflow::query()->create([
            'tenant_id' => $tenantA->id,
            'team_id' => $teamA->id,
            'created_by_id' => $managerA->id,
            'name' => 'Parent Workflow',
            'status' => WorkflowStatus::Active->value,
            'draft_definition' => ['trigger' => null, 'nodes' => [], 'edges' => []],
            'draft_revision' => 1,
        ]);

        // Child workflow belongs to tenant B
        $childWorkflow = Workflow::query()->create([
            'tenant_id' => $tenantB->id,
            'team_id' => $teamB->id,
            'created_by_id' => $managerB->id,
            'name' => 'Child Workflow',
            'status' => WorkflowStatus::Active->value,
            'draft_definition' => ['trigger' => null, 'nodes' => [], 'edges' => []],
            'draft_revision' => 1,
        ]);

        $node = ['id' => 'sw1', 'type' => 'sub-workflow', 'config' => ['workflowId' => $childWorkflow->id]];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result, $parentWorkflow);

        $this->assertFalse($result->isPublishable());
        $this->assertContains('sub_workflow.tenant_mismatch', array_column($result->issues(), 'code'));
    }

    public function test_self_reference_adds_error(): void
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
            'name' => 'Self-Referencing Workflow',
            'status' => WorkflowStatus::Active->value,
            'draft_definition' => ['trigger' => null, 'nodes' => [], 'edges' => []],
            'draft_revision' => 1,
        ]);

        $version = WorkflowVersion::query()->create([
            'workflow_id' => $workflow->id,
            'tenant_id' => $tenant->id,
            'version_number' => 1,
            'version_label' => 'v1.0.0',
            'definition' => [
                'trigger' => ['type' => 'manual-trigger'],
                'nodes' => [['id' => 'manual-trigger', 'type' => 'manual-trigger', 'config' => []]],
                'edges' => [],
            ],
            'published_by_id' => $manager->id,
            'published_at' => now(),
        ]);

        $workflow->update(['current_version_id' => $version->id]);

        $node = ['id' => 'sw1', 'type' => 'sub-workflow', 'config' => ['workflowId' => $workflow->id]];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result, $workflow);

        $this->assertFalse($result->isPublishable());
        $this->assertContains('sub_workflow.self_reference', array_column($result->issues(), 'code'));
    }

    public function test_invalid_template_expression_adds_error(): void
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

        $parentWorkflow = Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'created_by_id' => $manager->id,
            'name' => 'Parent',
            'status' => WorkflowStatus::Active->value,
            'draft_definition' => ['trigger' => null, 'nodes' => [], 'edges' => []],
            'draft_revision' => 1,
        ]);

        $childWorkflow = Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'created_by_id' => $manager->id,
            'name' => 'Child',
            'status' => WorkflowStatus::Active->value,
            'draft_definition' => ['trigger' => null, 'nodes' => [], 'edges' => []],
            'draft_revision' => 1,
        ]);

        $version = WorkflowVersion::query()->create([
            'workflow_id' => $childWorkflow->id,
            'tenant_id' => $tenant->id,
            'version_number' => 1,
            'version_label' => 'v1.0.0',
            'definition' => [
                'trigger' => ['type' => 'manual-trigger'],
                'nodes' => [['id' => 'manual-trigger', 'type' => 'manual-trigger', 'config' => []]],
                'edges' => [],
            ],
            'published_by_id' => $manager->id,
            'published_at' => now(),
        ]);

        $childWorkflow->update(['current_version_id' => $version->id]);

        $node = [
            'id' => 'sw1',
            'type' => 'sub-workflow',
            'config' => [
                'workflowId' => $childWorkflow->id,
                'inputMapping' => [
                    'hrEmail' => '{{context.invalidvar',
                ],
            ],
        ];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result, $parentWorkflow);

        $this->assertFalse($result->isPublishable());
        $this->assertContains('sub_workflow.unclosed_template', array_column($result->issues(), 'code'));
    }

    public function test_invalid_variable_name_adds_error(): void
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

        $parentWorkflow = Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'created_by_id' => $manager->id,
            'name' => 'Parent',
            'status' => WorkflowStatus::Active->value,
            'draft_definition' => ['trigger' => null, 'nodes' => [], 'edges' => []],
            'draft_revision' => 1,
        ]);

        $childWorkflow = Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'created_by_id' => $manager->id,
            'name' => 'Child',
            'status' => WorkflowStatus::Active->value,
            'draft_definition' => ['trigger' => null, 'nodes' => [], 'edges' => []],
            'draft_revision' => 1,
        ]);

        $version = WorkflowVersion::query()->create([
            'workflow_id' => $childWorkflow->id,
            'tenant_id' => $tenant->id,
            'version_number' => 1,
            'version_label' => 'v1.0.0',
            'definition' => [
                'trigger' => ['type' => 'manual-trigger'],
                'nodes' => [['id' => 'manual-trigger', 'type' => 'manual-trigger', 'config' => []]],
                'edges' => [],
            ],
            'published_by_id' => $manager->id,
            'published_at' => now(),
        ]);

        $childWorkflow->update(['current_version_id' => $version->id]);

        $node = [
            'id' => 'sw1',
            'type' => 'sub-workflow',
            'config' => [
                'workflowId' => $childWorkflow->id,
                'inputMapping' => [
                    'hrEmail' => '{{invalidvar}}',
                ],
            ],
        ];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result, $parentWorkflow);

        $this->assertFalse($result->isPublishable());
        $this->assertContains('sub_workflow.invalid_template_variable', array_column($result->issues(), 'code'));
    }

    public function test_valid_template_expression_passes(): void
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

        $parentWorkflow = Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'created_by_id' => $manager->id,
            'name' => 'Parent',
            'status' => WorkflowStatus::Active->value,
            'draft_definition' => ['trigger' => null, 'nodes' => [], 'edges' => []],
            'draft_revision' => 1,
        ]);

        $childWorkflow = Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'created_by_id' => $manager->id,
            'name' => 'Child',
            'status' => WorkflowStatus::Active->value,
            'draft_definition' => ['trigger' => null, 'nodes' => [], 'edges' => []],
            'draft_revision' => 1,
        ]);

        $version = WorkflowVersion::query()->create([
            'workflow_id' => $childWorkflow->id,
            'tenant_id' => $tenant->id,
            'version_number' => 1,
            'version_label' => 'v1.0.0',
            'definition' => [
                'trigger' => ['type' => 'manual-trigger'],
                'nodes' => [['id' => 'manual-trigger', 'type' => 'manual-trigger', 'config' => []]],
                'edges' => [],
            ],
            'published_by_id' => $manager->id,
            'published_at' => now(),
        ]);

        $childWorkflow->update(['current_version_id' => $version->id]);

        $node = [
            'id' => 'sw1',
            'type' => 'sub-workflow',
            'config' => [
                'workflowId' => $childWorkflow->id,
                'inputMapping' => [
                    'hrEmail' => '{{context.hrEmail}}',
                    'staticVal' => 'hello',
                ],
            ],
        ];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result, $parentWorkflow);

        $templateErrors = array_filter($result->issues(), fn($i) => in_array($i['code'], ['sub_workflow.unclosed_template', 'sub_workflow.invalid_template_variable', 'sub_workflow.variable_invalid_namespace', 'sub_workflow.variable_undefined']));
        $this->assertEmpty($templateErrors);
    }
}
