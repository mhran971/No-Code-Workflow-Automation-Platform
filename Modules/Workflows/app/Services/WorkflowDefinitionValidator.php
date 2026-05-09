<?php

namespace Modules\Workflows\Services;

class WorkflowDefinitionValidator
{
    public function validate(array $definition): array
    {
        $errors = [];
        $warnings = [];

        if (! array_key_exists('trigger', $definition) || ! is_array($definition['trigger'])) {
            $errors[] = 'Workflow trigger must be configured.';
        } elseif (empty($definition['trigger']['type'])) {
            $errors[] = 'Workflow trigger type is required.';
        }

        if (! array_key_exists('nodes', $definition) || ! is_array($definition['nodes'])) {
            $errors[] = 'Workflow nodes must be an array.';
        }

        if (! array_key_exists('edges', $definition) || ! is_array($definition['edges'])) {
            $errors[] = 'Workflow edges must be an array.';
        }

        if (($definition['nodes'] ?? []) === []) {
            $warnings[] = 'Workflow has no action nodes.';
        }

        return [
            'is_publishable' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
