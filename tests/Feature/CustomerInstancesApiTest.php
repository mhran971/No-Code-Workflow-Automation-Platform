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
use Modules\Customers\Models\Customer;
use Modules\Team\Models\Team;
use Modules\Workflows\Enums\TriggerType;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowVersion;
use Tests\TestCase;

class CustomerInstancesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_the_given_customers_instances_with_the_paginator_shape(): void
    {
        [$tenant, $manager] = $this->tenantWithManager();
        $workflow = $this->createWorkflow($tenant, $manager);
        $version = $this->createVersion($tenant, $workflow, $manager);

        $customer = $this->createCustomer($tenant, 'linked@example.test');
        $otherCustomer = $this->createCustomer($tenant, 'other@example.test');

        $mine = $this->createInstance($tenant, $workflow, $version, WorkflowInstanceStatus::Running, '2026-07-01 08:00:00', null, $customer);
        $this->createInstance($tenant, $workflow, $version, WorkflowInstanceStatus::Completed, '2026-07-02 08:00:00', '2026-07-02 09:00:00', $otherCustomer);
        $this->createInstance($tenant, $workflow, $version, WorkflowInstanceStatus::Completed, '2026-07-03 08:00:00', '2026-07-03 09:00:00', null);

        /** @var Authenticatable $managerAuth */
        $managerAuth = $manager;

        $this->actingAs($managerAuth, 'api')
            ->getJson("/api/v1/customers/{$customer->id}/instances")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.customer_id', $customer->id)
            ->assertJsonPath('total', 1)
            ->assertJsonStructure([
                'current_page',
                'data' => [['id', 'workflow_id', 'customer_id', 'status', 'started_at', 'finished_at']],
                'per_page',
                'total',
            ]);
    }

    public function test_filters_by_status_and_date_ranges(): void
    {
        [$tenant, $manager] = $this->tenantWithManager();
        $workflow = $this->createWorkflow($tenant, $manager);
        $version = $this->createVersion($tenant, $workflow, $manager);
        $customer = $this->createCustomer($tenant, 'linked@example.test');

        $this->createInstance($tenant, $workflow, $version, WorkflowInstanceStatus::Running, '2026-07-01 08:00:00', null, $customer);
        $failed = $this->createInstance($tenant, $workflow, $version, WorkflowInstanceStatus::Failed, '2026-07-03 09:00:00', '2026-07-03 10:00:00', $customer);
        $completed = $this->createInstance($tenant, $workflow, $version, WorkflowInstanceStatus::Completed, '2026-07-05 11:00:00', '2026-07-06 12:00:00', $customer);

        /** @var Authenticatable $managerAuth */
        $managerAuth = $manager;

        $this->actingAs($managerAuth, 'api')
            ->getJson("/api/v1/customers/{$customer->id}/instances?status=failed")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $failed->id);

        $this->actingAs($managerAuth, 'api')
            ->getJson("/api/v1/customers/{$customer->id}/instances?started_from=2026-07-02&started_to=2026-07-04")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $failed->id);

        $this->actingAs($managerAuth, 'api')
            ->getJson("/api/v1/customers/{$customer->id}/instances?finished_from=2026-07-06&finished_to=2026-07-06")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $completed->id);
    }

    public function test_rejects_invalid_status_filter(): void
    {
        [$tenant, $manager] = $this->tenantWithManager();
        $customer = $this->createCustomer($tenant, 'linked@example.test');
        /** @var Authenticatable $managerAuth */
        $managerAuth = $manager;

        $this->actingAs($managerAuth, 'api')
            ->getJson("/api/v1/customers/{$customer->id}/instances?status=bogus")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_customer_from_another_tenant_is_not_found(): void
    {
        [$tenant, $manager] = $this->tenantWithManager();
        $otherTenant = Tenant::query()->create(['business_name' => 'Other Inc', 'business_type' => BusinessType::SaaS->value]);
        $foreignCustomer = $this->createCustomer($otherTenant, 'foreign@example.test');
        /** @var Authenticatable $managerAuth */
        $managerAuth = $manager;

        $this->actingAs($managerAuth, 'api')
            ->getJson("/api/v1/customers/{$foreignCustomer->id}/instances")
            ->assertNotFound();
    }

    public function test_employee_is_denied(): void
    {
        [$tenant, $manager] = $this->tenantWithManager();
        $customer = $this->createCustomer($tenant, 'linked@example.test');
        $employee = User::query()->create([
            'first_name' => 'E', 'last_name' => 'Employee', 'name' => 'E Employee',
            'email' => 'employee-'.uniqid().'@example.test', 'password' => Hash::make('Pass1234!'),
            'tenant_id' => $tenant->id, 'role' => Role::Employee, 'is_active' => true,
        ]);
        /** @var Authenticatable $employeeAuth */
        $employeeAuth = $employee;

        $this->actingAs($employeeAuth, 'api')
            ->getJson("/api/v1/customers/{$customer->id}/instances")
            ->assertForbidden();
    }

    /** @return array{0: Tenant, 1: User} */
    protected function tenantWithManager(): array
    {
        $tenant = Tenant::query()->create(['business_name' => 'Acme Inc', 'business_type' => BusinessType::SaaS->value]);

        $manager = User::query()->create([
            'first_name' => 'M', 'last_name' => 'Manager', 'name' => 'M Manager',
            'email' => 'manager-'.uniqid().'@example.test', 'password' => Hash::make('Pass1234!'),
            'tenant_id' => $tenant->id, 'role' => Role::Manager, 'is_active' => true,
        ]);
        Team::query()->create(['tenant_id' => $tenant->id, 'name' => 'Team', 'manager_id' => $manager->id]);

        return [$tenant, $manager];
    }

    protected function createCustomer(Tenant $tenant, string $email): Customer
    {
        return Customer::query()->create([
            'tenant_id' => $tenant->id,
            'email' => $email,
            'is_active' => true,
        ]);
    }

    protected function createWorkflow(Tenant $tenant, User $manager): Workflow
    {
        $team = Team::query()->where('tenant_id', $tenant->id)->firstOrFail();

        return Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'created_by_id' => $manager->id,
            'name' => 'Customer Instances Workflow',
            'status' => WorkflowStatus::Active->value,
            'draft_definition' => ['trigger' => null, 'nodes' => [], 'edges' => [], 'settings' => []],
            'draft_revision' => 1,
        ]);
    }

    protected function createVersion(Tenant $tenant, Workflow $workflow, User $manager): WorkflowVersion
    {
        return WorkflowVersion::query()->create([
            'workflow_id' => $workflow->id,
            'tenant_id' => $tenant->id,
            'version_number' => 1,
            'version_label' => 'v1.0.0',
            'definition' => ['trigger' => null, 'nodes' => [], 'edges' => [], 'settings' => []],
            'release_note' => null,
            'published_by_id' => $manager->id,
            'published_at' => Carbon::parse('2026-07-01 00:00:00', 'UTC'),
        ]);
    }

    protected function createInstance(
        Tenant $tenant,
        Workflow $workflow,
        WorkflowVersion $version,
        WorkflowInstanceStatus $status,
        string $startedAt,
        ?string $finishedAt,
        ?Customer $customer,
    ): WorkflowInstance {
        return WorkflowInstance::query()->create([
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'parent_instance_id' => null,
            'customer_id' => $customer?->id,
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
