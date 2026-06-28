<?php

namespace Modules\Workflows\Services\Verification\Rules;

use Modules\Auth\Models\User;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
use Modules\Workflows\Services\Verification\WorkflowVerificationResult;

class FormTriggerVerificationRule implements VerificationRule
{
    private const VALID_FIELD_TYPES = ['text', 'textarea', 'number', 'date', 'file', 'checkbox', 'select'];

    private const OPTIONS_REQUIRED_TYPES = ['select', 'checkbox'];

    private const VALID_ACCESS_LEVELS = ['public', 'tenant'];

    public function verify(
        array $definition,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
        ?Workflow $workflow = null,
        ?User $actor = null,
    ): void {
        $trigger = $definition['trigger'] ?? null;

        if (! is_array($trigger) || ($trigger['type'] ?? null) !== 'form-trigger') {
            return;
        }

        $config = is_array($trigger['config'] ?? null) ? $trigger['config'] : [];

        $this->verifyFormFields($config, $result);
        $this->verifyAccessLevel($config, $result);
    }

    private function verifyFormFields(array $config, WorkflowVerificationResult $result): void
    {
        $fieldsPath = 'trigger.config.formFields';
        $formFields = is_array($config['formFields'] ?? null) ? $config['formFields'] : [];

        if (empty($formFields)) {
            $result->addError('form_trigger.form_fields_missing', 'Form trigger must have at least one form field.', $fieldsPath);

            return;
        }

        foreach ($formFields as $fieldIdx => $field) {
            if (! is_array($field)) {
                $result->addError('form_trigger.form_field_invalid', 'Form field #'.($fieldIdx + 1).' is not a valid object.', "{$fieldsPath}[{$fieldIdx}]");

                continue;
            }

            $fieldKey = trim((string) ($field['key'] ?? ''));
            if ($fieldKey === '') {
                $result->addError('form_trigger.form_field_key_missing', 'Form field #'.($fieldIdx + 1).' must have a key.', "{$fieldsPath}[{$fieldIdx}].key");
            }

            $fieldLabel = trim((string) ($field['label'] ?? ''));
            if ($fieldLabel === '') {
                $result->addError('form_trigger.form_field_label_missing', 'Form field #'.($fieldIdx + 1).' must have a label.', "{$fieldsPath}[{$fieldIdx}].label");
            }

            $fieldType = (string) ($field['type'] ?? 'text');
            if (! in_array($fieldType, self::VALID_FIELD_TYPES, true)) {
                $result->addError('form_trigger.form_field_type_invalid', 'Form field #'.($fieldIdx + 1)." has an invalid type '{$fieldType}'.", "{$fieldsPath}[{$fieldIdx}].type");
            } elseif (in_array($fieldType, self::OPTIONS_REQUIRED_TYPES, true)) {
                $options = is_array($field['options'] ?? null) ? $field['options'] : [];
                $validOptions = array_filter($options, fn ($o): bool => is_string($o) && trim($o) !== '');

                if (empty($validOptions)) {
                    $result->addError(
                        'form_trigger.form_field_options_missing',
                        'Form field #'.($fieldIdx + 1)." of type '{$fieldType}' must have at least one option.",
                        "{$fieldsPath}[{$fieldIdx}].options",
                    );
                }
            }
        }
    }

    private function verifyAccessLevel(array $config, WorkflowVerificationResult $result): void
    {
        $accessLevel = trim((string) ($config['accessLevel'] ?? ''));

        if ($accessLevel === '') {
            $result->addError('form_trigger.access_level_missing', 'Form trigger must have an access level.', 'trigger.config.accessLevel');

            return;
        }

        if (! in_array($accessLevel, self::VALID_ACCESS_LEVELS, true)) {
            $result->addError(
                'form_trigger.access_level_invalid',
                "Form trigger access level must be 'public' or 'tenant', got '{$accessLevel}'.",
                'trigger.config.accessLevel',
            );
        }
    }
}
