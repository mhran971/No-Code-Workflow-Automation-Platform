<?php

namespace Tests\Unit\Execution;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;
use Modules\Customers\Models\Customer;
use Modules\Team\Models\Team;
use Modules\Workflows\Enums\NodeExecutionStatus;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Models\WorkflowVersion;
use Modules\Workflows\Services\Execution\ExecutionPlan;
use Modules\Workflows\Services\Execution\Expression\ExpressionEvaluator;
use Modules\Workflows\Services\Execution\Expression\TemplateInterpolator;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The DB-backed part of this class (WorkflowInstance's `customer` relation) means this needs
 * RefreshDatabase despite living under Unit/ -- consistent with other DB-touching executor
 * dependency tests in this directory.
 */
class NodeExecutionContextTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function resolution_scope_exposes_linked_customer_fields_under_customer_namespace(): void
    {
        $tenant = Tenant::query()->create(['business_name' => 'Acme Inc', 'business_type' => BusinessType::SaaS->value]);
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'email' => 'jane@example.test',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'custom_field_values' => ['loyalty_tier' => 'gold'],
        ]);

        $context = $this->buildContext($tenant, $customer);

        $scope = $context->resolutionScope();

        $this->assertSame([
            'loyalty_tier' => 'gold',
            'email' => 'jane@example.test',
            'phone' => null,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ], $scope['customer']);
    }

    #[Test]
    public function resolution_scope_customer_is_empty_array_when_no_customer_linked(): void
    {
        $tenant = Tenant::query()->create(['business_name' => 'Acme Inc', 'business_type' => BusinessType::SaaS->value]);

        $context = $this->buildContext($tenant, null);

        $this->assertSame([], $context->resolutionScope()['customer']);
    }

    #[Test]
    public function render_resolves_customer_template_variables(): void
    {
        $tenant = Tenant::query()->create(['business_name' => 'Acme Inc', 'business_type' => BusinessType::SaaS->value]);
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'email' => 'jane@example.test',
            'first_name' => 'Jane',
        ]);

        $context = $this->buildContext($tenant, $customer);

        $this->assertSame('Hi Jane, thanks!', $context->render('Hi {{customer.first_name}}, thanks!'));
    }

    protected function buildContext(Tenant $tenant, ?Customer $customer): NodeExecutionContext
    {
        $manager = User::query()->create([
            'first_name' => 'M', 'last_name' => 'Manager', 'name' => 'M Manager',
            'email' => 'manager-'.uniqid().'@example.test', 'password' => Hash::make('Pass1234!'),
            'tenant_id' => $tenant->id, 'role' => Role::Manager, 'is_active' => true,
        ]);

        $team = Team::query()->create(['tenant_id' => $tenant->id, 'name' => 'Team', 'manager_id' => $manager->id]);

        $workflow = Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'name' => 'Test Workflow',
            'created_by_id' => $manager->id,
            'draft_definition' => ['trigger' => null, 'nodes' => [], 'edges' => [], 'settings' => []],
            'draft_revision' => 1,
        ]);

        $version = WorkflowVersion::query()->create([
            'workflow_id' => $workflow->id,
            'tenant_id' => $tenant->id,
            'version_number' => 1,
            'version_label' => 'v1.0.0',
            'definition' => ['trigger' => ['type' => 'manual-trigger', 'config' => []], 'nodes' => [], 'edges' => [], 'settings' => []],
            'published_by_id' => $manager->id,
            'published_at' => now(),
        ]);

        $instance = WorkflowInstance::query()->create([
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'tenant_id' => $tenant->id,
            'status' => WorkflowInstanceStatus::Running,
            'payload' => [],
            'context' => [],
            'customer_id' => $customer?->id,
            'started_at' => now(),
        ]);

        $execution = WorkflowNodeExecution::query()->create([
            'instance_id' => $instance->id,
            'tenant_id' => $tenant->id,
            'node_key' => 'start',
            'node_type' => 'manual-trigger',
            'status' => NodeExecutionStatus::Running,
            'attempt' => 1,
            'idempotency_key' => 'test-key',
            'input' => [],
        ]);

        $instance->load('customer');

        return new NodeExecutionContext(
            $instance,
            $execution,
            new ExecutionPlan(['trigger' => null, 'nodes' => [], 'edges' => []]),
            app(ExpressionEvaluator::class),
            app(TemplateInterpolator::class),
        );
    }
}
