<?php

namespace Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;
use Modules\Team\Models\Team;
use Modules\Team\Models\TeamMembership;
use Modules\Workflows\Database\Seeders\NodeDefinitionSeeder;
use Modules\Workflows\Enums\DynamicFlowStatus;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowDynamicFlow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowVersion;
use Tests\TestCase;

class DynamicFlowSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NodeDefinitionSeeder::class);
    }

    public function test_submitting_a_designed_segment_spawns_a_child_instance_and_resumes_the_parent(): void
    {
        [$tenant, $manager, $team] = $this->tenantWithManager();
        $workflow = $this->publishedDynamicFlowWorkflow($tenant, $team, $manager);

        /** @var Authenticatable $auth */
        $auth = $manager;

        // Trigger the parent — it should run to the dynamic-flow node and pause.
        $triggerResponse = $this->actingAs($auth, 'api')
            ->postJson("/api/v1/workflows/{$workflow->id}/trigger/manual", [])
            ->assertAccepted();

        $instanceId = $triggerResponse->json('instance_id');
        $instance = WorkflowInstance::query()->findOrFail($instanceId);

        $this->assertSame(WorkflowInstanceStatus::Paused, $instance->status);
        $this->assertDatabaseHas('workflow_dynamic_flows', [
            'instance_id' => $instanceId,
            'status' => DynamicFlowStatus::AwaitingDesign->value,
        ]);

        // The manager designs a minimal sub-flow: dynamic-entry -> termination-node.
        $response = $this->actingAs($auth, 'api')
            ->postJson("/api/v1/workflows/instances/{$instanceId}/dynamic-flow/definition", [
                'definition' => [
                    'nodes' => [
                        ['id' => 'entry', 'type' => 'dynamic-entry', 'config' => [], 'position' => ['x' => 0, 'y' => 0]],
                        ['id' => 'seg-term', 'type' => 'termination-node', 'config' => [], 'position' => ['x' => 0, 'y' => 200]],
                    ],
                    'edges' => [
                        ['id' => 'seg-e1', 'source_node_key' => 'entry', 'target_node_key' => 'seg-term'],
                    ],
                    'variables' => [],
                    'settings' => [],
                ],
            ]);

        $response->assertCreated();

        $dynamicFlow = WorkflowDynamicFlow::query()->where('instance_id', $instanceId)->firstOrFail();
        $this->assertNotNull($dynamicFlow->child_instance_id, 'A child instance should have been created.');

        $child = WorkflowInstance::query()->findOrFail($dynamicFlow->child_instance_id);
        $this->assertSame(WorkflowInstanceStatus::Completed, $child->status, 'Child instance should run to completion.');
        $this->assertSame($instanceId, (int) $child->parent_instance_id);

        $instance->refresh();
        $this->assertSame(
            WorkflowInstanceStatus::Completed,
            $instance->status,
            "Parent should resume and finish after the child completes, got {$instance->status->value}.",
        );
        $this->assertSame(DynamicFlowStatus::Completed, $dynamicFlow->fresh()->status);
    }

    public function test_parent_context_flows_into_the_segment_and_the_output_variable_flows_back(): void
    {
        [$tenant, $manager, $team] = $this->tenantWithManager();
        $workflow = $this->publishedDynamicFlowWorkflow($tenant, $team, $manager);

        /** @var Authenticatable $auth */
        $auth = $manager;

        $instanceId = $this->actingAs($auth, 'api')
            ->postJson("/api/v1/workflows/{$workflow->id}/trigger/manual", ['hrEmail' => 'hr@acme.test'])
            ->assertAccepted()
            ->json('instance_id');

        $this->actingAs($auth, 'api')
            ->postJson("/api/v1/workflows/instances/{$instanceId}/dynamic-flow/definition", [
                'definition' => [
                    'nodes' => [
                        ['id' => 'entry', 'type' => 'dynamic-entry', 'config' => []],
                        ['id' => 'seg-term', 'type' => 'termination-node', 'config' => []],
                    ],
                    'edges' => [
                        ['id' => 'seg-e1', 'source_node_key' => 'entry', 'target_node_key' => 'seg-term'],
                    ],
                    'variables' => [],
                    'settings' => [],
                ],
            ])
            ->assertCreated();

        $dynamicFlow = WorkflowDynamicFlow::query()->where('instance_id', $instanceId)->firstOrFail();
        $child = WorkflowInstance::query()->findOrFail($dynamicFlow->child_instance_id);

        // Parent context reached the child via the dynamic-entry trigger.
        $this->assertSame('hr@acme.test', data_get($child->context, 'hrEmail'));

        // Child context came back to the parent under the node's outputVariable ("subResult").
        $parent = WorkflowInstance::query()->findOrFail($instanceId);
        $this->assertSame('hr@acme.test', data_get($parent->context, 'subResult.hrEmail'));
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Team}
     */
    private function tenantWithManager(): array
    {
        $tenant = Tenant::query()->create([
            'business_name' => 'Acme Inc',
            'business_type' => BusinessType::SaaS->value,
        ]);

        $manager = User::query()->create([
            'first_name' => 'Manny',
            'last_name' => 'Ager',
            'name' => 'Manny Ager',
            'email' => 'manager-'.uniqid().'@example.test',
            'password' => Hash::make('Pass1234!'),
            'tenant_id' => $tenant->id,
            'role' => Role::Manager,
            'is_active' => true,
        ]);

        $team = Team::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Operations',
            'manager_id' => $manager->id,
        ]);

        TeamMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $manager->id,
            'team_id' => $team->id,
            'status' => 'active',
        ]);

        return [$tenant, $manager, $team];
    }

    private function publishedDynamicFlowWorkflow(Tenant $tenant, Team $team, User $manager): Workflow
    {
        $definition = [
            'trigger' => ['type' => 'manual-trigger', 'config' => []],
            'nodes' => [
                ['id' => 'trigger', 'type' => 'manual-trigger', 'config' => []],
                ['id' => 'df', 'type' => 'dynamic-flow', 'config' => ['message' => 'Design it', 'outputVariable' => 'subResult']],
                ['id' => 'term', 'type' => 'termination-node', 'config' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source_node_key' => 'trigger', 'target_node_key' => 'df'],
                ['id' => 'e2', 'source_node_key' => 'df', 'target_node_key' => 'term'],
            ],
            'variables' => [],
            'settings' => [],
        ];

        $workflow = Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'created_by_id' => $manager->id,
            'name' => 'Dynamic Flow Workflow',
            'description' => 'Exercises the dynamic-flow submit path',
            'status' => WorkflowStatus::Active->value,
            'draft_definition' => $definition,
            'draft_revision' => 1,
        ]);

        $version = WorkflowVersion::query()->create([
            'workflow_id' => $workflow->id,
            'tenant_id' => $tenant->id,
            'version_number' => 1,
            'version_label' => 'v1.0.0',
            'definition' => $definition,
            'release_note' => null,
            'published_by_id' => $manager->id,
            'published_at' => now(),
        ]);

        $workflow->forceFill(['current_version_id' => $version->id])->save();

        return $workflow;
    }
}
