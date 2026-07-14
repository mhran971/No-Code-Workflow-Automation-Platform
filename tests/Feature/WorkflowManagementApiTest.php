<?php

namespace Tests\Feature;

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
use Modules\Workflows\Models\WorkflowTemplate;
use Tests\TestCase;

class WorkflowManagementApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NodeDefinitionSeeder::class);
    }

    public function test_manager_creates_workflow_and_team_employee_can_view_it(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->createUser($tenant, Role::BusinessOwner, 'owner-create');
        $manager = $this->createUser($tenant, Role::Manager, 'manager-create');
        $employee = $this->createUser($tenant, Role::Employee, 'employee-create');
        $team = $this->createTeam($tenant, $manager, 'HR Team');
        $this->assignToTeam($tenant, $manager, $team);
        $this->assignToTeam($tenant, $employee, $team);

        $createResponse = $this->actingAs($manager, 'api')->postJson('/api/v1/workflows', [
            'method' => 'blank',
            'name' => 'Employee Onboarding',
            'description' => 'Automated sequence for provisioning access',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('workflow.name', 'Employee Onboarding')
            ->assertJsonPath('workflow.status', WorkflowStatus::Disabled->value)
            ->assertJsonPath('workflow.team.id', $team->id)
            ->assertJsonPath('workflow.created_by.id', $manager->id)
            ->assertJsonPath('workflow.version_number', 0)
            ->assertJsonPath('workflow.total_runs', 0)
            ->assertJsonPath('workflow.active_instances', 0);

        $workflowId = (int) $createResponse->json('workflow.id');

        $this->assertDatabaseHas('workflows', [
            'id' => $workflowId,
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'created_by_id' => $manager->id,
        ]);

        $this->actingAs($employee, 'api')
            ->getJson('/api/v1/workflows')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $workflowId)
            ->assertJsonPath('data.0.actions.0', 'view');

        $this->actingAs($owner, 'api')
            ->getJson('/api/v1/workflows')
            ->assertOk()
            ->assertJsonPath('data.0.id', $workflowId);
    }

    public function test_workflow_can_be_created_from_template_and_usage_count_is_incremented(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager-template');
        $team = $this->createTeam($tenant, $manager, 'Operations');
        $this->assignToTeam($tenant, $manager, $team);

        $template = WorkflowTemplate::query()->create([
            'tenant_id' => null,
            'name' => 'Onboarding Template',
            'description' => 'Pre-built onboarding flow',
            'category' => 'HR',
            'definition' => $this->validDefinition(),
            'is_active' => true,
            'usage_count' => 0,
        ]);

        $this->actingAs($manager, 'api')
            ->postJson('/api/v1/workflows', [
                'method' => 'template',
                'name' => 'Template Based Workflow',
                'template_id' => $template->id,
            ])
            ->assertCreated()
            ->assertJsonPath('workflow.name', 'Template Based Workflow');

        $this->assertDatabaseHas('workflows', [
            'template_id' => $template->id,
            'name' => 'Template Based Workflow',
        ]);

        $this->assertSame(1, (int) $template->refresh()->usage_count);
    }

    public function test_ai_proposal_is_not_persisted_until_manager_confirms_creation(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager-ai');

        $response = $this->actingAs($manager, 'api')->postJson('/api/v1/workflows/proposals/ai', [
            'goal' => 'When a new employee joins, create tasks and notify the HR team.',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['proposal_definition', 'proposal_checksum', 'warnings', 'confidence', 'generated_at']);

        $this->assertDatabaseCount('workflows', 0);
        $this->assertDatabaseCount('workflow_versions', 0);
    }

    public function test_validation_endpoint_returns_detailed_request_and_response_schema(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager-validate-schema');

        $definition = $this->validDefinition();
        $definition['nodes'][0]['type'] = 'missing-node-type';

        $response = $this->actingAs($manager, 'api')->postJson('/api/v1/workflows/validate', [
            'definition' => $definition,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'request_schema' => ['type', 'required', 'properties'],
                'response_schema' => ['type', 'properties'],
                'issues' => [
                    '*' => ['severity', 'code', 'message', 'location' => ['path', 'field', 'scope', 'node_id', 'edge_id']],
                ],
            ])
            ->assertJsonPath('issues.0.location.path', 'nodes[0].type')
            ->assertJsonPath('issues.0.location.node_id', 'send-welcome-email');
    }

    public function test_draft_publish_activate_and_trigger_update_version_and_counters(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager-publish');
        $team = $this->createTeam($tenant, $manager, 'Automation');
        $this->assignToTeam($tenant, $manager, $team);
        $workflow = $this->createWorkflow($tenant, $team, $manager);

        $draftResponse = $this->actingAs($manager, 'api')->patchJson("/api/v1/workflows/{$workflow->id}/draft", [
            'definition' => $this->validDefinition(),
            'expected_draft_revision' => 1,
        ]);

        $draftResponse->assertOk()
            ->assertJsonPath('draft_revision', 2)
            ->assertJsonPath('saved', true)
            ->assertJsonPath('validation.is_publishable', true);

        $publishResponse = $this->actingAs($manager, 'api')->postJson("/api/v1/workflows/{$workflow->id}/publish");

        $publishResponse->assertCreated()
            ->assertJsonPath('published_version.version_number', 1)
            ->assertJsonPath('published_version.version_label', 'v1.0.0')
            ->assertJsonPath('workflow_status', WorkflowStatus::Disabled->value);

        $this->actingAs($manager, 'api')
            ->patchJson("/api/v1/workflows/{$workflow->id}/status", ['status' => WorkflowStatus::Active->value])
            ->assertOk()
            ->assertJsonPath('status', WorkflowStatus::Active->value);

        $triggerResponse = $this->actingAs($manager, 'api')
            ->postJson("/api/v1/workflows/{$workflow->id}/trigger/webhook", ['source' => 'test']);

        $triggerResponse->assertCreated()
            ->assertJsonPath('workflow_id', $workflow->id)
            ->assertJsonPath('version_number', 1)
            ->assertJsonPath('status', 'running');

        $workflow->refresh();

        $this->assertSame('v1.0.0', $workflow->current_version_label);
        $this->assertSame(1, (int) $workflow->total_runs);
        $this->assertSame(1, (int) $workflow->active_instances);
    }

    public function test_publish_version_labels_bump_for_structural_and_config_changes(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager-versioning');
        $team = $this->createTeam($tenant, $manager, 'Operations');
        $this->assignToTeam($tenant, $manager, $team);
        $workflow = $this->createWorkflow($tenant, $team, $manager);

        $draft = $this->validDefinition();

        $this->actingAs($manager, 'api')->patchJson("/api/v1/workflows/{$workflow->id}/draft", [
            'definition' => $draft,
            'expected_draft_revision' => 1,
        ])->assertOk();

        $this->actingAs($manager, 'api')->postJson("/api/v1/workflows/{$workflow->id}/publish")
            ->assertCreated()
            ->assertJsonPath('published_version.version_label', 'v1.0.0');

        $structuralDraft = $draft;
        $structuralDraft['nodes'][] = [
            'id' => 'send-reminder-email',
            'type' => 'send-email',
            'label' => 'Reminder Email',
            'is_entry_point' => false,
            'is_terminal' => true,
            'config' => [
                'from' => 'hr@example.test',
                'to' => 'employee@example.test',
                'subject' => 'Reminder',
                'body' => 'Reminder email body',
            ],
        ];
        $structuralDraft['edges'][] = [
            'id' => 'edge-reminder',
            'source_node_key' => 'send-welcome-email',
            'target_node_key' => 'send-reminder-email',
            'branch_type' => 'default',
        ];

        $this->actingAs($manager, 'api')->patchJson("/api/v1/workflows/{$workflow->id}/draft", [
            'definition' => $structuralDraft,
            'expected_draft_revision' => 2,
        ])->assertOk();

        $this->actingAs($manager, 'api')->postJson("/api/v1/workflows/{$workflow->id}/publish")
            ->assertCreated()
            ->assertJsonPath('published_version.version_label', 'v2.1.0');

        $configDraft = $structuralDraft;
        $configDraft['nodes'][0]['config']['subject'] = 'Welcome aboard';

        $this->actingAs($manager, 'api')->patchJson("/api/v1/workflows/{$workflow->id}/draft", [
            'definition' => $configDraft,
            'expected_draft_revision' => 3,
        ])->assertOk();

        $this->actingAs($manager, 'api')->postJson("/api/v1/workflows/{$workflow->id}/publish")
            ->assertCreated()
            ->assertJsonPath('published_version.version_label', 'v3.1.1');
    }

    public function test_validate_only_verifies_without_saving_draft(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager-validate-only');
        $team = $this->createTeam($tenant, $manager, 'Automation');
        $this->assignToTeam($tenant, $manager, $team);
        $workflow = $this->createWorkflow($tenant, $team, $manager);

        $this->actingAs($manager, 'api')->patchJson("/api/v1/workflows/{$workflow->id}/draft", [
            'definition' => $this->validDefinition(),
            'expected_draft_revision' => 1,
            'validate_only' => true,
        ])->assertOk()
            ->assertJsonPath('draft_revision', 1)
            ->assertJsonPath('saved', false)
            ->assertJsonPath('validation.is_publishable', true)
            ->assertJsonPath('validation.summary.errors', 0);

        $workflow->refresh();

        $this->assertSame(1, (int) $workflow->draft_revision);
        $this->assertSame([], $workflow->draft_definition['nodes']);
    }

    public function test_invalid_draft_can_be_saved_but_cannot_be_published(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager-invalid-draft');
        $team = $this->createTeam($tenant, $manager, 'Automation');
        $this->assignToTeam($tenant, $manager, $team);
        $workflow = $this->createWorkflow($tenant, $team, $manager);

        $invalidDefinition = $this->validDefinition();
        $invalidDefinition['nodes'][0]['type'] = 'missing-node-type';

        $this->actingAs($manager, 'api')->patchJson("/api/v1/workflows/{$workflow->id}/draft", [
            'definition' => $invalidDefinition,
            'expected_draft_revision' => 1,
        ])->assertOk()
            ->assertJsonPath('draft_revision', 2)
            ->assertJsonPath('saved', true)
            ->assertJsonPath('validation.is_publishable', false);

        $this->actingAs($manager, 'api')->postJson("/api/v1/workflows/{$workflow->id}/publish")->assertUnprocessable()
            ->assertJsonPath('errors.definition.0', "Workflow node type 'missing-node-type' is not active or does not exist.");

        $this->assertDatabaseCount('workflow_versions', 0);
    }

    public function test_publish_rejects_when_draft_has_no_changes(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager-noop-publish');
        $team = $this->createTeam($tenant, $manager, 'Support');
        $this->assignToTeam($tenant, $manager, $team);
        $workflow = $this->createPublishedWorkflow($tenant, $team, $manager);

        $this->actingAs($manager, 'api')
            ->postJson("/api/v1/workflows/{$workflow->id}/publish")
            ->assertUnprocessable()
            ->assertJsonPath('errors.definition.0', 'No changes detected since the current published version. Update the draft before publishing.');

        $this->assertDatabaseCount('workflow_versions', 1);
    }

    public function test_disabled_workflow_returns_gone_without_stopping_active_instances(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager-disable');
        $team = $this->createTeam($tenant, $manager, 'Support');
        $this->assignToTeam($tenant, $manager, $team);
        $workflow = $this->createPublishedWorkflow($tenant, $team, $manager);

        $this->actingAs($manager, 'api')
            ->patchJson("/api/v1/workflows/{$workflow->id}/status", ['status' => WorkflowStatus::Active->value])
            ->assertOk();

        $this->actingAs($manager, 'api')
            ->postJson("/api/v1/workflows/{$workflow->id}/trigger/webhook", ['source' => 'first'])
            ->assertCreated();

        $this->actingAs($manager, 'api')
            ->patchJson("/api/v1/workflows/{$workflow->id}/status", ['status' => WorkflowStatus::Disabled->value])
            ->assertOk();

        $this->actingAs($manager, 'api')
            ->postJson("/api/v1/workflows/{$workflow->id}/trigger/webhook", ['source' => 'blocked'])
            ->assertStatus(410);

        $workflow->refresh();

        $this->assertSame(1, (int) $workflow->total_runs);
        $this->assertSame(1, (int) $workflow->active_instances);
    }

    public function test_business_owner_can_purge_deleted_workflow_when_no_active_instances_exist(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->createUser($tenant, Role::BusinessOwner, 'owner-purge');
        $manager = $this->createUser($tenant, Role::Manager, 'manager-purge');
        $team = $this->createTeam($tenant, $manager, 'Finance');
        $this->assignToTeam($tenant, $manager, $team);
        $workflow = $this->createWorkflow($tenant, $team, $manager);

        $this->actingAs($manager, 'api')
            ->deleteJson("/api/v1/workflows/{$workflow->id}")
            ->assertOk()
            ->assertJsonPath('status', WorkflowStatus::Deleted->value);

        $this->actingAs($owner, 'api')
            ->deleteJson("/api/v1/workflows/{$workflow->id}/purge")
            ->assertOk()
            ->assertJsonPath('message', 'Workflow permanently deleted.');

        $this->assertDatabaseMissing('workflows', [
            'id' => $workflow->id,
        ]);
    }

    private function createTenant(): Tenant
    {
        return Tenant::query()->create([
            'business_name' => 'Acme Inc',
            'business_type' => BusinessType::SaaS->value,
        ]);
    }

    private function createUser(Tenant $tenant, Role $role, string $prefix): User
    {
        return User::query()->create([
            'first_name' => ucfirst($prefix),
            'last_name' => 'User',
            'name' => ucfirst($prefix).' User',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'password' => Hash::make('Pass1234!'),
            'tenant_id' => $tenant->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function createTeam(Tenant $tenant, User $manager, string $name): Team
    {
        return Team::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'manager_id' => $manager->id,
        ]);
    }

    private function assignToTeam(Tenant $tenant, User $user, Team $team): void
    {
        TeamMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'team_id' => $team->id,
            'status' => 'active',
        ]);
    }

    private function createWorkflow(Tenant $tenant, Team $team, User $manager): Workflow
    {
        return Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'created_by_id' => $manager->id,
            'name' => 'Employee Onboarding',
            'description' => 'Automated sequence for provisioning access',
            'status' => WorkflowStatus::Disabled,
            'draft_definition' => [
                'trigger' => null,
                'nodes' => [],
                'edges' => [],
                'settings' => [],
            ],
            'draft_revision' => 1,
        ]);
    }

    private function createPublishedWorkflow(Tenant $tenant, Team $team, User $manager): Workflow
    {
        $workflow = $this->createWorkflow($tenant, $team, $manager);

        $this->actingAs($manager, 'api')->patchJson("/api/v1/workflows/{$workflow->id}/draft", [
            'definition' => $this->validDefinition(),
            'expected_draft_revision' => 1,
        ])->assertOk();

        $this->actingAs($manager, 'api')->postJson("/api/v1/workflows/{$workflow->id}/publish")->assertCreated();

        return $workflow->refresh();
    }

    private function validDefinition(): array
    {
        return [
            'trigger' => [
                'type' => 'manual-trigger',
                'config' => [
                    'variables' => [],
                ],
            ],
            'nodes' => [
                [
                    'id' => 'start',
                    'type' => 'manual-trigger',
                    'is_entry_point' => true,
                    'config' => [
                        'variables' => [],
                    ],
                ],
                [
                    'id' => 'send-welcome-email',
                    'type' => 'send-email',
                    'is_terminal' => true,
                    'config' => [
                        'from' => 'hr@example.test',
                        'to' => 'employee@example.test',
                        'subject' => 'Welcome',
                        'body' => 'Hello and welcome',
                    ],
                ],
            ],
            'edges' => [
                [
                    'id' => 'edge-start-email',
                    'source_node_key' => 'start',
                    'target_node_key' => 'send-welcome-email',
                    'branch_type' => 'default',
                ],
            ],
            'settings' => [],
        ];
    }
}
