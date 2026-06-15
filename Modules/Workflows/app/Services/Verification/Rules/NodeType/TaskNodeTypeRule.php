<?php

namespace Modules\Workflows\Services\Verification\Rules\NodeType;

use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
use Modules\Workflows\Services\Verification\WorkflowVerificationResult;

class TaskNodeTypeRule implements NodeTypeRule
{
    private const VALID_FIELD_TYPES = ['text', 'textarea', 'number', 'date', 'file', 'checkbox', 'select'];

    private const OPTIONS_REQUIRED_TYPES = ['select', 'checkbox'];

    public function nodeType(): string
    {
        return 'task-node';
    }

    public function verify(
        array $node,
        int $index,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
    ): void {
        $nodeId = is_string($node['id'] ?? null) ? $node['id'] : null;
        $config = is_array($node['config'] ?? null) ? $node['config'] : [];

        $title = trim((string) ($config['title'] ?? ''));
        if ($title === '') {
            $result->addError('task_node.title_missing', 'Task node must have a title.', "nodes[{$index}].config.title", $nodeId);
        }

        $this->verifyInputFields($config, $index, $nodeId, $result);

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

    private function verifyInputFields(array $config, int $index, ?string $nodeId, WorkflowVerificationResult $result): void
    {
        $fieldsPath = "nodes[{$index}].config.inputFields";
        $inputFields = is_array($config['inputFields'] ?? null) ? $config['inputFields'] : [];

        if (empty($inputFields)) {
            $result->addError('task_node.input_fields_missing', 'Task node must have at least one input field.', $fieldsPath, $nodeId);

            return;
        }

        foreach ($inputFields as $fieldIdx => $field) {
            if (! is_array($field)) {
                $result->addError('task_node.input_field_invalid', "Input field #".($fieldIdx + 1).' is not a valid object.', "{$fieldsPath}[{$fieldIdx}]", $nodeId);
                continue;
            }

            $fieldKey = trim((string) ($field['key'] ?? ''));
            if ($fieldKey === '') {
                $result->addError('task_node.input_field_key_missing', "Input field #".($fieldIdx + 1).' must have a key.', "{$fieldsPath}[{$fieldIdx}].key", $nodeId);
            }

            $fieldLabel = trim((string) ($field['label'] ?? ''));
            if ($fieldLabel === '') {
                $result->addError('task_node.input_field_label_missing', "Input field #".($fieldIdx + 1).' must have a label.', "{$fieldsPath}[{$fieldIdx}].label", $nodeId);
            }

            $fieldType = (string) ($field['type'] ?? 'text');
            if (! in_array($fieldType, self::VALID_FIELD_TYPES, true)) {
                $result->addError('task_node.input_field_type_invalid', "Input field #".($fieldIdx + 1)." has an invalid type '{$fieldType}'.", "{$fieldsPath}[{$fieldIdx}].type", $nodeId);
            } elseif (in_array($fieldType, self::OPTIONS_REQUIRED_TYPES, true)) {
                $options = is_array($field['options'] ?? null) ? $field['options'] : [];
                $validOptions = array_filter($options, fn ($o): bool => is_string($o) && trim($o) !== '');

                if (empty($validOptions)) {
                    $result->addError(
                        'task_node.input_field_options_missing',
                        "Input field #".($fieldIdx + 1)." of type '{$fieldType}' must have at least one option.",
                        "{$fieldsPath}[{$fieldIdx}].options",
                        $nodeId,
                    );
                }
            }
        }
    }
}
