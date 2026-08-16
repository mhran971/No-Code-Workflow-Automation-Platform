<?php

namespace Tests\Unit\Execution;

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
use Modules\Workflows\Models\WorkflowVersion;
use Modules\Workflows\Services\Execution\WorkflowDispatcher;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression test for the workflow_instances.started_at NOT NULL bug: when a tenant is
 * at its concurrent-instance admission cap, WorkflowDispatcher intentionally creates the
 * instance with status=pending and started_at=null (AdmitPendingInstancesCommand fills it
 * in later). The column must allow that or the insert throws a 23000 integrity violation —
 * this previously surfaced as a crash on every sub-workflow trigger once a tenant was
 * saturated.
 */
class WorkflowDispatcherAdmissionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dispatch_creates_a_pending_instance_with_null_started_at_when_tenant_is_over_the_admission_cap(): void
    {
        config(['workflows.execution.admission.max_concurrent_instances_per_tenant' => 1]);

        $tenant = $this->createTenant();
        $manager = $this->createUser($tenant, 'admission-manager');
        $team = $this->createTeam($tenant, $manager);
        $workflow = $this->createWorkflow($tenant, $team, $manager);
        $version = $this->createWorkflowVersion($tenant, $workflow, $manager);
        $workflow->update(['current_version_id' => $version->id]);

        // Saturate the tenant's admission cap with an already-running instance.
        WorkflowInstance::query()->create([
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'tenant_id' => $tenant->id,
            'status' => WorkflowInstanceStatus::Running,
            'trigger_type' => TriggerType::Manual,
            'payload' => [],
            'context' => [],
            'started_at' => now(),
        ]);

        $dispatcher = app(WorkflowDispatcher::class);

        $instance = $dispatcher->dispatch($workflow, TriggerType::Manual, []);

        $this->assertSame(WorkflowInstanceStatus::Pending, $instance->status);
        $this->assertNull($instance->started_at);
        $this->assertDatabaseHas('workflow_instances', [
            'id' => $instance->id,
            'status' => WorkflowInstanceStatus::Pending->value,
            'started_at' => null,
        ]);
    }

    private function createTenant(): Tenant
    {
        return Tenant::query()->create([
            'business_name' => 'Acme Inc',
            'business_type' => BusinessType::SaaS->value,
        ]);
    }

    private function createUser(Tenant $tenant, string $prefix): User
    {
        return User::query()->create([
            'first_name' => ucfirst($prefix),
            'last_name' => 'User',
            'name' => ucfirst($prefix).' User',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'password' => Hash::make('Pass1234!'),
            'tenant_id' => $tenant->id,
            'role' => Role::Manager,
            'is_active' => true,
        ]);
    }

    private function createTeam(Tenant $tenant, User $manager): Team
    {
        return Team::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Operations',
            'manager_id' => $manager->id,
        ]);
    }

    private function createWorkflow(Tenant $tenant, Team $team, User $manager): Workflow
    {
        return Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'created_by_id' => $manager->id,
            'name' => 'Admission Cap Workflow',
            'description' => 'Workflow used for admission cap dispatcher tests',
            'status' => WorkflowStatus::Active->value,
            'draft_definition' => [
                'trigger' => ['type' => 'manual-trigger', 'config' => []],
                'nodes' => [
                    ['id' => 'start', 'type' => 'manual-trigger', 'config' => []],
                ],
                'edges' => [],
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
                'trigger' => ['type' => 'manual-trigger', 'config' => []],
                'nodes' => [
                    ['id' => 'start', 'type' => 'manual-trigger', 'config' => []],
                ],
                'edges' => [],
            ],
            'release_note' => null,
            'published_by_id' => $manager->id,
            'published_at' => Carbon::parse('2026-08-01 00:00:00', 'UTC'),
        ]);
    }
}
