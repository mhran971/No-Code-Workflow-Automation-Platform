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
use Modules\Workflows\Enums\TriggerType;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowVersion;
use Tests\TestCase;

class WorkflowInstancesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_instances_endpoint_filters_by_status_and_date_ranges(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager-instances');
        $team = $this->createTeam($tenant, $manager, 'Operations');
        $this->assignToTeam($tenant, $manager, $team);

        $workflow = $this->createWorkflow($tenant, $team, $manager);
        $version = $this->createWorkflowVersion($tenant, $workflow, $manager);
        /** @var Authenticatable $managerAuth */
        $managerAuth = $manager;

        $runningInstance = $this->createInstance(
            $tenant,
            $workflow,
            $version,
            WorkflowInstanceStatus::Running,
            '2026-07-01 08:00:00',
            null,
        );

        $failedInstance = $this->createInstance(
            $tenant,
            $workflow,
            $version,
            WorkflowInstanceStatus::Failed,
            '2026-07-03 09:00:00',
            '2026-07-03 10:00:00',
        );

        $completedInstance = $this->createInstance(
            $tenant,
            $workflow,
            $version,
            WorkflowInstanceStatus::Completed,
            '2026-07-05 11:00:00',
            '2026-07-06 12:00:00',
        );

        $this->actingAs($managerAuth, 'api')
            ->getJson("/api/v1/workflows/{$workflow->id}/instances?status=failed")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $failedInstance->id)
            ->assertJsonPath('data.0.status', WorkflowInstanceStatus::Failed->value);

        $this->actingAs($managerAuth, 'api')
            ->getJson("/api/v1/workflows/{$workflow->id}/instances?started_from=2026-07-02&started_to=2026-07-04")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $failedInstance->id);

        $this->actingAs($managerAuth, 'api')
            ->getJson("/api/v1/workflows/{$workflow->id}/instances?finished_from=2026-07-06&finished_to=2026-07-06")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $completedInstance->id);

        $this->assertDatabaseHas('workflow_instances', [
            'id' => $runningInstance->id,
            'workflow_id' => $workflow->id,
            'status' => WorkflowInstanceStatus::Running->value,
        ]);
    }

    public function test_instances_endpoint_rejects_invalid_status_filters(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager-invalid-status');
        $team = $this->createTeam($tenant, $manager, 'Operations');
        $this->assignToTeam($tenant, $manager, $team);

        $workflow = $this->createWorkflow($tenant, $team, $manager);
        /** @var Authenticatable $managerAuth */
        $managerAuth = $manager;

        $this->actingAs($managerAuth, 'api')
            ->getJson("/api/v1/workflows/{$workflow->id}/instances?status=bogus")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_employee_on_team_can_list_instances(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager-employee-view');
        $employee = $this->createUser($tenant, Role::Employee, 'employee-view');
        $team = $this->createTeam($tenant, $manager, 'Operations');
        $this->assignToTeam($tenant, $manager, $team);
        $this->assignToTeam($tenant, $employee, $team);

        $workflow = $this->createWorkflow($tenant, $team, $manager);
        $version = $this->createWorkflowVersion($tenant, $workflow, $manager);
        /** @var Authenticatable $employeeAuth */
        $employeeAuth = $employee;

        $instance = $this->createInstance(
            $tenant,
            $workflow,
            $version,
            WorkflowInstanceStatus::Waiting,
            '2026-07-04 08:00:00',
            null,
        );

        $this->actingAs($employeeAuth, 'api')
            ->getJson("/api/v1/workflows/{$workflow->id}/instances")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $instance->id);
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
            'name' => 'Instance Filters Workflow',
            'description' => 'Workflow used for instance filter API tests',
            'status' => WorkflowStatus::Active->value,
            'draft_definition' => [
                'trigger' => null,
                'nodes' => [],
                'edges' => [],
                'settings' => [],
            ],
            'draft_revision' => 1,
        ]);
    }

    private function createWorkflowVersion(Tenant $tenant, Workflow $workflow, User $manager): WorkflowVersion
    {
        return WorkflowVersion::query()->create([
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
            'published_by_id' => $manager->id,
            'published_at' => Carbon::parse('2026-07-01 00:00:00', 'UTC'),
        ]);
    }

    private function createInstance(
        Tenant $tenant,
        Workflow $workflow,
        WorkflowVersion $version,
        WorkflowInstanceStatus $status,
        string $startedAt,
        ?string $finishedAt,
    ): WorkflowInstance {
        return WorkflowInstance::query()->create([
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'parent_instance_id' => null,
            'tenant_id' => $tenant->id,
            'status' => $status->value,
            'trigger_type' => TriggerType::Manual->value,
            'correlation_id' => null,
            'payload' => [],
            'context' => [],
            'error' => null,
            'paused_reason' => null,
            'started_at' => Carbon::parse($startedAt, 'UTC'),
            'finished_at' => $finishedAt ? Carbon::parse($finishedAt, 'UTC') : null,
        ]);
    }
}
