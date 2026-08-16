<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
use Tests\TestCase;

class ManagerReportsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_fetch_team_performance_report(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager1');
        $employee1 = $this->createUser($tenant, Role::Employee, 'employee1');
        $employee2 = $this->createUser($tenant, Role::Employee, 'employee2');
        $team = $this->createTeam($tenant, $manager, 'Engineering');
        $this->assignToTeam($tenant, $manager, $team);
        $this->assignToTeam($tenant, $employee1, $team);
        $this->assignToTeam($tenant, $employee2, $team);

        $workflow = $this->createWorkflow($tenant, $team, $manager, 'Bug Triage');
        $instance = $this->createInstance($tenant, $workflow, WorkflowInstanceStatus::Completed);
        $execution = $this->createExecution($tenant, $instance, 'task_review', 'task-node', NodeExecutionStatus::Succeeded);

        // Create completed task for employee1
        $this->createTask($tenant, $instance, $execution, $employee1, 'completed', Carbon::now()->subDays(5), Carbon::now()->subDays(3), Carbon::now()->subDays(4));

        // Create overdue open task for employee2
        $this->createTask($tenant, $instance, $execution, $employee2, 'open', Carbon::now()->subDays(10), Carbon::now()->subDays(2), null);

        $response = $this->actingAs($manager, 'api')->getJson('/api/v1/workflows/reports/team-performance');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('team.id', $team->id)
            ->assertJsonPath('team.name', 'Engineering')
            ->assertJsonPath('summary.total_members', 3)
            ->assertJsonPath('summary.total_tasks_assigned', 2)
            ->assertJsonPath('summary.total_tasks_completed', 1)
            ->assertJsonPath('summary.total_tasks_open', 1)
            ->assertJsonPath('summary.total_tasks_overdue', 1)
            ->assertJsonPath('summary.completion_rate', 50)
            ->assertJsonPath('summary.overdue_rate', 50)
            ->assertJsonStructure([
                'status',
                'team' => ['id', 'name', 'manager'],
                'period' => ['from', 'to'],
                'summary' => [
                    'total_members',
                    'total_tasks_assigned',
                    'total_tasks_completed',
                    'total_tasks_open',
                    'total_tasks_overdue',
                    'completion_rate',
                    'overdue_rate',
                    'avg_turnaround_hours',
                ],
                'charts' => [
                    'status_distribution',
                    'completion_trend',
                    'member_ranking',
                ],
                'members',
            ]);
    }

    public function test_manager_can_filter_team_performance_by_member_and_date(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager1');
        $employee1 = $this->createUser($tenant, Role::Employee, 'employee1');
        $employee2 = $this->createUser($tenant, Role::Employee, 'employee2');
        $team = $this->createTeam($tenant, $manager, 'Engineering');
        $this->assignToTeam($tenant, $manager, $team);
        $this->assignToTeam($tenant, $employee1, $team);
        $this->assignToTeam($tenant, $employee2, $team);

        $workflow = $this->createWorkflow($tenant, $team, $manager, 'Bug Triage');
        $instance = $this->createInstance($tenant, $workflow, WorkflowInstanceStatus::Completed);
        $execution = $this->createExecution($tenant, $instance, 'task_review', 'task-node', NodeExecutionStatus::Succeeded);

        $this->createTask($tenant, $instance, $execution, $employee1, 'completed', Carbon::now()->subDays(5), Carbon::now()->subDays(3), Carbon::now()->subDays(4));
        $this->createTask($tenant, $instance, $execution, $employee2, 'open', Carbon::now()->subDays(10), Carbon::now()->subDays(2), null);

        $response = $this->actingAs($manager, 'api')->getJson('/api/v1/workflows/reports/team-performance?' . http_build_query([
            'member_id' => $employee1->id,
            'date_from' => Carbon::now()->subDays(15)->toDateString(),
            'date_to' => Carbon::now()->toDateString(),
        ]));

        $response->assertOk()
            ->assertJsonPath('summary.total_tasks_assigned', 1)
            ->assertJsonPath('summary.total_tasks_completed', 1)
            ->assertJsonPath('summary.completion_rate', 100)
            ->assertJsonCount(1, 'members')
            ->assertJsonPath('members.0.user_id', $employee1->id);
    }

    public function test_manager_can_export_team_performance_as_csv_and_pdf(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager1');
        $team = $this->createTeam($tenant, $manager, 'Engineering');
        $this->assignToTeam($tenant, $manager, $team);

        $csvResponse = $this->actingAs($manager, 'api')->get('/api/v1/workflows/reports/team-performance/export?format=csv');
        $csvResponse->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $pdfResponse = $this->actingAs($manager, 'api')->get('/api/v1/workflows/reports/team-performance/export?format=pdf');
        $pdfResponse->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_manager_can_fetch_workflow_analytics_report(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager1');
        $team = $this->createTeam($tenant, $manager, 'Engineering');
        $this->assignToTeam($tenant, $manager, $team);

        $workflow = $this->createWorkflow($tenant, $team, $manager, 'Deployment Pipeline');

        $inst1 = $this->createInstance($tenant, $workflow, WorkflowInstanceStatus::Completed, Carbon::now()->subHours(2), Carbon::now()->subHours(1));
        $this->createExecution($tenant, $inst1, 'build', 'http-request', NodeExecutionStatus::Succeeded, Carbon::now()->subHours(2), Carbon::now()->subHours(2)->addMinutes(10));
        $this->createExecution($tenant, $inst1, 'deploy', 'ai-prompt', NodeExecutionStatus::Succeeded, Carbon::now()->subHours(2)->addMinutes(10), Carbon::now()->subHours(1));

        $inst2 = $this->createInstance($tenant, $workflow, WorkflowInstanceStatus::Failed, Carbon::now()->subHours(4), Carbon::now()->subHours(3));
        $this->createExecution($tenant, $inst2, 'build', 'http-request', NodeExecutionStatus::Failed, Carbon::now()->subHours(4), Carbon::now()->subHours(3));

        $response = $this->actingAs($manager, 'api')->getJson('/api/v1/workflows/reports/workflow-analytics');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('summary.total_workflows', 1)
            ->assertJsonPath('summary.total_executions', 2)
            ->assertJsonPath('summary.successful_executions', 1)
            ->assertJsonPath('summary.failed_executions', 1)
            ->assertJsonPath('summary.success_rate', 50)
            ->assertJsonPath('summary.failure_rate', 50)
            ->assertJsonStructure([
                'status',
                'team',
                'period',
                'summary' => [
                    'total_workflows',
                    'total_executions',
                    'successful_executions',
                    'failed_executions',
                    'running_executions',
                    'success_rate',
                    'failure_rate',
                    'avg_duration_seconds',
                    'avg_duration_formatted',
                ],
                'charts' => [
                    'status_breakdown',
                    'execution_trend',
                    'top_failing_workflows',
                ],
                'bottlenecks',
                'workflows',
            ]);
    }

    public function test_manager_can_filter_workflow_analytics_by_workflow_and_status(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager1');
        $team = $this->createTeam($tenant, $manager, 'Engineering');
        $this->assignToTeam($tenant, $manager, $team);

        $workflow = $this->createWorkflow($tenant, $team, $manager, 'Deployment Pipeline');
        $this->createInstance($tenant, $workflow, WorkflowInstanceStatus::Completed, Carbon::now()->subHours(2), Carbon::now()->subHours(1));
        $this->createInstance($tenant, $workflow, WorkflowInstanceStatus::Failed, Carbon::now()->subHours(4), Carbon::now()->subHours(3));

        $response = $this->actingAs($manager, 'api')->getJson('/api/v1/workflows/reports/workflow-analytics?' . http_build_query([
            'workflow_id' => $workflow->id,
            'status' => 'completed',
        ]));

        $response->assertOk()
            ->assertJsonPath('summary.total_executions', 1)
            ->assertJsonPath('summary.successful_executions', 1)
            ->assertJsonPath('summary.failed_executions', 0)
            ->assertJsonPath('summary.success_rate', 100);
    }

    public function test_manager_can_export_workflow_analytics_as_csv_and_pdf(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager1');
        $team = $this->createTeam($tenant, $manager, 'Engineering');
        $this->assignToTeam($tenant, $manager, $team);

        $csvResponse = $this->actingAs($manager, 'api')->get('/api/v1/workflows/reports/workflow-analytics/export?format=csv');
        $csvResponse->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $pdfResponse = $this->actingAs($manager, 'api')->get('/api/v1/workflows/reports/workflow-analytics/export?format=pdf');
        $pdfResponse->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_business_owner_can_fetch_reports_for_any_tenant_team(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->createUser($tenant, Role::BusinessOwner, 'owner1');
        $manager = $this->createUser($tenant, Role::Manager, 'manager1');
        $team = $this->createTeam($tenant, $manager, 'Support Team');
        $this->assignToTeam($tenant, $manager, $team);

        $response = $this->actingAs($owner, 'api')->getJson('/api/v1/workflows/reports/team-performance?team_id=' . $team->id);
        $response->assertOk()
            ->assertJsonPath('team.id', $team->id)
            ->assertJsonPath('team.name', 'Support Team');
    }

    public function test_manager_cannot_access_other_teams_reports(): void
    {
        $tenant = $this->createTenant();
        $manager1 = $this->createUser($tenant, Role::Manager, 'manager1');
        $manager2 = $this->createUser($tenant, Role::Manager, 'manager2');
        $team1 = $this->createTeam($tenant, $manager1, 'Team 1');
        $team2 = $this->createTeam($tenant, $manager2, 'Team 2');
        $this->assignToTeam($tenant, $manager1, $team1);
        $this->assignToTeam($tenant, $manager2, $team2);

        // Manager 1 attempting to request team_id of Team 2
        $response = $this->actingAs($manager1, 'api')->getJson('/api/v1/workflows/reports/team-performance?team_id=' . $team2->id);
        $response->assertForbidden();
    }

    public function test_employee_cannot_access_manager_reports(): void
    {
        $tenant = $this->createTenant();
        $employee = $this->createUser($tenant, Role::Employee, 'employee1');

        $response = $this->actingAs($employee, 'api')->getJson('/api/v1/workflows/reports/team-performance');
        $response->assertForbidden();
    }

    // Helper Factory Methods
    protected function createTenant(): Tenant
    {
        return Tenant::create([
            'business_name' => 'Acme Corp',
            'business_type' => BusinessType::SaaS->value,
            'is_active' => true,
        ]);
    }

    protected function createUser(Tenant $tenant, Role $role, string $prefix): User
    {
        return User::create([
            'tenant_id' => $tenant->id,
            'name' => ucfirst($prefix) . ' User',
            'first_name' => ucfirst($prefix),
            'last_name' => 'User',
            'email' => "{$prefix}@example.test",
            'password' => Hash::make('password123'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    protected function createTeam(Tenant $tenant, User $manager, string $name): Team
    {
        return Team::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'description' => 'Test team description',
            'manager_id' => $manager->id,
        ]);
    }

    protected function assignToTeam(Tenant $tenant, User $user, Team $team): void
    {
        TeamMembership::create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    protected function createWorkflow(Tenant $tenant, Team $team, User $creator, string $name): Workflow
    {
        return Workflow::create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'created_by_id' => $creator->id,
            'name' => $name,
            'status' => WorkflowStatus::Active,
            'draft_definition' => ['nodes' => [], 'edges' => []],
            'draft_revision' => 1,
            'current_version_number' => 1,
        ]);
    }

    protected function createInstance(
        Tenant $tenant,
        Workflow $workflow,
        WorkflowInstanceStatus $status,
        ?Carbon $startedAt = null,
        ?Carbon $finishedAt = null
    ): WorkflowInstance {
        $inst = new WorkflowInstance();
        $inst->timestamps = false;
        $inst->fill([
            'tenant_id' => $tenant->id,
            'workflow_id' => $workflow->id,
            'status' => $status,
            'trigger_type' => TriggerType::Manual,
            'correlation_id' => (string) Str::uuid(),
            'started_at' => $startedAt ?? Carbon::now()->subMinutes(10),
            'finished_at' => $finishedAt ?? ($status === WorkflowInstanceStatus::Completed ? Carbon::now() : null),
        ]);
        $inst->created_at = $startedAt ?? Carbon::now()->subMinutes(10);
        $inst->updated_at = $finishedAt ?? Carbon::now();
        $inst->save();

        return $inst;
    }

    protected function createExecution(
        Tenant $tenant,
        WorkflowInstance $instance,
        string $nodeKey,
        string $nodeType,
        NodeExecutionStatus $status,
        ?Carbon $startedAt = null,
        ?Carbon $finishedAt = null
    ): WorkflowNodeExecution {
        $exec = new WorkflowNodeExecution();
        $exec->timestamps = false;
        $exec->fill([
            'tenant_id' => $tenant->id,
            'instance_id' => $instance->id,
            'node_key' => $nodeKey,
            'node_type' => $nodeType,
            'status' => $status,
            'attempt' => 1,
            'idempotency_key' => "inst_{$instance->id}_{$nodeKey}_" . Str::random(6),
            'started_at' => $startedAt ?? Carbon::now()->subMinutes(5),
            'finished_at' => $finishedAt ?? Carbon::now(),
        ]);
        $exec->created_at = $startedAt ?? Carbon::now()->subMinutes(5);
        $exec->updated_at = $finishedAt ?? Carbon::now();
        $exec->save();

        return $exec;
    }

    protected function createTask(
        Tenant $tenant,
        WorkflowInstance $instance,
        WorkflowNodeExecution $execution,
        User $assignee,
        string $status,
        Carbon $createdAt,
        ?Carbon $dueAt = null,
        ?Carbon $completedAt = null
    ): WorkflowTask {
        $task = new WorkflowTask();
        $task->timestamps = false;
        $task->fill([
            'tenant_id' => $tenant->id,
            'instance_id' => $instance->id,
            'execution_id' => $execution->id,
            'node_key' => $execution->node_key,
            'assignee_id' => $assignee->id,
            'title' => "Task for {$assignee->name}",
            'status' => $status,
            'due_at' => $dueAt,
            'completed_by_id' => $completedAt ? $assignee->id : null,
            'completed_at' => $completedAt,
        ]);
        $task->created_at = $createdAt;
        $task->updated_at = $completedAt ?? $createdAt;
        $task->save();

        return $task;
    }
}
