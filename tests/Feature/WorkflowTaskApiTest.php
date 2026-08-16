<?php

namespace Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;
use Modules\Team\Models\Team;
use Modules\Team\Models\TeamMembership;
use Modules\Workflows\Enums\NodeExecutionStatus;
use Modules\Workflows\Enums\TriggerType;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Models\WorkflowTask;
use Modules\Workflows\Models\WorkflowVersion;
use Tests\TestCase;

class WorkflowTaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_response_is_saved_and_returned_when_viewing_task(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager-draft');
        $team = $this->createTeam($tenant, $manager, 'Operations');
        $this->assignToTeam($tenant, $manager, $team);

        $task = $this->createTask($tenant, $team, $manager);
        /** @var Authenticatable $managerAuth */
        $managerAuth = $manager;

        $this->actingAs($managerAuth, 'api')
            ->patchJson("/api/v1/workflows/tasks/{$task->id}/draft", [
                'response' => ['decision' => 'approve'],
            ])
            ->assertOk();

        $this->actingAs($managerAuth, 'api')
            ->getJson("/api/v1/workflows/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.draft_response.decision', 'approve')
            ->assertJsonPath('data.response', null);

        $this->assertDatabaseHas('workflow_tasks', [
            'id' => $task->id,
            'status' => 'open',
        ]);
    }

    public function test_empty_draft_response_can_be_saved(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager-empty-draft');
        $team = $this->createTeam($tenant, $manager, 'Operations');
        $this->assignToTeam($tenant, $manager, $team);

        $task = $this->createTask($tenant, $team, $manager);
        /** @var Authenticatable $managerAuth */
        $managerAuth = $manager;

        $this->actingAs($managerAuth, 'api')
            ->patchJson("/api/v1/workflows/tasks/{$task->id}/draft", [
                'response' => [],
            ])
            ->assertOk();

        $this->actingAs($managerAuth, 'api')
            ->patchJson("/api/v1/workflows/tasks/{$task->id}/draft", [])
            ->assertOk();
    }

    public function test_submitted_response_is_saved_and_returned_when_viewing_task(): void
    {
        Queue::fake();

        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager-submit');
        $team = $this->createTeam($tenant, $manager, 'Operations');
        $this->assignToTeam($tenant, $manager, $team);

        $task = $this->createTask($tenant, $team, $manager);
        /** @var Authenticatable $managerAuth */
        $managerAuth = $manager;

        $this->actingAs($managerAuth, 'api')
            ->postJson("/api/v1/workflows/tasks/{$task->id}/submit", [
                'response' => ['decision' => 'approve', 'comments' => 'Looks good.'],
            ])
            ->assertOk();

        $this->assertDatabaseHas('workflow_tasks', [
            'id' => $task->id,
            'status' => 'completed',
            'completed_by_id' => $manager->id,
        ]);

        $this->actingAs($managerAuth, 'api')
            ->getJson("/api/v1/workflows/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.response.decision', 'approve')
            ->assertJsonPath('data.response.comments', 'Looks good.')
            ->assertJsonPath('data.completed_by.id', $manager->id);
    }

    public function test_cancelling_instance_cancels_its_open_tasks(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager-cancel');
        $team = $this->createTeam($tenant, $manager, 'Operations');
        $this->assignToTeam($tenant, $manager, $team);

        $task = $this->createTask($tenant, $team, $manager);
        /** @var Authenticatable $managerAuth */
        $managerAuth = $manager;

        $this->actingAs($managerAuth, 'api')
            ->postJson("/api/v1/workflows/instances/{$task->instance_id}/cancel")
            ->assertOk();

        $this->assertDatabaseHas('workflow_instances', [
            'id' => $task->instance_id,
            'status' => WorkflowInstanceStatus::Cancelled->value,
        ]);

        $this->assertDatabaseHas('workflow_tasks', [
            'id' => $task->id,
            'status' => 'cancelled',
        ]);

        $this->actingAs($managerAuth, 'api')
            ->getJson("/api/v1/workflows/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cancelling_instance_does_not_reopen_already_completed_tasks(): void
    {
        Queue::fake();

        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager-cancel-completed');
        $team = $this->createTeam($tenant, $manager, 'Operations');
        $this->assignToTeam($tenant, $manager, $team);

        $task = $this->createTask($tenant, $team, $manager);
        /** @var Authenticatable $managerAuth */
        $managerAuth = $manager;

        $this->actingAs($managerAuth, 'api')
            ->postJson("/api/v1/workflows/tasks/{$task->id}/submit", [
                'response' => ['decision' => 'approve'],
            ])
            ->assertOk();

        $this->actingAs($managerAuth, 'api')
            ->postJson("/api/v1/workflows/instances/{$task->instance_id}/cancel")
            ->assertOk();

        $this->assertDatabaseHas('workflow_tasks', [
            'id' => $task->id,
            'status' => 'completed',
        ]);
    }

    public function test_escalated_task_is_returned_by_escalated_filter(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager-escalated');
        $team = $this->createTeam($tenant, $manager, 'Operations');
        $this->assignToTeam($tenant, $manager, $team);

        $escalatedTask = $this->createTask($tenant, $team, $manager, '2000-01-01 00:00:00');
        $escalatedTask->update(['status' => 'escalated', 'escalated_at' => now()]);
        $onTimeTask = $this->createTask($tenant, $team, $manager, '2999-01-01 00:00:00');
        /** @var Authenticatable $managerAuth */
        $managerAuth = $manager;

        $this->actingAs($managerAuth, 'api')
            ->getJson("/api/v1/workflows/tasks/{$escalatedTask->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'escalated');

        $this->actingAs($managerAuth, 'api')
            ->getJson("/api/v1/workflows/tasks/{$onTimeTask->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'open');

        $this->assertDatabaseHas('workflow_tasks', [
            'id' => $escalatedTask->id,
            'status' => 'escalated',
        ]);

        $this->actingAs($managerAuth, 'api')
            ->getJson('/api/v1/workflows/tasks?status=escalated')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $escalatedTask->id);

        $this->actingAs($managerAuth, 'api')
            ->getJson('/api/v1/workflows/tasks?status=open')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $onTimeTask->id);
    }

    public function test_cancelled_and_completed_tasks_ignore_due_date_when_displaying_status(): void
    {
        Queue::fake();

        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager-precedence');
        $team = $this->createTeam($tenant, $manager, 'Operations');
        $this->assignToTeam($tenant, $manager, $team);

        $completedTask = $this->createTask($tenant, $team, $manager, '2000-01-01 00:00:00');
        $cancelledTask = $this->createTask($tenant, $team, $manager, '2000-01-01 00:00:00');
        /** @var Authenticatable $managerAuth */
        $managerAuth = $manager;

        $this->actingAs($managerAuth, 'api')
            ->postJson("/api/v1/workflows/tasks/{$completedTask->id}/submit", [
                'response' => ['decision' => 'approve'],
            ])
            ->assertOk();

        $this->actingAs($managerAuth, 'api')
            ->postJson("/api/v1/workflows/instances/{$cancelledTask->instance_id}/cancel")
            ->assertOk();

        $this->actingAs($managerAuth, 'api')
            ->getJson("/api/v1/workflows/tasks/{$completedTask->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->actingAs($managerAuth, 'api')
            ->getJson("/api/v1/workflows/tasks/{$cancelledTask->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
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

    private function createTask(Tenant $tenant, Team $team, User $assignee, ?string $dueAt = null): WorkflowTask
    {
        $workflow = Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'created_by_id' => $assignee->id,
            'name' => 'Approval Workflow',
            'description' => 'Workflow used for task API tests',
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
            'published_by_id' => $assignee->id,
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
            'paused_reason' => null,
            'started_at' => now(),
            'finished_at' => null,
        ]);

        $execution = WorkflowNodeExecution::query()->create([
            'instance_id' => $instance->id,
            'tenant_id' => $tenant->id,
            'node_key' => 'task_1',
            'node_type' => 'task-node',
            'status' => NodeExecutionStatus::Waiting->value,
            'attempt' => 1,
            'idempotency_key' => 'task_1-'.uniqid(),
            'input' => [],
        ]);

        return WorkflowTask::query()->create([
            'instance_id' => $instance->id,
            'execution_id' => $execution->id,
            'tenant_id' => $tenant->id,
            'node_key' => 'task_1',
            'assignee_id' => $assignee->id,
            'title' => 'Review Proposal',
            'description' => 'Check the Q3 proposal and approve or reject.',
            'input_schema' => [
                ['key' => 'decision', 'label' => 'Decision', 'type' => 'select', 'required' => true, 'options' => ['approve', 'reject']],
                ['key' => 'comments', 'label' => 'Comments', 'type' => 'textarea', 'required' => false],
            ],
            'status' => 'open',
            'due_at' => $dueAt ? Carbon::parse($dueAt, 'UTC') : null,
        ]);
    }
}
