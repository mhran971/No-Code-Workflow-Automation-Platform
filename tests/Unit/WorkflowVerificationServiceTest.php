<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;
use Modules\Team\Models\Team;
use Modules\Team\Models\TeamMembership;
use Modules\Workflows\Database\Seeders\NodeDefinitionSeeder;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\WorkflowVerificationService;
use Tests\TestCase;

class WorkflowVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WorkflowVerificationService $verificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NodeDefinitionSeeder::class);
        $this->verificationService = app(WorkflowVerificationService::class);
    }

    public function test_unknown_trigger_and_node_types_are_rejected(): void
    {
        $definition = $this->validDefinition();
        $definition['trigger']['type'] = 'mystery-trigger';
        $definition['nodes'][0]['type'] = 'mystery-node';

        $result = $this->verify($definition);

        $this->assertFalse($result['is_publishable']);
        $this->assertContains('trigger.type_unknown', $this->issueCodes($result));
        $this->assertContains('node.type_unknown', $this->issueCodes($result));
    }

    public function test_missing_required_config_fields_are_rejected(): void
    {
        $definition = $this->validDefinition();
        unset($definition['nodes'][0]['config']['to']);

        $result = $this->verify($definition);

        $this->assertFalse($result['is_publishable']);
        $this->assertContains('config.required_missing', $this->issueCodes($result));
    }

    public function test_duplicate_node_ids_are_rejected(): void
    {
        $definition = $this->validDefinition();
        $definition['nodes'][] = $definition['nodes'][0];

        $result = $this->verify($definition);

        $this->assertFalse($result['is_publishable']);
        $this->assertContains('node.id_duplicate', $this->issueCodes($result));
    }

    public function test_dangling_and_duplicate_edges_are_rejected(): void
    {
        $definition = $this->twoNodeDefinition();
        $definition['edges'][] = [
            'id' => 'duplicate-edge',
            'source' => 'start',
            'target' => 'finish',
        ];
        $definition['edges'][] = [
            'id' => 'dangling-edge',
            'source' => 'start',
            'target' => 'missing-node',
        ];

        $result = $this->verify($definition);

        $this->assertFalse($result['is_publishable']);
        $this->assertContains('edge.duplicate', $this->issueCodes($result));
        $this->assertContains('edge.target_missing_reference', $this->issueCodes($result));
    }

    public function test_ambiguous_entry_nodes_are_rejected(): void
    {
        $definition = $this->validDefinition();
        unset($definition['nodes'][0]['is_entry_point']);
        $definition['nodes'][] = [
            'id' => 'second-entry',
            'type' => 'set-variables',
            'is_terminal' => true,
            'config' => [
                'assignments' => ['approved' => true],
            ],
        ];

        $result = $this->verify($definition);

        $this->assertFalse($result['is_publishable']);
        $this->assertContains('graph.entry_ambiguous', $this->issueCodes($result));
    }

    public function test_unreachable_dead_end_cycles_are_rejected(): void
    {
        $definition = $this->validDefinition();
        $definition['nodes'][] = [
            'id' => 'cycle-a',
            'type' => 'set-variables',
            'config' => [
                'assignments' => ['a' => 1],
            ],
        ];
        $definition['nodes'][] = [
            'id' => 'cycle-b',
            'type' => 'set-variables',
            'config' => [
                'assignments' => ['b' => 1],
            ],
        ];
        $definition['edges'] = [
            [
                'id' => 'cycle-a-b',
                'source' => 'cycle-a',
                'target' => 'cycle-b',
            ],
            [
                'id' => 'cycle-b-a',
                'source' => 'cycle-b',
                'target' => 'cycle-a',
            ],
        ];

        $result = $this->verify($definition);

        $this->assertFalse($result['is_publishable']);
        $this->assertContains('graph.unreachable', $this->issueCodes($result));
        $this->assertContains('graph.dead_end', $this->issueCodes($result));
        $this->assertContains('graph.unstructured_cycle', $this->issueCodes($result));
    }

    public function test_invalid_conditional_expression_is_rejected(): void
    {
        $definition = $this->twoNodeDefinition();
        $definition['edges'][0]['branch_type'] = 'conditional';
        $definition['edges'][0]['condition_expression'] = 'amount >';

        $result = $this->verify($definition);

        $this->assertFalse($result['is_publishable']);
        $this->assertContains('expression.condition_invalid', $this->issueCodes($result));
    }

    public function test_contextual_assignee_and_kb_document_checks_use_current_tenant(): void
    {
        $workflow = $this->createWorkflowContext();

        $assigneeDefinition = $this->validDefinition();
        $assigneeDefinition['nodes'][0] = [
            'id' => 'review',
            'type' => 'task-node',
            'is_entry_point' => true,
            'is_terminal' => true,
            'config' => [
                'title' => 'Review request',
                'assignTo' => 999999,
                'inputFields' => [],
            ],
        ];

        $kbDefinition = $this->validDefinition();
        $kbDefinition['nodes'][0] = [
            'id' => 'classify',
            'type' => 'ai-classifier',
            'is_entry_point' => true,
            'is_terminal' => true,
            'config' => [
                'dimensions' => ['priority' => ['low', 'high']],
                'kbDocs' => [999999],
            ],
        ];

        $assigneeResult = $this->verify($assigneeDefinition, $workflow);
        $kbResult = $this->verify($kbDefinition, $workflow);

        $this->assertContains('context.assignee_unknown', $this->issueCodes($assigneeResult));
        $this->assertContains('context.kb_doc_unknown', $this->issueCodes($kbResult));
    }

    protected function verify(array $definition, ?Workflow $workflow = null): array
    {
        return $this->verificationService->verify($definition, $workflow)->toArray();
    }

    protected function issueCodes(array $result): array
    {
        return array_column($result['issues'], 'code');
    }

    protected function validDefinition(): array
    {
        return [
            'trigger' => [
                'type' => 'webhook-trigger',
                'config' => [
                    'webhookUrl' => 'employee-onboarding',
                ],
            ],
            'variables' => [],
            'nodes' => [
                [
                    'id' => 'send-welcome-email',
                    'type' => 'send-email',
                    'is_entry_point' => true,
                    'is_terminal' => true,
                    'config' => [
                        'from' => 'hr@example.test',
                        'to' => 'employee@example.test',
                        'subject' => 'Welcome',
                    ],
                ],
            ],
            'edges' => [],
            'settings' => [],
        ];
    }

    protected function twoNodeDefinition(): array
    {
        $definition = $this->validDefinition();
        $definition['nodes'] = [
            [
                'id' => 'start',
                'type' => 'set-variables',
                'is_entry_point' => true,
                'config' => [
                    'assignments' => ['amount' => 1200],
                ],
            ],
            [
                'id' => 'finish',
                'type' => 'send-email',
                'is_terminal' => true,
                'config' => [
                    'from' => 'hr@example.test',
                    'to' => 'employee@example.test',
                    'subject' => 'Welcome',
                ],
            ],
        ];
        $definition['edges'] = [
            [
                'id' => 'start-finish',
                'source' => 'start',
                'target' => 'finish',
            ],
        ];

        return $definition;
    }

    protected function createWorkflowContext(): Workflow
    {
        $tenant = Tenant::query()->create([
            'business_name' => 'Acme Inc',
            'business_type' => BusinessType::SaaS->value,
        ]);
        $manager = User::query()->create([
            'first_name' => 'Manager',
            'last_name' => 'User',
            'name' => 'Manager User',
            'email' => 'manager-'.uniqid().'@example.test',
            'password' => Hash::make('Pass1234!'),
            'tenant_id' => $tenant->id,
            'role' => Role::Manager,
            'is_active' => true,
        ]);
        $team = Team::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Automation',
            'manager_id' => $manager->id,
        ]);
        TeamMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $manager->id,
            'team_id' => $team->id,
            'status' => 'active',
        ]);

        return Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'created_by_id' => $manager->id,
            'name' => 'Employee Onboarding',
            'description' => 'Automated sequence for provisioning access',
            'status' => WorkflowStatus::Disabled,
            'draft_definition' => [
                'trigger' => null,
                'nodes' => [],
                'edges' => [],
                'settings' => [],
            ],
            'draft_revision' => 1,
        ]);
    }
}
