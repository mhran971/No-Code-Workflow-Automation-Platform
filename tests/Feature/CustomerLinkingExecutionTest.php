<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;
use Modules\Customers\Enums\CustomerLinkingField;
use Modules\Customers\Models\Customer;
use Modules\Customers\Models\CustomerSettings;
use Modules\Team\Models\Team;
use Modules\Team\Models\TeamMembership;
use Modules\Workflows\Database\Seeders\NodeDefinitionSeeder;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Tests\TestCase;

class CustomerLinkingExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NodeDefinitionSeeder::class);
    }

    public function test_manual_trigger_creates_and_reuses_customer_by_email(): void
    {
        // ManualTriggerExecutor applies static trigger.config.variables *after* merging the
        // dispatch payload, unconditionally overwriting same-named keys -- so the value that
        // actually resolves is the one declared on the trigger node, not the request payload.
        [$tenant, $manager] = $this->tenantWithManager(CustomerLinkingField::Email);
        $workflow = $this->createPublishedManualTriggerWorkflow(
            $tenant, $manager, 'customerEmail', staticValue: 'jane.doe@example.test'
        );

        $first = $this->actingAs($manager, 'api')
            ->postJson("/api/v1/workflows/{$workflow->id}/trigger/manual", [])
            ->assertAccepted();

        $this->assertDatabaseCount('customers', 1);
        $customer = Customer::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('jane.doe@example.test', $customer->email);

        $this->assertDatabaseHas('workflow_instances', [
            'id' => $first->json('instance_id'),
            'customer_id' => $customer->id,
        ]);

        // Triggering again resolves the same value again -> reuses the same Customer, no duplicate.
        $second = $this->actingAs($manager, 'api')
            ->postJson("/api/v1/workflows/{$workflow->id}/trigger/manual", [])
            ->assertAccepted();

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('workflow_instances', [
            'id' => $second->json('instance_id'),
            'customer_id' => $customer->id,
        ]);
    }

    public function test_manual_trigger_creates_and_reuses_customer_by_phone(): void
    {
        [$tenant, $manager] = $this->tenantWithManager(CustomerLinkingField::Phone);
        $workflow = $this->createPublishedManualTriggerWorkflow(
            $tenant, $manager, 'customerPhone', staticValue: '(555) 123-4567'
        );

        $this->actingAs($manager, 'api')
            ->postJson("/api/v1/workflows/{$workflow->id}/trigger/manual", [])
            ->assertAccepted();

        $this->assertDatabaseCount('customers', 1);
        $customer = Customer::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('5551234567', $customer->phone); // normalized: digits only, no leading "+"

        $this->actingAs($manager, 'api')
            ->postJson("/api/v1/workflows/{$workflow->id}/trigger/manual", [])
            ->assertAccepted();

        $this->assertDatabaseCount('customers', 1);
    }

    public function test_customer_context_disabled_creates_no_customer_link(): void
    {
        [$tenant, $manager] = $this->tenantWithManager(CustomerLinkingField::Email);
        $workflow = $this->createPublishedManualTriggerWorkflow($tenant, $manager, 'customerEmail', enabled: false);

        $response = $this->actingAs($manager, 'api')->postJson("/api/v1/workflows/{$workflow->id}/trigger/manual", [
            'customerEmail' => 'someone@example.test',
        ])->assertAccepted();

        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseHas('workflow_instances', [
            'id' => $response->json('instance_id'),
            'customer_id' => null,
        ]);
    }

    /** @return array{0: Tenant, 1: User} */
    protected function tenantWithManager(CustomerLinkingField $linkingField): array
    {
        $tenant = Tenant::query()->create([
            'business_name' => 'Acme Inc',
            'business_type' => BusinessType::SaaS->value,
        ]);
        CustomerSettings::query()->create(['tenant_id' => $tenant->id, 'linking_field' => $linkingField->value]);

        $manager = User::query()->create([
            'first_name' => 'M', 'last_name' => 'Manager', 'name' => 'M Manager',
            'email' => 'manager-'.uniqid().'@example.test', 'password' => Hash::make('Pass1234!'),
            'tenant_id' => $tenant->id, 'role' => Role::Manager, 'is_active' => true,
        ]);
        $team = Team::query()->create(['tenant_id' => $tenant->id, 'name' => 'Team', 'manager_id' => $manager->id]);
        TeamMembership::query()->create(['tenant_id' => $tenant->id, 'user_id' => $manager->id, 'team_id' => $team->id, 'status' => 'active']);

        return [$tenant, $manager];
    }

    protected function createPublishedManualTriggerWorkflow(
        Tenant $tenant,
        User $manager,
        string $variableKey,
        ?string $staticValue = null,
        bool $enabled = true,
    ): Workflow {
        $team = Team::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $workflow = Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'created_by_id' => $manager->id,
            'name' => 'Customer Linking Workflow',
            'status' => WorkflowStatus::Disabled,
            'draft_definition' => ['trigger' => null, 'nodes' => [], 'edges' => [], 'settings' => []],
            'draft_revision' => 1,
        ]);

        $triggerConfig = [
            'variables' => [
                ['key' => $variableKey, 'value' => $staticValue],
            ],
            'customerContextEnabled' => $enabled,
            'customerContextField' => $enabled ? $variableKey : null,
        ];

        $definition = [
            'trigger' => ['type' => 'manual-trigger', 'config' => $triggerConfig],
            'nodes' => [
                [
                    'id' => 'start',
                    'type' => 'manual-trigger',
                    'is_entry_point' => true,
                    'config' => $triggerConfig,
                ],
                [
                    'id' => 'end',
                    'type' => 'termination-node',
                    'is_terminal' => true,
                    'config' => [],
                ],
            ],
            'edges' => [
                ['id' => 'edge-1', 'source_node_key' => 'start', 'target_node_key' => 'end', 'branch_type' => 'default'],
            ],
            'settings' => [],
        ];

        $this->actingAs($manager, 'api')->patchJson("/api/v1/workflows/{$workflow->id}/draft", [
            'definition' => $definition,
            'expected_draft_revision' => 1,
        ])->assertOk();

        $this->actingAs($manager, 'api')->postJson("/api/v1/workflows/{$workflow->id}/publish")->assertCreated();

        return $workflow->refresh();
    }
}
