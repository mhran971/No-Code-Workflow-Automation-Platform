<?php

namespace Modules\Workflows\Services\Verification\Rules\NodeType;

use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\Rules\Concerns\FormFieldValidation;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;

class TaskNodeTypeRule implements NodeTypeRule
{
    use FormFieldValidation;

    public function nodeType(): string
    {
        return 'task-node';
    }

    public function verify(
        array $node,
        int $index,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
        ?Workflow $workflow = null,
    ): void {
        $nodeId = is_string($node['id'] ?? null) ? $node['id'] : null;
        $config = is_array($node['config'] ?? null) ? $node['config'] : [];

        $title = trim((string) ($config['title'] ?? ''));
        if ($title === '') {
            $result->addError('task_node.title_missing', 'Task node must have a title.', "nodes[{$index}].config.title", $nodeId);
        }

        $inputFields = is_array($config['inputFields'] ?? null) ? $config['inputFields'] : [];

        if (empty($inputFields)) {
            $result->addError('task_node.input_fields_missing', 'Task node must have at least one input field.', "nodes[{$index}].config.inputFields", $nodeId);
        } else {
            $this->validateFields($inputFields, "nodes[{$index}].config.inputFields", 'task_node', 'input_field', 'Input field', $nodeId, $result);
        }

        if ($nodeId !== null && $graph->hasNode($nodeId)) {
            $count = count($graph->outgoing($nodeId));

            if ($count !== 1) {
                $result->addError(
                    'task_node.branches_invalid',
                    "Task node must have exactly 1 outgoing branch, found {$count}.",
                    null,
                    $nodeId,
                );
            }
        }
    }
}
