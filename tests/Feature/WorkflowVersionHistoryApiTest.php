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
use Modules\Workflows\Models\WorkflowVersion;
use Tests\TestCase;

class WorkflowVersionHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NodeDefinitionSeeder::class);
    }

    // ─── List Versions ────────────────────────────────────────────────

    public function test_manager_can_list_workflow_versions(): void
    {
        [$tenant, $manager, $team, $workflow] = $this->scaffoldPublishedWorkflow();

        // Publish a second version with a config change.
        $workflow->refresh();
        $definition = $this->validDefinitionV2();
        $this->actingAs($manager, 'api')->patchJson("/api/v1/workflows/{$workflow->id}/draft", [
            'definition' => $definition,
            'expected_draft_revision' => $workflow->draft_revision,
        ])->assertOk();

        $this->actingAs($manager, 'api')
            ->postJson("/api/v1/workflows/{$workflow->id}/publish")
            ->assertCreated();

        $response = $this->actingAs($manager, 'api')
            ->getJson("/api/v1/workflows/{$workflow->id}/versions");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.version_number', 2)
            ->assertJsonPath('data.1.version_number', 1);
    }

    // ─── Show Single Version ──────────────────────────────────────────

    public function test_manager_can_view_single_version_with_definition(): void
    {
        [$tenant, $manager, $team, $workflow] = $this->scaffoldPublishedWorkflow();

        $versionId = $workflow->current_version_id;

        $response = $this->actingAs($manager, 'api')
            ->getJson("/api/v1/workflows/{$workflow->id}/versions/{$versionId}");

        $response->assertOk()
            ->assertJsonPath('data.id', $versionId)
            ->assertJsonPath('data.version_number', 1)
            ->assertJsonStructure([
                'data' => ['id', 'version_number', 'version_label', 'definition', 'is_current', 'published_by'],
            ])
            ->assertJsonPath('data.is_current', true);

        $this->assertNotNull($response->json('data.definition'));
    }

    // ─── Rollback ─────────────────────────────────────────────────────

    public function test_employee_cannot_rollback(): void
    {
        [$tenant, $manager, $team, $workflow] = $this->scaffoldTwoVersions();
        $employee = $this->createUser($tenant, Role::Employee, 'emp');
        $this->assignToTeam($tenant, $employee, $team);

        $firstVersionId = $workflow->versions()->orderBy('version_number')->first()->id;

        $response = $this->actingAs($employee, 'api')
            ->postJson("/api/v1/workflows/{$workflow->id}/versions/{$firstVersionId}/rollback");

        $response->assertForbidden();
    }

    public function test_manager_can_rollback_to_previous_version(): void
    {
        [$tenant, $manager, $team, $workflow] = $this->scaffoldTwoVersions();

        $firstVersion = $workflow->versions()->orderBy('version_number')->first();

        $response = $this->actingAs($manager, 'api')
            ->postJson("/api/v1/workflows/{$workflow->id}/versions/{$firstVersion->id}/rollback");

        $response->assertCreated()
            ->assertJsonPath('workflow_status', WorkflowStatus::Active->value)
            ->assertJsonStructure([
                'workflow_id',
                'rolled_back_version' => ['id', 'version_number', 'version_label', 'rollback_source_version_id'],
                'workflow_status',
            ]);

        $newVersionId = $response->json('rolled_back_version.id');
        $this->assertNotEquals($firstVersion->id, $newVersionId);
        $this->assertEquals($firstVersion->id, $response->json('rolled_back_version.rollback_source_version_id'));

        $workflow->refresh();
        $this->assertEquals($newVersionId, $workflow->current_version_id);
        $this->assertEquals(3, $workflow->current_version_number);
        $this->assertEquals(WorkflowStatus::Active, $workflow->status);
    }

    public function test_rollback_preserves_version_history(): void
    {
        [$tenant, $manager, $team, $workflow] = $this->scaffoldTwoVersions();

        $firstVersion = $workflow->versions()->orderBy('version_number')->first();

        $this->actingAs($manager, 'api')
            ->postJson("/api/v1/workflows/{$workflow->id}/versions/{$firstVersion->id}/rollback")
            ->assertCreated();

        // All 3 versions should exist: original, second, and rollback.
        $this->assertEquals(3, $workflow->versions()->count());

        $rollbackVersion = $workflow->versions()->orderByDesc('version_number')->first();
        $this->assertEquals($firstVersion->id, $rollbackVersion->rollback_source_version_id);
        $this->assertStringContainsString('Rollback', $rollbackVersion->release_note);
    }

    public function test_rollback_rejects_already_current_version(): void
    {
        [$tenant, $manager, $team, $workflow] = $this->scaffoldPublishedWorkflow();

        $currentVersionId = $workflow->current_version_id;

        $response = $this->actingAs($manager, 'api')
            ->postJson("/api/v1/workflows/{$workflow->id}/versions/{$currentVersionId}/rollback");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('version');
    }

    public function test_rollback_rejects_deleted_workflow(): void
    {
        [$tenant, $manager, $team, $workflow] = $this->scaffoldTwoVersions();

        $firstVersion = $workflow->versions()->orderBy('version_number')->first();

        // Soft-delete the workflow.
        $this->actingAs($manager, 'api')
            ->deleteJson("/api/v1/workflows/{$workflow->id}")
            ->assertOk();

        $response = $this->actingAs($manager, 'api')
            ->postJson("/api/v1/workflows/{$workflow->id}/versions/{$firstVersion->id}/rollback");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('workflow');
    }

    public function test_business_owner_can_rollback_any_team_workflow(): void
    {
        [$tenant, $manager, $team, $workflow] = $this->scaffoldTwoVersions();
        $owner = $this->createUser($tenant, Role::BusinessOwner, 'owner');

        $firstVersion = $workflow->versions()->orderBy('version_number')->first();

        $response = $this->actingAs($owner, 'api')
            ->postJson("/api/v1/workflows/{$workflow->id}/versions/{$firstVersion->id}/rollback");

        $response->assertCreated();
    }

    // ─── Compare Versions ─────────────────────────────────────────────

    public function test_compare_versions_returns_diff(): void
    {
        [$tenant, $manager, $team, $workflow] = $this->scaffoldTwoVersions();

        $versions = $workflow->versions()->orderBy('version_number')->get();
        $v1 = $versions[0];
        $v2 = $versions[1];

        $response = $this->actingAs($manager, 'api')
            ->getJson("/api/v1/workflows/{$workflow->id}/versions/compare?from_version_id={$v1->id}&to_version_id={$v2->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'from_version' => ['id', 'version_number', 'version_label'],
                    'to_version' => ['id', 'version_number', 'version_label'],
                    'nodes_added',
                    'nodes_removed',
                    'nodes_modified',
                    'edges_added',
                    'edges_removed',
                    'edges_modified',
                    'trigger_changed',
                    'settings_changed',
                ],
            ]);

        // V2 added a notification node, so nodes_added should contain it.
        $this->assertContains('send-notification', $response->json('data.nodes_added'));
    }

    public function test_rollback_with_custom_release_note(): void
    {
        [$tenant, $manager, $team, $workflow] = $this->scaffoldTwoVersions();

        $firstVersion = $workflow->versions()->orderBy('version_number')->first();

        $response = $this->actingAs($manager, 'api')
            ->postJson("/api/v1/workflows/{$workflow->id}/versions/{$firstVersion->id}/rollback", [
                'release_note' => 'Reverting due to email config issue',
            ]);

        $response->assertCreated();

        $rollbackVersion = $workflow->versions()->orderByDesc('version_number')->first();
        $this->assertEquals('Reverting due to email config issue', $rollbackVersion->release_note);
    }

    // ─── Helpers ──────────────────────────────────────────────────────

    /**
     * Creates a tenant, manager, team, and a published workflow (1 version).
     */
    private function scaffoldPublishedWorkflow(): array
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'mgr');
        $team = $this->createTeam($tenant, $manager, 'Engineering');
        $this->assignToTeam($tenant, $manager, $team);

        $workflow = $this->createWorkflow($tenant, $team, $manager);

        $this->actingAs($manager, 'api')->patchJson("/api/v1/workflows/{$workflow->id}/draft", [
            'definition' => $this->validDefinition(),
            'expected_draft_revision' => 1,
        ])->assertOk();

        $this->actingAs($manager, 'api')
            ->postJson("/api/v1/workflows/{$workflow->id}/publish")
            ->assertCreated();

        return [$tenant, $manager, $team, $workflow->refresh()];
    }

    /**
     * Creates a published workflow then publishes a second version with a node added.
     */
    private function scaffoldTwoVersions(): array
    {
        [$tenant, $manager, $team, $workflow] = $this->scaffoldPublishedWorkflow();

        $this->actingAs($manager, 'api')->patchJson("/api/v1/workflows/{$workflow->id}/draft", [
            'definition' => $this->validDefinitionV2(),
            'expected_draft_revision' => $workflow->draft_revision,
        ])->assertOk();

        $this->actingAs($manager, 'api')
            ->postJson("/api/v1/workflows/{$workflow->id}/publish")
            ->assertCreated();

        return [$tenant, $manager, $team, $workflow->refresh()];
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
            'name' => 'Version Test Workflow',
            'description' => 'Workflow for testing version history',
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

    /**
     * V1 definition: manual trigger → send-email (terminal).
     */
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

    /**
     * V2 definition: manual trigger → send-email → send-notification (terminal).
     * Adds a new node and a new edge compared to V1.
     */
    private function validDefinitionV2(): array
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
                    'config' => [
                        'from' => 'hr@example.test',
                        'to' => 'employee@example.test',
                        'subject' => 'Welcome',
                        'body' => 'Hello and welcome',
                    ],
                ],
                [
                    'id' => 'send-notification',
                    'type' => 'send-email',
                    'is_terminal' => true,
                    'config' => [
                        'from' => 'system@example.test',
                        'to' => 'manager@example.test',
                        'subject' => 'Onboarding complete',
                        'body' => 'Employee onboarding finished',
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
                [
                    'id' => 'edge-email-notification',
                    'source_node_key' => 'send-welcome-email',
                    'target_node_key' => 'send-notification',
                    'branch_type' => 'default',
                ],
            ],
            'settings' => [],
        ];
    }
}
