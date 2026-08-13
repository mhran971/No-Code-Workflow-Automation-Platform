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
use Modules\Team\Models\TeamMembership;
use Modules\Workflows\Database\Seeders\NodeDefinitionSeeder;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\WorkflowVerificationService;
use Tests\TestCase;

class CustomerContextVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected WorkflowVerificationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NodeDefinitionSeeder::class);
        $this->validator = app(WorkflowVerificationService::class);
    }

    protected function validate(array $definition, ?Workflow $workflow = null): array
    {
        return $this->validator->verify($definition, $workflow)->toArray();
    }

    protected function assertHasError(array $result, string $code): void
    {
        $codes = array_column($result['issues'], 'code');
        $this->assertContains($code, $codes, "Expected error code '{$code}' but got: ".implode(', ', $codes));
    }

    protected function assertNoError(array $result, string $code): void
    {
        $codes = array_column($result['issues'], 'code');
        $this->assertNotContains($code, $codes, "Unexpected error code '{$code}' found.");
    }

    protected function formTriggerDefinition(array $triggerConfigOverrides = []): array
    {
        return [
            'trigger' => [
                'type' => 'form-trigger',
                'config' => array_replace_recursive([
                    'formName' => 'Contact Us',
                    'formFields' => [
                        ['key' => 'customerEmail', 'label' => 'Email', 'type' => 'text', 'required' => true],
                    ],
                    'accessLevel' => 'public',
                ], $triggerConfigOverrides),
            ],
            'nodes' => [
                [
                    'id' => 'start',
                    'type' => 'form-trigger',
                    'is_entry_point' => true,
                    'is_terminal' => true,
                    'config' => array_replace_recursive([
                        'formName' => 'Contact Us',
                        'formFields' => [
                            ['key' => 'customerEmail', 'label' => 'Email', 'type' => 'text', 'required' => true],
                        ],
                        'accessLevel' => 'public',
                    ], $triggerConfigOverrides),
                ],
            ],
            'edges' => [],
            'settings' => [],
        ];
    }

    protected function manualTriggerDefinition(array $triggerConfigOverrides = []): array
    {
        return [
            'trigger' => [
                'type' => 'manual-trigger',
                'config' => array_replace_recursive([
                    'variables' => [
                        ['key' => 'customerEmail', 'value' => 'someone@example.test'],
                    ],
                ], $triggerConfigOverrides),
            ],
            'nodes' => [
                [
                    'id' => 'start',
                    'type' => 'manual-trigger',
                    'is_entry_point' => true,
                    'is_terminal' => true,
                    'config' => array_replace_recursive([
                        'variables' => [
                            ['key' => 'customerEmail', 'value' => 'someone@example.test'],
                        ],
                    ], $triggerConfigOverrides),
                ],
            ],
            'edges' => [],
            'settings' => [],
        ];
    }

    protected function tenantWithLinkingField(CustomerLinkingField $field = CustomerLinkingField::Email): Workflow
    {
        $tenant = Tenant::query()->create([
            'business_name' => 'Acme Inc',
            'business_type' => BusinessType::SaaS->value,
        ]);
        CustomerSettings::query()->create(['tenant_id' => $tenant->id, 'linking_field' => $field->value]);

        $manager = User::query()->create([
            'first_name' => 'M', 'last_name' => 'Manager', 'name' => 'M Manager',
            'email' => 'manager-'.uniqid().'@example.test', 'password' => Hash::make('Pass1234!'),
            'tenant_id' => $tenant->id, 'role' => Role::Manager, 'is_active' => true,
        ]);
        $team = Team::query()->create(['tenant_id' => $tenant->id, 'name' => 'Team', 'manager_id' => $manager->id]);
        TeamMembership::query()->create(['tenant_id' => $tenant->id, 'user_id' => $manager->id, 'team_id' => $team->id, 'status' => 'active']);

        return Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'created_by_id' => $manager->id,
            'name' => 'Test Workflow',
            'status' => WorkflowStatus::Disabled,
            'draft_definition' => ['trigger' => null, 'nodes' => [], 'edges' => [], 'settings' => []],
            'draft_revision' => 1,
        ]);
    }

    public function test_customer_context_disabled_is_valid(): void
    {
        $result = $this->validate($this->formTriggerDefinition());

        $this->assertNoError($result, 'customer_context.field_missing');
        $this->assertNoError($result, 'customer_context.field_undeclared');
        $this->assertNoError($result, 'customer_context.field_not_required');
        $this->assertNoError($result, 'customer_context.tenant_linking_field_not_configured');
    }

    public function test_form_trigger_missing_mapping_field_produces_error(): void
    {
        $result = $this->validate($this->formTriggerDefinition([
            'customerContextEnabled' => true,
        ]));

        $this->assertHasError($result, 'customer_context.field_missing');
    }

    public function test_form_trigger_undeclared_field_produces_error(): void
    {
        $result = $this->validate($this->formTriggerDefinition([
            'customerContextEnabled' => true,
            'customerContextField' => 'doesNotExist',
        ]));

        $this->assertHasError($result, 'customer_context.field_undeclared');
    }

    public function test_form_trigger_field_not_required_produces_error(): void
    {
        $result = $this->validate($this->formTriggerDefinition([
            'customerContextEnabled' => true,
            'customerContextField' => 'customerEmail',
            'formFields' => [
                ['key' => 'customerEmail', 'label' => 'Email', 'type' => 'text', 'required' => false],
            ],
        ]));

        $this->assertHasError($result, 'customer_context.field_not_required');
    }

    public function test_form_trigger_valid_mapping_without_workflow_context_passes(): void
    {
        // No $workflow passed (e.g. a /validate dry-run) -> the tenant-linking-field check is skipped.
        $result = $this->validate($this->formTriggerDefinition([
            'customerContextEnabled' => true,
            'customerContextField' => 'customerEmail',
        ]));

        $this->assertNoError($result, 'customer_context.field_missing');
        $this->assertNoError($result, 'customer_context.field_undeclared');
        $this->assertNoError($result, 'customer_context.field_not_required');
    }

    public function test_customer_context_enabled_without_tenant_linking_field_configured_produces_error(): void
    {
        $tenant = Tenant::query()->create(['business_name' => 'Acme Inc', 'business_type' => BusinessType::SaaS->value]);
        $manager = User::query()->create([
            'first_name' => 'M', 'last_name' => 'Manager', 'name' => 'M Manager',
            'email' => 'manager-'.uniqid().'@example.test', 'password' => Hash::make('Pass1234!'),
            'tenant_id' => $tenant->id, 'role' => Role::Manager, 'is_active' => true,
        ]);
        $team = Team::query()->create(['tenant_id' => $tenant->id, 'name' => 'Team', 'manager_id' => $manager->id]);
        $workflow = Workflow::query()->create([
            'tenant_id' => $tenant->id, 'team_id' => $team->id, 'created_by_id' => $manager->id,
            'name' => 'Test Workflow', 'status' => WorkflowStatus::Disabled,
            'draft_definition' => ['trigger' => null, 'nodes' => [], 'edges' => [], 'settings' => []],
            'draft_revision' => 1,
        ]);
        // Deliberately no CustomerSettings row for this tenant.

        $result = $this->validate($this->formTriggerDefinition([
            'customerContextEnabled' => true,
            'customerContextField' => 'customerEmail',
        ]), $workflow);

        $this->assertHasError($result, 'customer_context.tenant_linking_field_not_configured');
    }

    public function test_form_trigger_valid_mapping_with_tenant_settings_configured_passes(): void
    {
        $workflow = $this->tenantWithLinkingField();

        $result = $this->validate($this->formTriggerDefinition([
            'customerContextEnabled' => true,
            'customerContextField' => 'customerEmail',
        ]), $workflow);

        $this->assertNoError($result, 'customer_context.field_missing');
        $this->assertNoError($result, 'customer_context.field_undeclared');
        $this->assertNoError($result, 'customer_context.field_not_required');
        $this->assertNoError($result, 'customer_context.tenant_linking_field_not_configured');
    }

    public function test_manual_trigger_valid_variable_mapping_passes(): void
    {
        $workflow = $this->tenantWithLinkingField();

        $result = $this->validate($this->manualTriggerDefinition([
            'customerContextEnabled' => true,
            'customerContextField' => 'customerEmail',
        ]), $workflow);

        $this->assertNoError($result, 'customer_context.field_missing');
        $this->assertNoError($result, 'customer_context.field_undeclared');
        // manual-trigger has no "must be required" check (see rule) -- only presence matters.
        $this->assertNoError($result, 'customer_context.field_not_required');
    }

    public function test_manual_trigger_undeclared_variable_produces_error(): void
    {
        $result = $this->validate($this->manualTriggerDefinition([
            'customerContextEnabled' => true,
            'customerContextField' => 'doesNotExist',
        ]));

        $this->assertHasError($result, 'customer_context.field_undeclared');
    }
}
