<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;
use Modules\Notifications\Notifications\TaskEscalatedNotification;
use Modules\Team\Models\Team;
use Modules\Team\Models\TeamMembership;
use Modules\Workflows\Enums\NodeExecutionStatus;
use Modules\Workflows\Enums\TriggerType;
use Modules\Workflows\Enums\WaitType;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Models\WorkflowTask;
use Modules\Workflows\Models\WorkflowVersion;
use Modules\Workflows\Services\Execution\ExecutionPlan;
use Modules\Workflows\Services\Execution\Executors\TaskNodeExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;

use Tests\TestCase;

class EscalateOverdueTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_is_auto_escalated_and_manager_notified_on_sla_breach(): void
    {
        Notification::fake();

        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager@example.com');
        $team = $this->createTeam($tenant, $manager, 'Engineering');
        $employee = $this->createUser($tenant, Role::Employee, 'employee@example.com');
        $this->assignToTeam($tenant, $employee, $team);

        [$instance, $execution] = $this->createWorkflowAndExecution($tenant);

        // Set execution wait_until in the past
        $execution->update([
            'status' => NodeExecutionStatus::Waiting->value,
            'wait_type' => WaitType::TaskSla->value,
            'wait_until' => now()->subMinute(),
        ]);

        $task = WorkflowTask::query()->create([
            'instance_id' => $instance->id,
            'execution_id' => $execution->id,
            'tenant_id' => $tenant->id,
            'node_key' => 'task_1',
            'assignee_id' => $employee->id,
            'title' => 'Review Request',
            'description' => 'Please review this request',
            'status' => 'open',
            'due_at' => now()->subMinute(),
        ]);

        $evaluator = new \Modules\Workflows\Services\Execution\Expression\ExpressionEvaluator();
        $interpolator = new \Modules\Workflows\Services\Execution\Expression\TemplateInterpolator($evaluator);

        $context = new NodeExecutionContext(
            instance: $instance,
            execution: $execution,
            plan: new ExecutionPlan(['task_1' => []], ['task_1' => []], 'task_1', []),
            evaluator: $evaluator,
            interpolator: $interpolator,
        );

        $executor = app(TaskNodeExecutor::class);
        $result = $executor->execute($context);

        $this->assertEquals(\Modules\Workflows\app\Enums\ResultKind::Proceed, $result->kind);
        $this->assertEquals('escalated', $result->output['task_status']);

        $task->refresh();
        $this->assertEquals('escalated', $task->status);
        $this->assertNotNull($task->escalated_at);

        Notification::assertSentTo(
            $manager,
            TaskEscalatedNotification::class,
            function (TaskEscalatedNotification $notification) use ($task) {
                return $notification->toArray($task)['task_id'] === $task->id;
            }
        );
    }

    public function test_task_escalated_without_manager_does_not_fail(): void
    {
        Notification::fake();

        $tenant = $this->createTenant();
        $employee = $this->createUser($tenant, Role::Employee, 'alone@example.com');
        [$instance, $execution] = $this->createWorkflowAndExecution($tenant);

        $execution->update([
            'status' => NodeExecutionStatus::Waiting->value,
            'wait_type' => WaitType::TaskSla->value,
            'wait_until' => now()->subMinute(),
        ]);

        $task = WorkflowTask::query()->create([
            'instance_id' => $instance->id,
            'execution_id' => $execution->id,
            'tenant_id' => $tenant->id,
            'node_key' => 'task_1',
            'assignee_id' => $employee->id,
            'title' => 'Alone Task',
            'status' => 'open',
            'due_at' => now()->subMinute(),
        ]);

        $evaluator = new \Modules\Workflows\Services\Execution\Expression\ExpressionEvaluator();
        $interpolator = new \Modules\Workflows\Services\Execution\Expression\TemplateInterpolator($evaluator);

        $context = new NodeExecutionContext(
            instance: $instance,
            execution: $execution,
            plan: new ExecutionPlan(['task_1' => []], ['task_1' => []], 'task_1', []),
            evaluator: $evaluator,
            interpolator: $interpolator,
        );

        $executor = app(TaskNodeExecutor::class);
        $result = $executor->execute($context);

        $this->assertEquals(\Modules\Workflows\app\Enums\ResultKind::Proceed, $result->kind);
        $this->assertEquals('escalated', $result->output['task_status']);

        $task->refresh();
        $this->assertEquals('escalated', $task->status);
        $this->assertNotNull($task->escalated_at);

        Notification::assertNotSentTo($employee, TaskEscalatedNotification::class);
    }

    protected function createTenant(): Tenant
    {
        return Tenant::query()->create([
            'business_name' => 'Acme Corp',
            'business_type' => BusinessType::SaaS,
        ]);
    }

    protected function createUser(Tenant $tenant, Role $role, string $email): User
    {
        return User::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'name' => 'John Doe',
            'email' => $email,
            'password' => '$2y$10$92IXcountersafety',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    protected function createTeam(Tenant $tenant, User $manager, string $name): Team
    {
        return Team::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'manager_id' => $manager->id,
        ]);
    }

    protected function assignToTeam(Tenant $tenant, User $user, Team $team): TeamMembership
    {
        return TeamMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'team_id' => $team->id,
            'status' => 'active',
        ]);
    }

    protected function createWorkflowAndExecution(Tenant $tenant, ?Team $team = null, ?User $user = null): array
    {
        if ($team === null) {
            $user ??= $this->createUser($tenant, Role::Manager, 'dummy-'.uniqid().'@example.com');
            $team = $this->createTeam($tenant, $user, 'Default Team '.uniqid());
        }

        $workflow = Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'created_by_id' => $user?->id ?? $team->manager_id,
            'name' => 'Test Workflow',
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
            'published_by_id' => $user?->id ?? $team->manager_id,
            'published_at' => now(),
        ]);

        $instance = WorkflowInstance::query()->create([
            'tenant_id' => $tenant->id,
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'status' => WorkflowInstanceStatus::Running,
            'trigger_type' => TriggerType::Manual,
            'started_at' => now(),
        ]);

        $execution = WorkflowNodeExecution::query()->create([
            'instance_id' => $instance->id,
            'tenant_id' => $tenant->id,
            'node_key' => 'task_1',
            'node_type' => 'task-node',
            'status' => NodeExecutionStatus::Running->value,
            'idempotency_key' => 'task_1-'.uniqid(),
            'started_at' => now(),
        ]);

        return [$instance, $execution];
    }
}
