<?php

namespace Tests\Unit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;
use Modules\Team\Models\Team;
use Modules\Workflows\Enums\TriggerType;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowTask;
use Modules\Workflows\Models\WorkflowVersion;
use Modules\Workflows\Services\TenantOperationsDashboardService;
use Tests\TestCase;

class TenantOperationsDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private TenantOperationsDashboardService $service;

    private Tenant $tenant;

    private Workflow $workflow;

    private WorkflowVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TenantOperationsDashboardService::class);

        $this->tenant = Tenant::query()->create([
            'business_name' => 'Dashboard Test Co',
            'business_type' => BusinessType::SaaS->value,
        ]);

        $owner = User::query()->create([
            'first_name' => 'Owner',
            'last_name' => 'User',
            'name' => 'Owner User',
            'email' => 'owner-dashboard-'.uniqid().'@example.test',
            'password' => Hash::make('Pass1234!'),
            'tenant_id' => $this->tenant->id,
            'role' => Role::BusinessOwner,
            'is_active' => true,
        ]);

        $team = Team::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dashboard Team',
            'manager_id' => $owner->id,
        ]);

        $this->workflow = Workflow::query()->create([
            'tenant_id' => $this->tenant->id,
            'team_id' => $team->id,
            'created_by_id' => $owner->id,
            'name' => 'Dashboard Test Workflow',
            'description' => 'Used in dashboard KPI tests',
            'status' => WorkflowStatus::Active->value,
            'draft_definition' => ['trigger' => null, 'nodes' => [], 'edges' => [], 'settings' => []],
            'draft_revision' => 1,
        ]);

        $this->version = WorkflowVersion::query()->create([
            'workflow_id' => $this->workflow->id,
            'tenant_id' => $this->tenant->id,
            'version_number' => 1,
            'version_label' => 'v1.0.0',
            'definition' => ['trigger' => null, 'nodes' => [], 'edges' => [], 'settings' => []],
            'release_note' => null,
            'published_by_id' => $owner->id,
            'published_at' => Carbon::parse('2026-07-01 00:00:00', 'UTC'),
        ]);
    }

    // ─── Active Instances ────────────────────────────────────────────

    public function test_active_instances_counts_only_non_terminal(): void
    {
        // Non-terminal statuses → should be counted.
        $this->createInstance(WorkflowInstanceStatus::Running, '2026-07-10 08:00:00');
        $this->createInstance(WorkflowInstanceStatus::Waiting, '2026-07-10 09:00:00');
        $this->createInstance(WorkflowInstanceStatus::Paused, '2026-07-10 10:00:00');
        $this->createInstance(WorkflowInstanceStatus::Pending, '2026-07-10 11:00:00');

        // Terminal statuses → should NOT be counted.
        $this->createInstance(WorkflowInstanceStatus::Completed, '2026-07-10 12:00:00', '2026-07-10 13:00:00');
        $this->createInstance(WorkflowInstanceStatus::Failed, '2026-07-10 14:00:00');
        $this->createInstance(WorkflowInstanceStatus::Cancelled, '2026-07-10 15:00:00');

        $count = $this->service->countActiveInstances($this->tenant->id);

        $this->assertSame(4, $count);
    }

    // ─── Pending Tasks ───────────────────────────────────────────────

    public function test_pending_tasks_counts_open_tasks_only(): void
    {
        $instance = $this->createInstance(WorkflowInstanceStatus::Running, '2026-07-10 08:00:00');

        $this->createTask($instance, 'open');
        $this->createTask($instance, 'open');
        $this->createTask($instance, 'completed');
        $this->createTask($instance, 'expired');
        $this->createTask($instance, 'cancelled');

        $count = $this->service->countPendingTasks($this->tenant->id);

        $this->assertSame(2, $count);
    }

    // ─── Average Completion Time ─────────────────────────────────────

    public function test_average_completion_time_with_completed_instances(): void
    {
        // Instance 1: 1 hour (3600s).
        $this->createInstance(
            WorkflowInstanceStatus::Completed,
            '2026-07-10 08:00:00',
            '2026-07-10 09:00:00',
        );

        // Instance 2: 2 hours (7200s).
        $this->createInstance(
            WorkflowInstanceStatus::Completed,
            '2026-07-10 10:00:00',
            '2026-07-10 12:00:00',
        );

        $avg = $this->service->computeAverageCompletionTime($this->tenant->id);

        // Average: (3600 + 7200) / 2 = 5400.0
        $this->assertSame(5400.0, $avg);
    }

    public function test_average_completion_time_returns_null_when_no_completed(): void
    {
        $this->createInstance(WorkflowInstanceStatus::Running, '2026-07-10 08:00:00');

        $avg = $this->service->computeAverageCompletionTime($this->tenant->id);

        $this->assertNull($avg);
    }

    // ─── Failed Instances (24h) ──────────────────────────────────────

    public function test_failed_instances_counts_only_last_24_hours(): void
    {
        $now = Carbon::parse('2026-07-12 12:00:00', 'UTC');

        // Within last 24 hours.
        $recent = $this->createInstance(WorkflowInstanceStatus::Failed, '2026-07-12 08:00:00');
        $recent->update(['updated_at' => Carbon::parse('2026-07-12 08:00:00', 'UTC')]);

        // Older than 24 hours.
        $old = $this->createInstance(WorkflowInstanceStatus::Failed, '2026-07-10 08:00:00');
        $old->update(['updated_at' => Carbon::parse('2026-07-10 08:00:00', 'UTC')]);

        $count = $this->service->countFailedInstances24h($this->tenant->id, $now);

        $this->assertSame(1, $count);
    }

    // ─── SLA Breaches (24h) ──────────────────────────────────────────

    public function test_sla_breaches_counts_overdue_open_tasks_in_last_24h(): void
    {
        $now = Carbon::parse('2026-07-12 12:00:00', 'UTC');
        $instance = $this->createInstance(WorkflowInstanceStatus::Running, '2026-07-10 08:00:00');

        // SLA breach: open + due_at passed + within 24h of $now.
        $this->createTask($instance, 'open', '2026-07-12 06:00:00');

        // NOT a breach: open + due_at passed but older than 24h.
        $this->createTask($instance, 'open', '2026-07-10 06:00:00');

        // NOT a breach: completed (not open), even though due_at passed.
        $this->createTask($instance, 'completed', '2026-07-12 05:00:00');

        // NOT a breach: open but due_at is in the future.
        $this->createTask($instance, 'open', '2026-07-13 00:00:00');

        // NOT a breach: open but no due_at set.
        $this->createTask($instance, 'open', null);

        $count = $this->service->countSlaBreaches24h($this->tenant->id, $now);

        $this->assertSame(1, $count);
    }

    // ─── Tenant Isolation ────────────────────────────────────────────

    public function test_tenant_isolation(): void
    {
        // Create data for our tenant.
        $this->createInstance(WorkflowInstanceStatus::Running, '2026-07-10 08:00:00');

        // Create data for a different tenant.
        $otherTenant = Tenant::query()->create([
            'business_name' => 'Other Company',
            'business_type' => BusinessType::SaaS->value,
        ]);

        $otherOwner = User::query()->create([
            'first_name' => 'Other',
            'last_name' => 'Owner',
            'name' => 'Other Owner',
            'email' => 'other-owner-'.uniqid().'@example.test',
            'password' => Hash::make('Pass1234!'),
            'tenant_id' => $otherTenant->id,
            'role' => Role::BusinessOwner,
            'is_active' => true,
        ]);

        $otherTeam = Team::query()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Team',
            'manager_id' => $otherOwner->id,
        ]);

        $otherWorkflow = Workflow::query()->create([
            'tenant_id' => $otherTenant->id,
            'team_id' => $otherTeam->id,
            'created_by_id' => $otherOwner->id,
            'name' => 'Other Workflow',
            'description' => 'Belongs to other tenant',
            'status' => WorkflowStatus::Active->value,
            'draft_definition' => ['trigger' => null, 'nodes' => [], 'edges' => [], 'settings' => []],
            'draft_revision' => 1,
        ]);

        $otherVersion = WorkflowVersion::query()->create([
            'workflow_id' => $otherWorkflow->id,
            'tenant_id' => $otherTenant->id,
            'version_number' => 1,
            'version_label' => 'v1.0.0',
            'definition' => ['trigger' => null, 'nodes' => [], 'edges' => [], 'settings' => []],
            'release_note' => null,
            'published_by_id' => $otherOwner->id,
            'published_at' => Carbon::parse('2026-07-01 00:00:00', 'UTC'),
        ]);

        WorkflowInstance::query()->create([
            'workflow_id' => $otherWorkflow->id,
            'workflow_version_id' => $otherVersion->id,
            'tenant_id' => $otherTenant->id,
            'status' => WorkflowInstanceStatus::Running->value,
            'trigger_type' => TriggerType::Manual->value,
            'payload' => [],
            'context' => [],
            'started_at' => Carbon::parse('2026-07-10 08:00:00', 'UTC'),
        ]);

        // Our tenant should see only its own data.
        $kpis = $this->service->getKpis($this->tenant->id);

        $this->assertSame(1, $kpis['active_workflow_instances']);
    }

    // ─── Full getKpis shape ──────────────────────────────────────────

    public function test_get_kpis_returns_correct_shape(): void
    {
        $kpis = $this->service->getKpis($this->tenant->id);

        $this->assertArrayHasKey('active_workflow_instances', $kpis);
        $this->assertArrayHasKey('pending_tasks', $kpis);
        $this->assertArrayHasKey('average_completion_time_seconds', $kpis);
        $this->assertArrayHasKey('average_completion_time_human', $kpis);
        $this->assertArrayHasKey('failed_instances_24h', $kpis);
        $this->assertArrayHasKey('sla_breaches_24h', $kpis);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function createInstance(
        WorkflowInstanceStatus $status,
        string $startedAt,
        ?string $finishedAt = null,
    ): WorkflowInstance {
        return WorkflowInstance::query()->create([
            'workflow_id' => $this->workflow->id,
            'workflow_version_id' => $this->version->id,
            'parent_instance_id' => null,
            'tenant_id' => $this->tenant->id,
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

    private function createTask(
        WorkflowInstance $instance,
        string $status,
        ?string $dueAt = null,
    ): WorkflowTask {
        static $executionCounter = 0;
        $executionCounter++;

        // WorkflowTask requires a valid execution_id FK. Create a minimal node execution.
        $execution = \Modules\Workflows\Models\WorkflowNodeExecution::query()->create([
            'instance_id' => $instance->id,
            'tenant_id' => $this->tenant->id,
            'node_key' => 'task_'.$executionCounter,
            'node_type' => 'task-node',
            'status' => 'waiting',
            'attempt' => 1,
            'started_at' => $instance->started_at,
        ]);

        return WorkflowTask::query()->create([
            'instance_id' => $instance->id,
            'execution_id' => $execution->id,
            'tenant_id' => $this->tenant->id,
            'node_key' => 'task_'.$executionCounter,
            'title' => 'Test Task '.$executionCounter,
            'status' => $status,
            'due_at' => $dueAt ? Carbon::parse($dueAt, 'UTC') : null,
        ]);
    }
}
