<?php

namespace Modules\Workflows\Services\Verification\Rules\Concerns;

use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;

trait FormFieldValidation
{
    private const VALID_FIELD_TYPES = ['text', 'textarea', 'number', 'date', 'file', 'checkbox', 'select'];

    private const OPTIONS_REQUIRED_TYPES = ['select', 'checkbox'];

    /**
     * @param  list<array>  $fields
     */
    protected function validateFields(
        array $fields,
        string $fieldsPath,
        string $errorPrefix,
        string $fieldSuffix,
        string $fieldLabel,
        ?string $nodeId,
        WorkflowVerificationResult $result,
    ): void {
        foreach ($fields as $fieldIdx => $field) {
            if (! is_array($field)) {
                $result->addError("{$errorPrefix}.{$fieldSuffix}_invalid", "{$fieldLabel} #".($fieldIdx + 1).' is not a valid object.', "{$fieldsPath}[{$fieldIdx}]", $nodeId);

                continue;
            }

            $fieldKey = trim((string) ($field['key'] ?? ''));
            if ($fieldKey === '') {
                $result->addError("{$errorPrefix}.{$fieldSuffix}_key_missing", "{$fieldLabel} #".($fieldIdx + 1).' must have a key.', "{$fieldsPath}[{$fieldIdx}].key", $nodeId);
            }

            $fieldLabelValue = trim((string) ($field['label'] ?? ''));
            if ($fieldLabelValue === '') {
                $result->addError("{$errorPrefix}.{$fieldSuffix}_label_missing", "{$fieldLabel} #".($fieldIdx + 1).' must have a label.', "{$fieldsPath}[{$fieldIdx}].label", $nodeId);
            }

            $fieldType = (string) ($field['type'] ?? 'text');
            if (! in_array($fieldType, self::VALID_FIELD_TYPES, true)) {
                $result->addError("{$errorPrefix}.{$fieldSuffix}_type_invalid", "{$fieldLabel} #".($fieldIdx + 1)." has an invalid type '{$fieldType}'.", "{$fieldsPath}[{$fieldIdx}].type", $nodeId);
            } elseif (in_array($fieldType, self::OPTIONS_REQUIRED_TYPES, true)) {
                $options = is_array($field['options'] ?? null) ? $field['options'] : [];
                $validOptions = array_filter($options, fn ($o): bool => is_string($o) && trim($o) !== '');

                if (empty($validOptions)) {
                    $result->addError(
                        "{$errorPrefix}.{$fieldSuffix}_options_missing",
                        "{$fieldLabel} #".($fieldIdx + 1)." of type '{$fieldType}' must have at least one option.",
                        "{$fieldsPath}[{$fieldIdx}].options",
                        $nodeId,
                    );
                }
            }
        }
    }
}
