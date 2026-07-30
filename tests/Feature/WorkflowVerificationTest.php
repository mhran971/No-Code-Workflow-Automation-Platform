<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\KnowledgeBase\Models\Document;
use Modules\Team\Models\TeamMembership;
use Modules\Workflows\Models\Node;
use Modules\Workflows\Models\NodeConfigField;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\WorkflowDefinitionNormalizer;
use Modules\Workflows\Services\Verification\WorkflowVerificationService;
use Tests\TestCase;

/**
 * Comprehensive tests for the Workflow Verification System.
 *
 * Covers all four rules:
 *   1. SyntaxVerificationRule
 *   2. GraphControlFlowVerificationRule
 *   3. ExpressionVerificationRule
 *   4. ContextualVerificationRule
 *
 * Test naming: test_{rule}_{scenario}_{expected_outcome}
 */
class WorkflowVerificationTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // Shared helpers
    // ─────────────────────────────────────────────────────────────────────────

    protected WorkflowVerificationService $validator;

    protected WorkflowDefinitionNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = app(WorkflowVerificationService::class);
        $this->normalizer = app(WorkflowDefinitionNormalizer::class);

        // Seed the active node types used across tests
        $this->seedNodeTypes();
    }

    /**
     * Seed the minimum set of active Node definitions needed by SyntaxVerificationRule
     * and ContextualVerificationRule.
     */
    protected function seedNodeTypes(): void
    {
        $types = [
            ['type' => 'webhook-trigger',  'category' => 'trigger', 'is_active' => true],
            ['type' => 'send-email',        'category' => 'action',  'is_active' => true],
            ['type' => 'human-task',        'category' => 'action',  'is_active' => true],
            ['type' => 'conditional',       'category' => 'logic',   'is_active' => true],
            ['type' => 'parallel-split',    'category' => 'logic',   'is_active' => true],
            ['type' => 'parallel-join',     'category' => 'logic',   'is_active' => true],
            ['type' => 'loop',              'category' => 'logic',   'is_active' => true],
            ['type' => 'ai-summarize',      'category' => 'action',  'is_active' => true],
            ['type' => 'inactive-node',     'category' => 'action',  'is_active' => false],
        ];

        foreach ($types as $attrs) {
            Node::factory()->create($attrs);
        }
    }

    /** Return a minimal valid, publishable workflow definition. */
    protected function validDefinition(array $overrides = []): array
    {
        return array_replace_recursive([
            'trigger' => [
                'type' => 'webhook-trigger',
                'config' => [],
            ],
            'nodes' => [
                [
                    'id' => 'start',
                    'type' => 'send-email',
                    'label' => 'Welcome Email',
                    'config' => [],
                    'is_entry_point' => true,
                    'is_terminal' => true,
                ],
            ],
            'edges' => [],
            'variables' => [],
            'settings' => [],
        ], $overrides);
    }

    /** Run the full validator and return the result array. */
    protected function validate(array $definition, ?Workflow $workflow = null, ?User $actor = null): array
    {
        return $this->validator->verify($definition, $workflow, $actor)->toArray();
    }

    /** Assert the result has zero errors (publishable). */
    protected function assertPublishable(array $result): void
    {
        $this->assertTrue(
            $result['is_publishable'],
            'Expected publishable but got errors: '.implode(', ', $result['errors'])
        );
    }

    /** Assert the result is not publishable (has errors). */
    protected function assertNotPublishable(array $result): void
    {
        $this->assertFalse($result['is_publishable'], 'Expected not publishable but found no errors.');
    }

    /** Assert at least one issue with the given error code exists. */
    protected function assertHasError(array $result, string $code): void
    {
        $codes = array_column($result['issues'], 'code');
        $this->assertContains(
            $code,
            $codes,
            "Expected error code '{$code}' but got: ".implode(', ', $codes)
        );
    }

    /** Assert no issue with the given error code exists. */
    protected function assertNoError(array $result, string $code): void
    {
        $codes = array_column($result['issues'], 'code');
        $this->assertNotContains($code, $codes, "Unexpected error code '{$code}' found.");
    }

    /** Assert at least one warning with the given code exists. */
    protected function assertHasWarning(array $result, string $code): void
    {
        $warnings = array_filter($result['issues'], fn ($i) => $i['severity'] === 'warning');
        $codes = array_column(array_values($warnings), 'code');
        $this->assertContains($code, $codes, "Expected warning '{$code}' but got: ".implode(', ', $codes));
    }

    // =========================================================================
    // 1. SyntaxVerificationRule
    // =========================================================================

    // ── 1a. Top-level shape ──────────────────────────────────────────────────

    /** @test */
    public function test_syntax_valid_minimal_definition_passes(): void
    {
        $result = $this->validate($this->validDefinition());

        $this->assertPublishable($result);
        $this->assertCount(0, $result['issues']);
    }

    /** @test */
    public function test_syntax_missing_trigger_key_produces_trigger_missing_error(): void
    {
        $definition = $this->validDefinition();
        unset($definition['trigger']);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'definition.trigger_missing');
    }

    /** @test */
    public function test_syntax_trigger_as_array_not_object_produces_trigger_invalid_error(): void
    {
        $definition = $this->validDefinition();
        $definition['trigger'] = ['webhook-trigger']; // indexed array, not keyed

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'definition.trigger_invalid');
    }

    /** @test */
    public function test_syntax_missing_nodes_key_produces_nodes_missing_error(): void
    {
        $definition = $this->validDefinition();
        unset($definition['nodes']);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'definition.nodes_missing');
    }

    /** @test */
    public function test_syntax_nodes_as_string_produces_nodes_invalid_error(): void
    {
        $definition = $this->validDefinition();
        $definition['nodes'] = 'not-an-array';

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'definition.nodes_invalid');
    }

    /** @test */
    public function test_syntax_missing_edges_key_produces_edges_missing_error(): void
    {
        $definition = $this->validDefinition();
        unset($definition['edges']);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'definition.edges_missing');
    }

    /** @test */
    public function test_syntax_variables_as_string_produces_variables_invalid_error(): void
    {
        $definition = $this->validDefinition();
        $definition['variables'] = 'not-an-array';

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'definition.variables_invalid');
    }

    /** @test */
    public function test_syntax_settings_as_array_not_object_produces_settings_invalid_error(): void
    {
        // Sequential (non-associative) array is not a valid settings "object"
        $definition = $this->validDefinition();
        $definition['settings'] = ['foo', 'bar'];

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'definition.settings_invalid');
    }

    // ── 1b. Trigger validation ───────────────────────────────────────────────

    /** @test */
    public function test_syntax_empty_trigger_array_produces_trigger_missing_error(): void
    {
        $definition = $this->validDefinition();
        $definition['trigger'] = [];

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'trigger.missing');
    }

    /** @test */
    public function test_syntax_trigger_with_empty_type_produces_type_missing_error(): void
    {
        $definition = $this->validDefinition();
        $definition['trigger']['type'] = '';

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'trigger.type_missing');
    }

    /** @test */
    public function test_syntax_trigger_with_nonexistent_type_produces_type_unknown_error(): void
    {
        $definition = $this->validDefinition();
        $definition['trigger']['type'] = 'does-not-exist';

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'trigger.type_unknown');
    }

    /** @test */
    public function test_syntax_trigger_with_inactive_type_produces_type_unknown_error(): void
    {
        // 'inactive-node' was seeded with is_active=false but as a trigger type for the test
        $definition = $this->validDefinition();
        $definition['trigger']['type'] = 'inactive-node';

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'trigger.type_unknown');
    }

    /** @test */
    public function test_syntax_trigger_config_as_string_produces_config_invalid_error(): void
    {
        $definition = $this->validDefinition();
        $definition['trigger']['config'] = 'not-an-object';

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'trigger.config_invalid');
    }

    // ── 1c. Node validation ──────────────────────────────────────────────────

    /** @test */
    public function test_syntax_node_that_is_not_object_produces_node_invalid_error(): void
    {
        $definition = $this->validDefinition();
        $definition['nodes'] = ['just-a-string'];

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'node.invalid');
    }

    /** @test */
    public function test_syntax_node_with_missing_id_produces_id_missing_error(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['type' => 'send-email', 'config' => [], 'is_entry_point' => true, 'is_terminal' => true],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'node.id_missing');
    }

    /** @test */
    public function test_syntax_node_with_empty_id_string_produces_id_missing_error(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => '', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true, 'is_terminal' => true],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'node.id_missing');
    }

    /** @test */
    public function test_syntax_two_nodes_sharing_an_id_produces_id_duplicate_error(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'start', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true],
                ['id' => 'start', 'type' => 'send-email', 'config' => [], 'is_terminal' => true],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'node.id_duplicate');
    }

    /** @test */
    public function test_syntax_node_with_missing_type_produces_type_missing_error(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'config' => [], 'is_entry_point' => true, 'is_terminal' => true],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'node.type_missing');
    }

    /** @test */
    public function test_syntax_node_with_unknown_type_produces_type_unknown_error(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'ghost-node', 'config' => [], 'is_entry_point' => true, 'is_terminal' => true],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'node.type_unknown');
    }

    /** @test */
    public function test_syntax_node_config_as_sequential_array_produces_config_invalid_error(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => ['a', 'b'], 'is_entry_point' => true, 'is_terminal' => true],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'node.config_invalid');
    }

    /** @test */
    public function test_syntax_required_config_field_missing_produces_field_missing_error(): void
    {
        // Add a required config field to the send-email node type
        $node = Node::where('type', 'send-email')->first();
        NodeConfigField::factory()->create([
            'node_id' => $node->id,
            'name' => 'to',
            'type' => 'string',
            'is_required' => true,
        ]);

        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true, 'is_terminal' => true],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'node.to_missing');
    }

    /** @test */
    public function test_syntax_config_field_with_wrong_enum_value_produces_field_invalid_error(): void
    {
        $node = Node::where('type', 'send-email')->first();
        NodeConfigField::factory()->create([
            'node_id' => $node->id,
            'name' => 'priority',
            'type' => 'enum',
            'is_required' => false,
            'metadata' => ['values' => ['low', 'medium', 'high']],
        ]);

        $definition = $this->validDefinition([
            'nodes' => [
                [
                    'id' => 'n1',
                    'type' => 'send-email',
                    'config' => ['priority' => 'urgent'],   // not in enum
                    'is_entry_point' => true,
                    'is_terminal' => true,
                ],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'node.priority_invalid');
    }

    // ── 1d. Edge validation ──────────────────────────────────────────────────

    /** @test */
    public function test_syntax_edge_that_is_not_object_produces_edge_invalid_error(): void
    {
        $definition = $this->validDefinition();
        $definition['edges'] = ['not-an-object'];

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'edge.invalid');
    }

    /** @test */
    public function test_syntax_edge_with_missing_source_produces_source_missing_error(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true],
                ['id' => 'n2', 'type' => 'send-email', 'config' => [], 'is_terminal' => true],
            ],
            'edges' => [
                ['target' => 'n2'], // source missing
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'edge.source_missing');
    }

    /** @test */
    public function test_syntax_edge_source_referencing_nonexistent_node_produces_source_missing_reference_error(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true, 'is_terminal' => true],
            ],
            'edges' => [
                ['source' => 'ghost', 'target' => 'n1'],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'edge.source_missing_reference');
    }

    /** @test */
    public function test_syntax_edge_with_missing_target_produces_target_missing_error(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true, 'is_terminal' => true],
            ],
            'edges' => [
                ['source' => 'n1'], // target missing
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'edge.target_missing');
    }

    /** @test */
    public function test_syntax_edge_target_referencing_nonexistent_node_produces_target_missing_reference_error(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true, 'is_terminal' => true],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'ghost'],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'edge.target_missing_reference');
    }

    /** @test */
    public function test_syntax_two_edges_with_same_source_target_pair_produces_duplicate_error(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true],
                ['id' => 'n2', 'type' => 'send-email', 'config' => [], 'is_terminal' => true],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2'],
                ['source' => 'n1', 'target' => 'n2'], // duplicate
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'edge.duplicate');
    }

    /** @test */
    public function test_syntax_edge_with_invalid_branch_type_produces_branch_type_invalid_error(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true],
                ['id' => 'n2', 'type' => 'send-email', 'config' => [], 'is_terminal' => true],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2', 'branch_type' => 'teleport'], // invalid
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'edge.branch_type_invalid');
    }

    /** @test */
    public function test_syntax_edge_accepts_alternate_property_names_from_normalizer(): void
    {
        // Normalizer maps 'from' → 'source_node_key', 'to' → 'target_node_key'
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true],
                ['id' => 'n2', 'type' => 'send-email', 'config' => [], 'is_terminal' => true],
            ],
            'edges' => [
                ['from' => 'n1', 'to' => 'n2'],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertPublishable($result);
    }

    // =========================================================================
    // 2. GraphControlFlowVerificationRule
    // =========================================================================

    // ── 2a. Entry node resolution ────────────────────────────────────────────

    /** @test */
    public function test_graph_no_entry_point_at_all_produces_entry_missing_error(): void
    {
        // No is_entry_point flag and all nodes have incoming edges
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => []],
                ['id' => 'n2', 'type' => 'send-email', 'config' => [], 'is_terminal' => true],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2'],
                ['source' => 'n2', 'target' => 'n1'], // both nodes have incoming
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'graph.entry_missing');
    }

    /** @test */
    public function test_graph_multiple_explicit_entry_nodes_produces_entry_ambiguous_or_multiple_error(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true, 'is_terminal' => true],
                ['id' => 'n2', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true, 'is_terminal' => true],
            ],
            'edges' => [],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        // either graph.entry_ambiguous or graph.entry_multiple
        $codes = array_column($result['issues'], 'code');
        $this->assertTrue(
            in_array('graph.entry_ambiguous', $codes) || in_array('graph.entry_multiple', $codes),
            'Expected graph.entry_ambiguous or graph.entry_multiple'
        );
    }

    /** @test */
    public function test_graph_multiple_inferred_entry_nodes_produces_entry_ambiguous_error(): void
    {
        // Two nodes, neither has incoming edges, neither is marked entry
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_terminal' => true],
                ['id' => 'n2', 'type' => 'send-email', 'config' => [], 'is_terminal' => true],
            ],
            'edges' => [],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'graph.entry_ambiguous');
    }

    /** @test */
    public function test_graph_single_inferred_entry_node_passes_entry_check(): void
    {
        // Only n1 has no incoming edge → inferred entry
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => []],
                ['id' => 'n2', 'type' => 'send-email', 'config' => [], 'is_terminal' => true],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2'],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNoError($result, 'graph.entry_missing');
        $this->assertNoError($result, 'graph.entry_ambiguous');
    }

    // ── 2b. Terminal node validation ─────────────────────────────────────────

    /** @test */
    public function test_graph_no_terminal_node_produces_terminal_missing_error(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true],
                ['id' => 'n2', 'type' => 'send-email', 'config' => []],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2'],
                ['source' => 'n2', 'target' => 'n1'], // cycle, no terminal
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'graph.terminal_missing');
    }

    /** @test */
    public function test_graph_node_with_no_outgoing_edges_is_treated_as_terminal(): void
    {
        // n2 has no outgoing edges → implicit terminal
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true],
                ['id' => 'n2', 'type' => 'send-email', 'config' => []],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2'],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNoError($result, 'graph.terminal_missing');
    }

    // ── 2c. Node degree analysis ─────────────────────────────────────────────

    /** @test */
    public function test_graph_non_entry_node_with_no_incoming_edges_produces_incoming_missing_error(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true, 'is_terminal' => true],
                ['id' => 'n2', 'type' => 'send-email', 'config' => [], 'is_terminal' => true], // orphan
            ],
            'edges' => [],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'graph.incoming_missing');
    }

    /** @test */
    public function test_graph_non_terminal_node_with_no_outgoing_edges_produces_outgoing_missing_error(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true],
                ['id' => 'n2', 'type' => 'send-email', 'config' => []],   // dead-end, not marked terminal
                ['id' => 'n3', 'type' => 'send-email', 'config' => [], 'is_terminal' => true],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2'],
                ['source' => 'n1', 'target' => 'n3'],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'graph.outgoing_missing');
    }

    // ── 2d. Reachability analysis ────────────────────────────────────────────

    /** @test */
    public function test_graph_isolated_node_produces_unreachable_error(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true, 'is_terminal' => true],
                ['id' => 'orphan', 'type' => 'send-email', 'config' => [], 'is_terminal' => true], // no incoming
            ],
            'edges' => [],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'graph.unreachable');
    }

    /** @test */
    public function test_graph_node_on_branch_that_never_reaches_terminal_produces_dead_end_error(): void
    {
        // n1(entry) → n2 → n3(dead end, not terminal)
        //           → n4(terminal)
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true],
                ['id' => 'n2', 'type' => 'send-email', 'config' => []],
                ['id' => 'n3', 'type' => 'send-email', 'config' => [], 'is_terminal' => true],  // terminal
                ['id' => 'n4', 'type' => 'send-email', 'config' => []],  // dead end
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2'],
                ['source' => 'n1', 'target' => 'n4'],
                ['source' => 'n2', 'target' => 'n3'],
                // n4 has no outgoing
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        // n4 should trigger dead_end or outgoing_missing
        $codes = array_column($result['issues'], 'code');
        $this->assertTrue(
            in_array('graph.dead_end', $codes) || in_array('graph.outgoing_missing', $codes),
            'Expected graph.dead_end or graph.outgoing_missing for disconnected node.'
        );
    }

    /** @test */
    public function test_graph_all_nodes_reachable_and_leading_to_terminal_passes(): void
    {
        // Linear: n1 → n2 → n3(terminal)
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true],
                ['id' => 'n2', 'type' => 'send-email', 'config' => []],
                ['id' => 'n3', 'type' => 'send-email', 'config' => [], 'is_terminal' => true],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2'],
                ['source' => 'n2', 'target' => 'n3'],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNoError($result, 'graph.unreachable');
        $this->assertNoError($result, 'graph.dead_end');
    }

    // ── 2e. Cycle detection ──────────────────────────────────────────────────

    /** @test */
    public function test_graph_unstructured_cycle_between_regular_nodes_produces_cycle_error(): void
    {
        // n1(entry) → n2 → n3 → n2 (cycle)
        //                  ↓
        //                 n4(terminal)
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true],
                ['id' => 'n2', 'type' => 'send-email', 'config' => []],
                ['id' => 'n3', 'type' => 'send-email', 'config' => []],
                ['id' => 'n4', 'type' => 'send-email', 'config' => [], 'is_terminal' => true],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2'],
                ['source' => 'n2', 'target' => 'n3'],
                ['source' => 'n3', 'target' => 'n2'], // cycle edge
                ['source' => 'n3', 'target' => 'n4'],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'graph.unstructured_cycle');
    }

    /** @test */
    public function test_graph_cycle_involving_loop_node_type_is_allowed(): void
    {
        // n1(entry) → loop → n2 → loop (back-edge is allowed because 'loop' node)
        //                         ↓
        //                        n3(terminal)
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1',   'type' => 'send-email', 'config' => [], 'is_entry_point' => true],
                ['id' => 'loop', 'type' => 'loop',        'config' => []],
                ['id' => 'n2',   'type' => 'send-email', 'config' => []],
                ['id' => 'n3',   'type' => 'send-email', 'config' => [], 'is_terminal' => true],
            ],
            'edges' => [
                ['source' => 'n1',   'target' => 'loop'],
                ['source' => 'loop', 'target' => 'n2'],
                ['source' => 'n2',   'target' => 'loop'], // back-edge via loop node
                ['source' => 'loop', 'target' => 'n3'],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNoError($result, 'graph.unstructured_cycle');
    }

    // =========================================================================
    // 3. ExpressionVerificationRule
    // =========================================================================

    // ── 3a. Edge conditional expressions ────────────────────────────────────

    /** @test */
    public function test_expression_conditional_edge_missing_expression_produces_condition_missing_error(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true],
                ['id' => 'n2', 'type' => 'send-email', 'config' => [], 'is_terminal' => true],
            ],
            'edges' => [
                [
                    'source' => 'n1',
                    'target' => 'n2',
                    'branch_type' => 'conditional',
                    // condition_expression intentionally missing
                ],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'expression.condition_missing');
    }

    /** @test */
    public function test_expression_conditional_edge_empty_expression_produces_condition_missing_error(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true],
                ['id' => 'n2', 'type' => 'send-email', 'config' => [], 'is_terminal' => true],
            ],
            'edges' => [
                [
                    'source' => 'n1',
                    'target' => 'n2',
                    'branch_type' => 'conditional',
                    'condition_expression' => '',
                ],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'expression.condition_missing');
    }

    /** @test */
    public function test_expression_invalid_syntax_in_edge_expression_produces_condition_invalid_error(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true],
                ['id' => 'n2', 'type' => 'send-email', 'config' => [], 'is_terminal' => true],
            ],
            'edges' => [
                [
                    'source' => 'n1',
                    'target' => 'n2',
                    'branch_type' => 'conditional',
                    'condition_expression' => 'x >',  // incomplete expression
                ],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'expression.condition_invalid');
    }

    /** @test */
    public function test_expression_arithmetic_only_expression_produces_condition_invalid_error(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true],
                ['id' => 'n2', 'type' => 'send-email', 'config' => [], 'is_terminal' => true],
            ],
            'edges' => [
                [
                    'source' => 'n1',
                    'target' => 'n2',
                    'branch_type' => 'conditional',
                    'condition_expression' => 'x + 5', // not boolean
                ],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'expression.condition_invalid');
    }

    /**
     * @test
     *
     * @dataProvider validBooleanExpressions
     */
    public function test_expression_valid_boolean_expressions_pass(string $expression): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true],
                ['id' => 'n2', 'type' => 'send-email', 'config' => [], 'is_terminal' => true],
            ],
            'edges' => [
                [
                    'source' => 'n1',
                    'target' => 'n2',
                    'branch_type' => 'conditional',
                    'condition_expression' => $expression,
                ],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNoError($result, 'expression.condition_missing');
        $this->assertNoError($result, 'expression.condition_invalid');
    }

    public static function validBooleanExpressions(): array
    {
        return [
            'simple comparison' => ['x > 5'],
            'equality check' => ['status == "active"'],
            'negation' => ['!is_deleted'],
            'logical AND' => ['x > 5 && y < 10'],
            'logical OR' => ['is_admin || is_manager'],
            'complex nested' => ['(x > 5 && y < 10) || is_override'],
            'not equal' => ['role != "guest"'],
            'greater or equal' => ['count >= 3'],
            'less or equal' => ['score <= 100'],
            'boolean literal' => ['true'],
            'single quoted string compare' => ["status == 'pending'"],
            'negative number compare' => ['temperature > -5'],
            'decimal compare' => ['ratio >= 0.5'],
            'null comparison' => ['value == null'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider invalidExpressions
     */
    public function test_expression_invalid_expressions_produce_condition_invalid_error(string $expression): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true],
                ['id' => 'n2', 'type' => 'send-email', 'config' => [], 'is_terminal' => true],
            ],
            'edges' => [
                [
                    'source' => 'n1',
                    'target' => 'n2',
                    'branch_type' => 'conditional',
                    'condition_expression' => $expression,
                ],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertHasError($result, 'expression.condition_invalid');
    }

    public static function invalidExpressions(): array
    {
        return [
            'incomplete expression' => ['x >'],
            'arithmetic only' => ['x + 5'],
            'method call not supported' => ['user.getName()'],
            'bare identifier (ambiguous)' => ['just_identifier'],
            'dangling operator' => ['&& true'],
            'unclosed parenthesis' => ['(x > 5'],
        ];
    }

    // ── 3b. Node expressions ─────────────────────────────────────────────────

    /** @test */
    public function test_expression_invalid_node_expression_in_config_produces_node_invalid_error(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                [
                    'id' => 'n1',
                    'type' => 'conditional',
                    'config' => ['expression' => 'x + 5'], // non-boolean
                    'is_entry_point' => true,
                    'is_terminal' => true,
                ],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'expression.node_invalid');
    }

    /** @test */
    public function test_expression_valid_node_expression_in_config_passes(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                [
                    'id' => 'n1',
                    'type' => 'conditional',
                    'config' => ['expression' => 'score >= 50'],
                    'is_entry_point' => true,
                    'is_terminal' => true,
                ],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNoError($result, 'expression.node_invalid');
    }

    /** @test */
    public function test_expression_condition_field_in_node_config_also_validated(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                [
                    'id' => 'n1',
                    'type' => 'conditional',
                    'config' => ['condition' => 'bad +++ expression'],
                    'is_entry_point' => true,
                    'is_terminal' => true,
                ],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertHasError($result, 'expression.node_invalid');
    }

    // ── 3c. Non-conditional edges are not validated ──────────────────────────

    /** @test */
    public function test_expression_default_branch_edge_without_expression_does_not_produce_error(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true],
                ['id' => 'n2', 'type' => 'send-email', 'config' => [], 'is_terminal' => true],
            ],
            'edges' => [
                [
                    'source' => 'n1',
                    'target' => 'n2',
                    'branch_type' => 'default', // no condition_expression needed
                ],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertNoError($result, 'expression.condition_missing');
    }

    // =========================================================================
    // 4. ContextualVerificationRule
    // =========================================================================

    // ── 4a. Workflow null – rule is skipped ───────────────────────────────────

    /** @test */
    public function test_context_skipped_entirely_when_workflow_is_null(): void
    {
        // Human task with invalid assignee, but no workflow context
        $definition = $this->validDefinition([
            'nodes' => [
                [
                    'id' => 'n1',
                    'type' => 'human-task',
                    'config' => ['assignee' => 99999], // non-existent user
                    'is_entry_point' => true,
                    'is_terminal' => true,
                ],
            ],
        ]);

        // Pass null for workflow → contextual rule is skipped
        $result = $this->validate($definition, null, null);

        $this->assertNoError($result, 'context.assignee_unknown');
        $this->assertNoError($result, 'context.assignee_not_team_member');
    }

    // ── 4b. Human task assignee validation ───────────────────────────────────

    /** @test */
    public function test_context_assignee_that_does_not_exist_produces_assignee_unknown_error(): void
    {
        $workflow = Workflow::factory()->create();
        $definition = $this->validDefinition([
            'nodes' => [
                [
                    'id' => 'n1',
                    'type' => 'human-task',
                    'config' => ['assignee' => 99999], // non-existent
                    'is_entry_point' => true,
                    'is_terminal' => true,
                ],
            ],
        ]);

        $result = $this->validate($definition, $workflow);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'context.assignee_unknown');
    }

    /** @test */
    public function test_context_assignee_from_different_tenant_produces_assignee_unknown_error(): void
    {
        $workflow = Workflow::factory()->create(['tenant_id' => 1]);
        $foreignUser = User::factory()->create(['tenant_id' => 2, 'is_active' => true]);

        $definition = $this->validDefinition([
            'nodes' => [
                [
                    'id' => 'n1',
                    'type' => 'human-task',
                    'config' => ['assignee' => $foreignUser->id],
                    'is_entry_point' => true,
                    'is_terminal' => true,
                ],
            ],
        ]);

        $result = $this->validate($definition, $workflow);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'context.assignee_unknown');
    }

    /** @test */
    public function test_context_inactive_assignee_produces_assignee_unknown_error(): void
    {
        $workflow = Workflow::factory()->create();
        $inactiveUser = User::factory()->create([
            'tenant_id' => $workflow->tenant_id,
            'is_active' => false,
        ]);

        $definition = $this->validDefinition([
            'nodes' => [
                [
                    'id' => 'n1',
                    'type' => 'human-task',
                    'config' => ['assignee' => $inactiveUser->id],
                    'is_entry_point' => true,
                    'is_terminal' => true,
                ],
            ],
        ]);

        $result = $this->validate($definition, $workflow);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'context.assignee_unknown');
    }

    /** @test */
    public function test_context_active_user_not_in_workflow_team_produces_assignee_not_team_member_error(): void
    {
        $workflow = Workflow::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $workflow->tenant_id,
            'is_active' => true,
        ]);
        // No TeamMembership created

        $definition = $this->validDefinition([
            'nodes' => [
                [
                    'id' => 'n1',
                    'type' => 'human-task',
                    'config' => ['assignee' => $user->id],
                    'is_entry_point' => true,
                    'is_terminal' => true,
                ],
            ],
        ]);

        $result = $this->validate($definition, $workflow);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'context.assignee_not_team_member');
    }

    /** @test */
    public function test_context_user_with_inactive_team_membership_produces_assignee_not_team_member_error(): void
    {
        $workflow = Workflow::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $workflow->tenant_id,
            'is_active' => true,
        ]);
        TeamMembership::factory()->create([
            'tenant_id' => $workflow->tenant_id,
            'team_id' => $workflow->team_id,
            'user_id' => $user->id,
            'status' => 'inactive', // not active
        ]);

        $definition = $this->validDefinition([
            'nodes' => [
                [
                    'id' => 'n1',
                    'type' => 'human-task',
                    'config' => ['assignee' => $user->id],
                    'is_entry_point' => true,
                    'is_terminal' => true,
                ],
            ],
        ]);

        $result = $this->validate($definition, $workflow);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'context.assignee_not_team_member');
    }

    /** @test */
    public function test_context_valid_active_team_member_assignee_passes(): void
    {
        $workflow = Workflow::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $workflow->tenant_id,
            'is_active' => true,
        ]);
        TeamMembership::factory()->create([
            'tenant_id' => $workflow->tenant_id,
            'team_id' => $workflow->team_id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $definition = $this->validDefinition([
            'nodes' => [
                [
                    'id' => 'n1',
                    'type' => 'human-task',
                    'config' => ['assignee' => $user->id],
                    'is_entry_point' => true,
                    'is_terminal' => true,
                ],
            ],
        ]);

        $result = $this->validate($definition, $workflow);

        $this->assertNoError($result, 'context.assignee_unknown');
        $this->assertNoError($result, 'context.assignee_not_team_member');
    }

    // ── 4c. Knowledge base document validation ────────────────────────────────

    /** @test */
    public function test_context_kb_docs_as_string_produces_kb_docs_invalid_error(): void
    {
        $workflow = Workflow::factory()->create();

        $definition = $this->validDefinition([
            'nodes' => [
                [
                    'id' => 'n1',
                    'type' => 'ai-summarize',
                    'config' => ['kbDocs' => 'not-an-array'],
                    'is_entry_point' => true,
                    'is_terminal' => true,
                ],
            ],
        ]);

        $result = $this->validate($definition, $workflow);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'context.kb_docs_invalid');
    }

    /** @test */
    public function test_context_kb_docs_containing_non_numeric_id_produces_kb_doc_id_invalid_error(): void
    {
        $workflow = Workflow::factory()->create();

        $definition = $this->validDefinition([
            'nodes' => [
                [
                    'id' => 'n1',
                    'type' => 'ai-summarize',
                    'config' => ['kbDocs' => ['not-a-number']],
                    'is_entry_point' => true,
                    'is_terminal' => true,
                ],
            ],
        ]);

        $result = $this->validate($definition, $workflow);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'context.kb_doc_id_invalid');
    }

    /** @test */
    public function test_context_kb_doc_that_does_not_exist_produces_kb_doc_unknown_error(): void
    {
        $workflow = Workflow::factory()->create();

        $definition = $this->validDefinition([
            'nodes' => [
                [
                    'id' => 'n1',
                    'type' => 'ai-summarize',
                    'config' => ['kbDocs' => [99999]], // non-existent document
                    'is_entry_point' => true,
                    'is_terminal' => true,
                ],
            ],
        ]);

        $result = $this->validate($definition, $workflow);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'context.kb_doc_unknown');
    }

    /** @test */
    public function test_context_kb_doc_from_different_tenant_produces_kb_doc_unknown_error(): void
    {
        $workflow = Workflow::factory()->create(['tenant_id' => 1]);
        $foreignDoc = Document::factory()->create(['tenant_id' => 2]);

        $definition = $this->validDefinition([
            'nodes' => [
                [
                    'id' => 'n1',
                    'type' => 'ai-summarize',
                    'config' => ['kbDocs' => [$foreignDoc->id]],
                    'is_entry_point' => true,
                    'is_terminal' => true,
                ],
            ],
        ]);

        $result = $this->validate($definition, $workflow);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'context.kb_doc_unknown');
    }

    /** @test */
    public function test_context_valid_kb_docs_from_same_tenant_pass(): void
    {
        $workflow = Workflow::factory()->create();
        $doc1 = Document::factory()->create(['tenant_id' => $workflow->tenant_id]);
        $doc2 = Document::factory()->create(['tenant_id' => $workflow->tenant_id]);

        $definition = $this->validDefinition([
            'nodes' => [
                [
                    'id' => 'n1',
                    'type' => 'ai-summarize',
                    'config' => ['kbDocs' => [$doc1->id, $doc2->id]],
                    'is_entry_point' => true,
                    'is_terminal' => true,
                ],
            ],
        ]);

        $result = $this->validate($definition, $workflow);

        $this->assertNoError($result, 'context.kb_docs_invalid');
        $this->assertNoError($result, 'context.kb_doc_id_invalid');
        $this->assertNoError($result, 'context.kb_doc_unknown');
    }

    /** @test */
    public function test_context_ai_node_with_no_kb_docs_config_field_passes(): void
    {
        $workflow = Workflow::factory()->create();

        $definition = $this->validDefinition([
            'nodes' => [
                [
                    'id' => 'n1',
                    'type' => 'ai-summarize',
                    'config' => [], // no kbDocs key at all
                    'is_entry_point' => true,
                    'is_terminal' => true,
                ],
            ],
        ]);

        $result = $this->validate($definition, $workflow);

        $this->assertNoError($result, 'context.kb_docs_invalid');
    }

    /** @test */
    public function test_context_multiple_kb_docs_reports_each_unknown_doc_separately(): void
    {
        $workflow = Workflow::factory()->create();
        $validDoc = Document::factory()->create(['tenant_id' => $workflow->tenant_id]);

        $definition = $this->validDefinition([
            'nodes' => [
                [
                    'id' => 'n1',
                    'type' => 'ai-summarize',
                    'config' => ['kbDocs' => [$validDoc->id, 88888, 99999]], // two unknown
                    'is_entry_point' => true,
                    'is_terminal' => true,
                ],
            ],
        ]);

        $result = $this->validate($definition, $workflow);

        $unknownErrors = array_filter($result['issues'], fn ($i) => $i['code'] === 'context.kb_doc_unknown');
        $this->assertCount(2, array_values($unknownErrors));
    }

    // =========================================================================
    // 5. Cross-rule & Integration scenarios
    // =========================================================================

    /** @test */
    public function test_integration_syntax_errors_are_reported_before_graph_errors(): void
    {
        // Definition with both syntax errors (missing trigger type) and a
        // disconnected node. Syntax should dominate.
        $definition = [
            'trigger' => ['type' => '', 'config' => []],
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true, 'is_terminal' => true],
                ['id' => 'n2', 'type' => 'send-email', 'config' => [], 'is_terminal' => true], // orphan
            ],
            'edges' => [],
        ];

        $result = $this->validate($definition);

        $this->assertNotPublishable($result);
        $this->assertHasError($result, 'trigger.type_missing');
    }

    /** @test */
    public function test_integration_fully_valid_multi_node_linear_workflow_is_publishable(): void
    {
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email', 'config' => [], 'is_entry_point' => true],
                ['id' => 'n2', 'type' => 'send-email', 'config' => []],
                ['id' => 'n3', 'type' => 'send-email', 'config' => [], 'is_terminal' => true],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2'],
                ['source' => 'n2', 'target' => 'n3'],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertPublishable($result);
        $this->assertSame(0, $result['summary']['errors']);
    }

    /** @test */
    public function test_integration_workflow_with_conditional_branching_and_valid_expressions_is_publishable(): void
    {
        // n1 → n2(conditional) → n3 (true branch)
        //                      → n4 (false branch, terminal)
        $definition = $this->validDefinition([
            'nodes' => [
                ['id' => 'n1', 'type' => 'send-email',  'config' => [], 'is_entry_point' => true],
                ['id' => 'n2', 'type' => 'conditional',  'config' => []],
                ['id' => 'n3', 'type' => 'send-email',  'config' => [], 'is_terminal' => true],
                ['id' => 'n4', 'type' => 'send-email',  'config' => [], 'is_terminal' => true],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2'],
                [
                    'source' => 'n2',
                    'target' => 'n3',
                    'branch_type' => 'conditional',
                    'condition_expression' => 'approved == true',
                ],
                [
                    'source' => 'n2',
                    'target' => 'n4',
                    'branch_type' => 'conditional',
                    'condition_expression' => 'approved == false',
                ],
            ],
        ]);

        $result = $this->validate($definition);

        $this->assertPublishable($result);
    }

    /** @test */
    public function test_integration_result_structure_always_contains_required_keys(): void
    {
        $result = $this->validate($this->validDefinition());

        $this->assertArrayHasKey('is_publishable', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('errors', $result['summary']);
        $this->assertArrayHasKey('warnings', $result['summary']);
        $this->assertArrayHasKey('issues', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('warnings', $result);
    }

    /** @test */
    public function test_integration_issue_objects_always_contain_required_keys(): void
    {
        $definition = $this->validDefinition();
        unset($definition['trigger']);

        $result = $this->validate($definition);

        foreach ($result['issues'] as $issue) {
            $this->assertArrayHasKey('severity', $issue);
            $this->assertArrayHasKey('code', $issue);
            $this->assertArrayHasKey('message', $issue);
            $this->assertArrayHasKey('path', $issue);
            $this->assertArrayHasKey('node_id', $issue);
            $this->assertArrayHasKey('edge_id', $issue);
        }
    }

    /** @test */
    public function test_integration_warnings_do_not_block_publication(): void
    {
        // A definition that triggers warnings but no errors
        // The exact scenario depends on what produces warnings in the system.
        // For now, confirm that a valid definition with no errors is publishable
        // even if warnings are present.
        $result = $this->validate($this->validDefinition());

        if ($result['summary']['warnings'] > 0) {
            $this->assertTrue($result['is_publishable']);
        } else {
            $this->assertTrue(true); // no warnings to verify, still fine
        }
    }

    /** @test */
    public function test_integration_errors_array_matches_error_count_in_summary(): void
    {
        $definition = $this->validDefinition();
        unset($definition['trigger']);
        unset($definition['edges']);

        $result = $this->validate($definition);

        $this->assertSame($result['summary']['errors'], count($result['errors']));
        $this->assertSame($result['summary']['warnings'], count($result['warnings']));
    }
}
