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

class WorkflowRecommendationsReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_fetch_recommendations_report_structure(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager1');
        $team = $this->createTeam($tenant, $manager, 'Engineering');
        $this->assignToTeam($tenant, $manager, $team);

        $response = $this->actingAs($manager, 'api')->getJson('/api/v1/workflows/reports/recommendations');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('team.id', $team->id)
            ->assertJsonPath('team.name', 'Engineering')
            ->assertJsonStructure([
                'status',
                'team' => ['id', 'name', 'manager'],
                'period' => ['from', 'to'],
                'thresholds' => [
                    'failure_rate_threshold_percent',
                    'cancellation_rate_threshold_percent',
                    'bottleneck_median_excess_percent',
                    'min_executions',
                ],
                'health_score' => [
                    'score',
                    'grade',
                    'total_recommendations',
                    'critical_count',
                    'warning_count',
                ],
                'recommendations' => [
                    'bottlenecks',
                    'high_failure_nodes',
                    'high_cancellation_workflows',
                    'sla_breach_hot_spots',
                ],
            ]);
    }

    public function test_detects_bottleneck_nodes_exceeding_median_by_more_than_50_percent(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager1');
        $team = $this->createTeam($tenant, $manager, 'Engineering');
        $this->assignToTeam($tenant, $manager, $team);

        $workflow = $this->createWorkflow($tenant, $team, $manager, 'Order Processing');

        // Create 3 instances with 3 nodes: fast_node (1s), normal_node (2s), slow_node (15s)
        for ($i = 0; $i < 3; $i++) {
            $inst = $this->createInstance($tenant, $workflow, WorkflowInstanceStatus::Completed);
            
            // Fast node: 1s
            $this->createExecution($tenant, $inst, 'fast_step', 'parse-json', NodeExecutionStatus::Succeeded, Carbon::now()->subSeconds(20), Carbon::now()->subSeconds(19));
            
            // Normal node: 2s
            $this->createExecution($tenant, $inst, 'normal_step', 'if-node', NodeExecutionStatus::Succeeded, Carbon::now()->subSeconds(18), Carbon::now()->subSeconds(16));
            
            // Slow bottleneck node: 15s (Median is 2s; 15s is > 1.5 * 2 = 3s)
            $this->createExecution($tenant, $inst, 'slow_step', 'ai-generator', NodeExecutionStatus::Succeeded, Carbon::now()->subSeconds(15), Carbon::now());
        }

        $response = $this->actingAs($manager, 'api')->getJson('/api/v1/workflows/reports/recommendations');

        $response->assertOk()
            ->assertJsonCount(1, 'recommendations.bottlenecks')
            ->assertJsonPath('recommendations.bottlenecks.0.node_key', 'slow_step')
            ->assertJsonPath('recommendations.bottlenecks.0.node_type', 'ai-generator')
            ->assertJsonPath('recommendations.bottlenecks.0.workflow_id', $workflow->id);

        $excess = $response->json('recommendations.bottlenecks.0.excess_percentage');
        $this->assertGreaterThan(50, $excess);
    }

    public function test_detects_high_failure_nodes_above_threshold(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager1');
        $team = $this->createTeam($tenant, $manager, 'Engineering');
        $this->assignToTeam($tenant, $manager, $team);

        $workflow = $this->createWorkflow($tenant, $team, $manager, 'Payment Flow');

        // Create 5 instances: 2 failures out of 5 for payment_node (40% failure rate > default 10%)
        for ($i = 0; $i < 5; $i++) {
            $inst = $this->createInstance($tenant, $workflow, $i < 2 ? WorkflowInstanceStatus::Failed : WorkflowInstanceStatus::Completed);
            
            $status = $i < 2 ? NodeExecutionStatus::Failed : NodeExecutionStatus::Succeeded;
            $error = $i < 2 ? ['message' => 'Gateway Timeout 504'] : null;

            $exec = $this->createExecution($tenant, $inst, 'charge_card', 'send-email', $status);
            if ($error) {
                $exec->error = $error;
                $exec->save();
            }
        }

        $response = $this->actingAs($manager, 'api')->getJson('/api/v1/workflows/reports/recommendations');

        $response->assertOk()
            ->assertJsonCount(1, 'recommendations.high_failure_nodes')
            ->assertJsonPath('recommendations.high_failure_nodes.0.node_key', 'charge_card')
            ->assertJsonPath('recommendations.high_failure_nodes.0.failure_rate', 40.0)
            ->assertJsonPath('recommendations.high_failure_nodes.0.failed_executions', 2)
            ->assertJsonPath('recommendations.high_failure_nodes.0.total_executions', 5);
    }

    public function test_detects_high_cancellation_workflows(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager1');
        $team = $this->createTeam($tenant, $manager, 'Engineering');
        $this->assignToTeam($tenant, $manager, $team);

        $workflow = $this->createWorkflow($tenant, $team, $manager, 'Long Approval Workflow');

        // Create 4 instances: 2 cancelled out of 4 (50% cancellation rate > default 15%)
        for ($i = 0; $i < 4; $i++) {
            $status = $i < 2 ? WorkflowInstanceStatus::Cancelled : WorkflowInstanceStatus::Completed;
            $this->createInstance($tenant, $workflow, $status);
        }

        $response = $this->actingAs($manager, 'api')->getJson('/api/v1/workflows/reports/recommendations');

        $response->assertOk()
            ->assertJsonCount(1, 'recommendations.high_cancellation_workflows')
            ->assertJsonPath('recommendations.high_cancellation_workflows.0.workflow_id', $workflow->id)
            ->assertJsonPath('recommendations.high_cancellation_workflows.0.cancellation_rate', 50.0)
            ->assertJsonPath('recommendations.high_cancellation_workflows.0.cancelled_instances', 2);
    }

    public function test_detects_sla_breach_hot_spots_in_human_tasks(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager1');
        $employee = $this->createUser($tenant, Role::Employee, 'employee1');
        $team = $this->createTeam($tenant, $manager, 'Engineering');
        $this->assignToTeam($tenant, $manager, $team);
        $this->assignToTeam($tenant, $employee, $team);

        $workflow = $this->createWorkflow($tenant, $team, $manager, 'Document Review');
        $inst = $this->createInstance($tenant, $workflow, WorkflowInstanceStatus::Running);
        $exec = $this->createExecution($tenant, $inst, 'legal_review_step', 'task-node', NodeExecutionStatus::Waiting);

        // Create 2 breached tasks: 1 completed after due date, 1 open past due date
        // Task 1: Completed late
        $this->createTask(
            $tenant,
            $inst,
            $exec,
            $employee,
            'completed',
            Carbon::now()->subDays(5),
            Carbon::now()->subDays(3), // due
            Carbon::now()->subDays(1)  // completed late by 48 hours
        );

        // Task 2: Open past due
        $this->createTask(
            $tenant,
            $inst,
            $exec,
            $employee,
            'open',
            Carbon::now()->subDays(4),
            Carbon::now()->subDays(2), // due 2 days ago
            null
        );

        $response = $this->actingAs($manager, 'api')->getJson('/api/v1/workflows/reports/recommendations');

        $response->assertOk()
            ->assertJsonCount(1, 'recommendations.sla_breach_hot_spots')
            ->assertJsonPath('recommendations.sla_breach_hot_spots.0.node_key', 'legal_review_step')
            ->assertJsonPath('recommendations.sla_breach_hot_spots.0.breached_tasks', 2)
            ->assertJsonPath('recommendations.sla_breach_hot_spots.0.breach_rate', 100.0);
    }

    public function test_manager_can_export_recommendations_as_csv_and_pdf(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager1');
        $team = $this->createTeam($tenant, $manager, 'Engineering');
        $this->assignToTeam($tenant, $manager, $team);

        $csvResponse = $this->actingAs($manager, 'api')->get('/api/v1/workflows/reports/recommendations/export?format=csv');
        $csvResponse->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $pdfResponse = $this->actingAs($manager, 'api')->get('/api/v1/workflows/reports/recommendations/export?format=pdf');
        $pdfResponse->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_business_owner_can_fetch_recommendations_for_any_tenant_team(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->createUser($tenant, Role::BusinessOwner, 'owner1');
        $manager = $this->createUser($tenant, Role::Manager, 'manager1');
        $team = $this->createTeam($tenant, $manager, 'Support Team');
        $this->assignToTeam($tenant, $manager, $team);

        $response = $this->actingAs($owner, 'api')->getJson('/api/v1/workflows/reports/recommendations?team_id=' . $team->id);
        $response->assertOk()
            ->assertJsonPath('team.id', $team->id)
            ->assertJsonPath('team.name', 'Support Team');
    }

    public function test_employee_cannot_access_recommendations(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, Role::Manager, 'manager1');
        $employee = $this->createUser($tenant, Role::Employee, 'employee1');
        $team = $this->createTeam($tenant, $manager, 'Engineering');
        $this->assignToTeam($tenant, $manager, $team);
        $this->assignToTeam($tenant, $employee, $team);

        $response = $this->actingAs($employee, 'api')->getJson('/api/v1/workflows/reports/recommendations');
        $response->assertForbidden();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    protected function createTenant(): Tenant
    {
        return Tenant::create([
            'company_name' => 'Acme Corp ' . Str::random(5),
            'business_type' => BusinessType::Retail,
            'is_active' => true,
        ]);
    }

    protected function createUser(Tenant $tenant, Role $role, string $prefix): User
    {
        return User::create([
            'tenant_id' => $tenant->id,
            'first_name' => ucfirst($prefix),
            'last_name' => 'User',
            'email' => "{$prefix}_" . Str::random(6) . '@example.com',
            'password' => Hash::make('secret123'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    protected function createTeam(Tenant $tenant, User $manager, string $name): Team
    {
        return Team::create([
            'tenant_id' => $tenant->id,
            'manager_id' => $manager->id,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    protected function assignToTeam(Tenant $tenant, User $user, Team $team): TeamMembership
    {
        return TeamMembership::create([
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
