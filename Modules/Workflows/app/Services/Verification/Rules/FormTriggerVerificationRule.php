<?php

namespace Modules\Workflows\Services\Verification\Rules;

use Modules\Auth\Models\User;
use Modules\Workflows\Enums\VerificationMode;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\Rules\Concerns\FormFieldValidation;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;

class FormTriggerVerificationRule implements VerificationRule
{
    use FormFieldValidation;

    private const VALID_ACCESS_LEVELS = ['public', 'tenant'];

    public function verify(
        array $definition,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
        ?Workflow $workflow = null,
        ?User $actor = null,
        VerificationMode $mode = VerificationMode::Full,
    ): void {
        $trigger = $definition['trigger'] ?? null;

        if (! is_array($trigger) || ($trigger['type'] ?? null) !== 'form-trigger') {
            return;
        }

        $config = is_array($trigger['config'] ?? null) ? $trigger['config'] : [];
        $formFields = is_array($config['formFields'] ?? null) ? $config['formFields'] : [];

        if (empty($formFields)) {
            $result->addError('form_trigger.form_fields_missing', 'Form trigger must have at least one form field.', 'trigger.config.formFields');

            return;
        }

        $this->validateFields($formFields, 'trigger.config.formFields', 'form_trigger', 'form_field', 'Form field', null, $result);
        $this->verifyAccessLevel($config, $result);
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
