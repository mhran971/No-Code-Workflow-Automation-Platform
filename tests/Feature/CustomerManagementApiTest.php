<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;
use Modules\Customers\Enums\CustomerLinkingField;
use Modules\Customers\Models\CustomerSettings;
use Modules\Team\Models\Team;
use Modules\Workflows\Database\Seeders\NodeDefinitionSeeder;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Tests\TestCase;

class CustomerManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_list_and_update_a_customer(): void
    {
        [$tenant, $manager] = $this->tenantWithManager();

        $create = $this->actingAs($manager, 'api')->postJson('/api/v1/customers', [
            'email' => 'jane@example.test',
            'first_name' => 'Jane',
        ])->assertCreated()->assertJsonPath('data.email', 'jane@example.test');

        $id = $create->json('data.id');

        $this->actingAs($manager, 'api')->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($manager, 'api')->putJson("/api/v1/customers/{$id}", [
            'last_name' => 'Doe',
        ])->assertOk()->assertJsonPath('data.last_name', 'Doe');

        $this->actingAs($manager, 'api')->deleteJson("/api/v1/customers/{$id}")->assertOk();
        $this->assertDatabaseMissing('customers', ['id' => $id]);
    }

    public function test_employee_is_denied_access_to_customer_records(): void
    {
        [$tenant, $manager] = $this->tenantWithManager();
        $employee = User::query()->create([
            'first_name' => 'E', 'last_name' => 'Employee', 'name' => 'E Employee',
            'email' => 'employee-'.uniqid().'@example.test', 'password' => Hash::make('Pass1234!'),
            'tenant_id' => $tenant->id, 'role' => Role::Employee, 'is_active' => true,
        ]);

        $this->actingAs($employee, 'api')->getJson('/api/v1/customers')->assertForbidden();
    }

    public function test_custom_field_with_reserved_key_is_rejected(): void
    {
        [$tenant, $manager] = $this->tenantWithManager();

        $this->actingAs($manager, 'api')->postJson('/api/v1/customers/fields', [
            'key' => 'email',
            'label' => 'Email again',
            'type' => 'text',
        ])->assertStatus(422);
    }

    public function test_customer_create_rejects_unknown_custom_field_key(): void
    {
        [$tenant, $manager] = $this->tenantWithManager();

        $response = $this->actingAs($manager, 'api')->postJson('/api/v1/customers', [
            'email' => 'jane@example.test',
            'custom_field_values' => ['not_a_real_field' => 'x'],
        ]);

        $response->assertStatus(422);
    }

    public function test_updating_linking_field_is_blocked_while_a_published_workflow_uses_customer_context(): void
    {
        [$tenant, $manager] = $this->tenantWithManager(CustomerLinkingField::Email);
        $this->seed(NodeDefinitionSeeder::class);

        $team = Team::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $workflow = Workflow::query()->create([
            'tenant_id' => $tenant->id, 'team_id' => $team->id, 'created_by_id' => $manager->id,
            'name' => 'Linked Workflow', 'status' => WorkflowStatus::Disabled,
            'draft_definition' => ['trigger' => null, 'nodes' => [], 'edges' => [], 'settings' => []],
            'draft_revision' => 1,
        ]);

        $definition = [
            'trigger' => [
                'type' => 'manual-trigger',
                'config' => [
                    'variables' => [['key' => 'customerEmail', 'value' => 'static@example.test']],
                    'customerContextEnabled' => true,
                    'customerContextField' => 'customerEmail',
                ],
            ],
            'nodes' => [
                [
                    'id' => 'start', 'type' => 'manual-trigger', 'is_entry_point' => true,
                    'config' => [
                        'variables' => [['key' => 'customerEmail', 'value' => 'static@example.test']],
                        'customerContextEnabled' => true,
                        'customerContextField' => 'customerEmail',
                    ],
                ],
                ['id' => 'end', 'type' => 'termination-node', 'is_terminal' => true, 'config' => []],
            ],
            'edges' => [['id' => 'e1', 'source_node_key' => 'start', 'target_node_key' => 'end', 'branch_type' => 'default']],
            'settings' => [],
        ];

        $this->actingAs($manager, 'api')->patchJson("/api/v1/workflows/{$workflow->id}/draft", [
            'definition' => $definition,
            'expected_draft_revision' => 1,
        ])->assertOk();
        $this->actingAs($manager, 'api')->postJson("/api/v1/workflows/{$workflow->id}/publish")->assertCreated();

        $this->actingAs($manager, 'api')->putJson('/api/v1/customers/settings', [
            'linking_field' => CustomerLinkingField::Phone->value,
        ])->assertStatus(422);

        $settings = CustomerSettings::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame(CustomerLinkingField::Email, $settings->linking_field);
    }

    /** @return array{0: Tenant, 1: User} */
    protected function tenantWithManager(CustomerLinkingField $linkingField = CustomerLinkingField::Email): array
    {
        $tenant = Tenant::query()->create(['business_name' => 'Acme Inc', 'business_type' => BusinessType::SaaS->value]);
        CustomerSettings::query()->create(['tenant_id' => $tenant->id, 'linking_field' => $linkingField->value]);

        $manager = User::query()->create([
            'first_name' => 'M', 'last_name' => 'Manager', 'name' => 'M Manager',
            'email' => 'manager-'.uniqid().'@example.test', 'password' => Hash::make('Pass1234!'),
            'tenant_id' => $tenant->id, 'role' => Role::Manager, 'is_active' => true,
        ]);
        Team::query()->create(['tenant_id' => $tenant->id, 'name' => 'Team', 'manager_id' => $manager->id]);

        return [$tenant, $manager];
    }
}
