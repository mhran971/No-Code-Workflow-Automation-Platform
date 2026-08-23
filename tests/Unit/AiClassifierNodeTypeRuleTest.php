<?php

namespace Tests\Unit;

use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\Rules\NodeType\AiClassifierNodeTypeRule;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
use Tests\TestCase;

class AiClassifierNodeTypeRuleTest extends TestCase
{
    protected AiClassifierNodeTypeRule $rule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule = new AiClassifierNodeTypeRule;
    }

    public function test_node_type_is_ai_classifier(): void
    {
        $this->assertSame('ai-classifier', $this->rule->nodeType());
    }

    public function test_missing_text_adds_error(): void
    {
        $node = ['id' => 'ac1', 'type' => 'ai-classifier', 'config' => ['categories' => ['a', 'b']]];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertFalse($result->isPublishable());
        $this->assertContains('ai_classifier.text_missing', array_column($result->issues(), 'code'));
    }

    public function test_missing_categories_adds_error(): void
    {
        $node = ['id' => 'ac1', 'type' => 'ai-classifier', 'config' => ['text' => 'hello']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertContains('ai_classifier.categories_missing', array_column($result->issues(), 'code'));
    }

    public function test_single_category_is_insufficient(): void
    {
        $node = ['id' => 'ac1', 'type' => 'ai-classifier', 'config' => ['text' => 'hello', 'categories' => ['billing']]];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertContains('ai_classifier.categories_insufficient', array_column($result->issues(), 'code'));
    }

    public function test_non_array_categories_adds_error(): void
    {
        $node = ['id' => 'ac1', 'type' => 'ai-classifier', 'config' => ['text' => 'hello', 'categories' => 'billing']];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertContains('ai_classifier.categories_invalid', array_column($result->issues(), 'code'));
    }

    public function test_duplicate_categories_add_error(): void
    {
        $node = ['id' => 'ac1', 'type' => 'ai-classifier', 'config' => ['text' => 'hello', 'categories' => ['billing', 'billing']]];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertContains('ai_classifier.categories_duplicate', array_column($result->issues(), 'code'));
    }

    public function test_invalid_output_variable_adds_error(): void
    {
        $node = ['id' => 'ac1', 'type' => 'ai-classifier', 'config' => [
            'text' => 'hello', 'categories' => ['billing', 'shipping'], 'outputVariable' => '1bad',
        ]];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertContains('ai_classifier.output_variable_invalid', array_column($result->issues(), 'code'));
    }

    public function test_invalid_template_variable_in_text_adds_error(): void
    {
        $node = ['id' => 'ac1', 'type' => 'ai-classifier', 'config' => [
            'text' => '{{not valid}}', 'categories' => ['billing', 'shipping'],
        ]];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertContains('ai_classifier.invalid_template_variable', array_column($result->issues(), 'code'));
    }

    public function test_valid_config_passes(): void
    {
        $node = ['id' => 'ac1', 'type' => 'ai-classifier', 'config' => [
            'text' => '{{context.message}}',
            'categories' => ['billing', 'shipping', 'general_inquiry'],
            'outputVariable' => 'classificationResult',
        ]];
        $graph = new WorkflowDefinitionGraph(['nodes' => [$node], 'edges' => []]);
        $result = new WorkflowVerificationResult;

        $this->rule->verify($node, 0, $graph, $result);

        $this->assertTrue($result->isPublishable());
    }
}
