<?php

namespace Tests\Unit;

use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\Rules\NodeType\ParseJsonNodeTypeRule;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
use PHPUnit\Framework\TestCase;

/**
 * Pure (no-DB) tests for ParseJsonNodeTypeRule.
 */
class ParseJsonNodeTypeRuleTest extends TestCase
{
    private ParseJsonNodeTypeRule $rule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule = new ParseJsonNodeTypeRule;
    }

    public function test_node_type_is_parse_json(): void
    {
        $this->assertSame('parse-json', $this->rule->nodeType());
    }

    public function test_missing_input_variable_adds_error(): void
    {
        $node = ['id' => 'pj1', 'type' => 'parse-json', 'config' => ['outputVariable' => 'parsed']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertFalse($result->isPublishable());
        $this->assertContains('parse_json.input_variable_missing', array_column($result->issues(), 'code'));
    }

    public function test_input_variable_without_namespace_adds_error(): void
    {
        $node = ['id' => 'pj1', 'type' => 'parse-json', 'config' => ['inputVariable' => 'rawJson', 'outputVariable' => 'parsed']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertFalse($result->isPublishable());
        $this->assertContains('parse_json.input_variable_invalid', array_column($result->issues(), 'code'));
    }

    public function test_missing_output_variable_adds_error(): void
    {
        $node = ['id' => 'pj1', 'type' => 'parse-json', 'config' => ['inputVariable' => 'context.rawJson']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertFalse($result->isPublishable());
        $this->assertContains('parse_json.output_variable_missing', array_column($result->issues(), 'code'));
    }

    public function test_invalid_output_variable_adds_error(): void
    {
        $node = ['id' => 'pj1', 'type' => 'parse-json', 'config' => ['inputVariable' => 'context.rawJson', 'outputVariable' => '1invalid']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertFalse($result->isPublishable());
        $this->assertContains('parse_json.output_variable_invalid', array_column($result->issues(), 'code'));
    }

    public function test_undefined_context_variable_under_manual_trigger_adds_error(): void
    {
        $trigger = ['id' => 'trig', 'type' => 'manual-trigger', 'config' => ['variables' => [['key' => 'otherVar']]]];
        $node = ['id' => 'pj1', 'type' => 'parse-json', 'config' => ['inputVariable' => 'context.rawJson', 'outputVariable' => 'parsed']];
        $graph = new WorkflowDefinitionGraph([
            'trigger' => ['type' => 'manual-trigger', 'config' => ['variables' => [['key' => 'otherVar']]]],
            'nodes' => [$trigger, $node],
            'edges' => [['source_node_key' => 'trig', 'target_node_key' => 'pj1']],
        ]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertFalse($result->isPublishable());
        $this->assertContains('parse_json.variable_undefined', array_column($result->issues(), 'code'));
    }

    public function test_valid_config_under_manual_trigger_passes(): void
    {
        $trigger = ['id' => 'trig', 'type' => 'manual-trigger', 'config' => ['variables' => [['key' => 'rawJson']]]];
        $node = ['id' => 'pj1', 'type' => 'parse-json', 'config' => ['inputVariable' => 'context.rawJson', 'outputVariable' => 'parsed']];
        $graph = new WorkflowDefinitionGraph([
            'trigger' => ['type' => 'manual-trigger', 'config' => ['variables' => [['key' => 'rawJson']]]],
            'nodes' => [$trigger, $node],
            'edges' => [['source_node_key' => 'trig', 'target_node_key' => 'pj1']],
        ]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertTrue($result->isPublishable());
    }
}
