<?php

namespace Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;
use Modules\Team\Models\Team;
use Modules\Team\Models\TeamMembership;
use Modules\Workflows\Enums\DynamicFlowStatus;
use Modules\Workflows\Enums\NodeExecutionStatus;
use Modules\Workflows\Enums\TriggerType;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowDynamicFlow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Models\WorkflowVersion;
use Tests\TestCase;

class DynamicFlowPendingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sees_instances_awaiting_dynamic_flow_design_in_their_team(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager-own-team');
        $team = $this->createTeam($tenant, $manager, 'Operations');
        $creator = $this->createUser($tenant, Role::Employee, 'creator');
        $this->assignToTeam($tenant, $creator, $team);

        $instance = $this->createInstanceAwaitingDynamicFlow($tenant, $team, $creator);

        /** @var Authenticatable $managerAuth */
        $managerAuth = $manager;

        $this->actingAs($managerAuth, 'api')
            ->getJson('/api/v1/workflows/dynamic-flows/pending')
            ->assertOk()
            ->assertJsonPath('data.0.id', $instance->id)
            ->assertJsonPath('data.0.dynamic_flows.0.status', DynamicFlowStatus::AwaitingDesign->value);
    }

    public function test_manager_does_not_see_instances_from_a_different_team_even_if_they_created_the_workflow(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager-outsider');
        $this->createTeam($tenant, $manager, 'Operations');

        $otherTeam = $this->createTeam($tenant, $this->createUser($tenant, Role::Manager, 'other-manager'), 'Sales');
        // The manager under test created this workflow themselves, but it belongs to a different team.
        $this->createInstanceAwaitingDynamicFlow($tenant, $otherTeam, $manager);

        /** @var Authenticatable $managerAuth */
        $managerAuth = $manager;

        $this->actingAs($managerAuth, 'api')
            ->getJson('/api/v1/workflows/dynamic-flows/pending')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_instances_with_a_dynamic_flow_no_longer_awaiting_design_are_excluded(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager-executing');
        $team = $this->createTeam($tenant, $manager, 'Operations');
        $creator = $this->createUser($tenant, Role::Employee, 'creator-executing');
        $this->assignToTeam($tenant, $creator, $team);

        $this->createInstanceAwaitingDynamicFlow($tenant, $team, $creator, DynamicFlowStatus::Executing);

        /** @var Authenticatable $managerAuth */
        $managerAuth = $manager;

        $this->actingAs($managerAuth, 'api')
            ->getJson('/api/v1/workflows/dynamic-flows/pending')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_non_manager_roles_are_forbidden(): void
    {
        $tenant = $this->createTenant();
        $employee = $this->createUser($tenant, Role::Employee, 'employee');
        $businessOwner = $this->createUser($tenant, Role::BusinessOwner, 'owner');

        /** @var Authenticatable $employeeAuth */
        $employeeAuth = $employee;
        /** @var Authenticatable $ownerAuth */
        $ownerAuth = $businessOwner;

        $this->actingAs($employeeAuth, 'api')
            ->getJson('/api/v1/workflows/dynamic-flows/pending')
            ->assertForbidden();

        $this->actingAs($ownerAuth, 'api')
            ->getJson('/api/v1/workflows/dynamic-flows/pending')
            ->assertForbidden();
    }

    public function test_manager_with_no_team_gets_an_empty_result_not_an_error(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager-no-team');

        /** @var Authenticatable $managerAuth */
        $managerAuth = $manager;

        $this->actingAs($managerAuth, 'api')
            ->getJson('/api/v1/workflows/dynamic-flows/pending')
            ->assertOk()
            ->assertJsonCount(0, 'data');
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

    private function createInstanceAwaitingDynamicFlow(
        Tenant $tenant,
        Team $team,
        User $creator,
        DynamicFlowStatus $status = DynamicFlowStatus::AwaitingDesign,
    ): WorkflowInstance {
        $workflow = Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'created_by_id' => $creator->id,
            'name' => 'Dynamic Flow Workflow',
            'description' => 'Workflow used for dynamic-flow pending API tests',
            'status' => WorkflowStatus::Active->value,
            'draft_definition' => [
                'trigger' => null,
                'nodes' => [],
                'edges' => [],
                'settings' => [],
            ],
            'draft_revision' => 1,
        ]);

        $version = WorkflowVersion::query()->create([
            'workflow_id' => $workflow->id,
            'tenant_id' => $tenant->id,
            'version_number' => 1,
            'version_label' => 'v1.0.0',
            'definition' => [
                'trigger' => null,
                'nodes' => [],
                'edges' => [],
                'settings' => [],
            ],
            'release_note' => null,
            'published_by_id' => $creator->id,
            'published_at' => Carbon::parse('2026-07-01 00:00:00', 'UTC'),
        ]);

        $instance = WorkflowInstance::query()->create([
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'parent_instance_id' => null,
            'tenant_id' => $tenant->id,
            'status' => WorkflowInstanceStatus::Waiting->value,
            'trigger_type' => TriggerType::Manual->value,
            'correlation_id' => null,
            'payload' => [],
            'context' => [],
            'error' => null,
            'paused_reason' => 'awaiting_dynamic_flow_design',
            'started_at' => now(),
            'finished_at' => null,
        ]);

        $execution = WorkflowNodeExecution::query()->create([
            'instance_id' => $instance->id,
            'tenant_id' => $tenant->id,
            'node_key' => 'dynamic_flow_1',
            'node_type' => 'dynamic-flow',
            'status' => NodeExecutionStatus::Waiting->value,
            'attempt' => 1,
            'idempotency_key' => 'dynamic_flow_1-'.uniqid(),
            'input' => [],
        ]);

        WorkflowDynamicFlow::query()->create([
            'tenant_id' => $tenant->id,
            'instance_id' => $instance->id,
            'execution_id' => $execution->id,
            'node_key' => 'dynamic_flow_1',
            'status' => $status->value,
        ]);

        return $instance;
    }
}
