<?php

namespace Modules\Workflows\Services;

use Illuminate\Support\Str;
use Modules\Workflows\Enums\TriggerType;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Services\Execution\WorkflowDispatcher;

/**
 * Serves and accepts submissions for public form-trigger workflows, without any authenticated
 * actor. Deliberately kept separate from WorkflowManagementService (which assumes an authenticated,
 * tenant-scoped actor for every operation) so a future edit there can never accidentally weaken the
 * checks that gate this genuinely public, unauthenticated surface.
 *
 * A workflow is a valid public form only when all of the following hold:
 *  - it has a published version (current_version_id is set);
 *  - it is Active (not disabled/deleted);
 *  - the published version's trigger is a 'form-trigger' with config.accessLevel === 'public'.
 * Anything else resolves to "not found" — callers should never learn *why* a form isn't public.
 */
class PublicFormService
{
    private const VALID_FIELD_TYPES = ['text', 'textarea', 'number', 'date', 'file', 'checkbox', 'select'];

    private const OPTIONS_REQUIRED_TYPES = ['select', 'checkbox'];

    public function __construct(protected WorkflowDispatcher $dispatcher) {}

    public function findPublicForm(string $publicToken): ?Workflow
    {
        // public_token is a `uuid` column — Postgres throws a query error (rather than matching
        // zero rows) for a malformed UUID literal, which every scanner/typo/stale link will send.
        if (! Str::isUuid($publicToken)) {
            return null;
        }

        $workflow = Workflow::query()
            ->where('public_token', $publicToken)
            ->where('status', WorkflowStatus::Active->value)
            ->whereNotNull('current_version_id')
            ->with('currentVersion')
            ->first();

        if ($workflow === null || $workflow->currentVersion === null || ! $this->isPublicFormTrigger($workflow)) {
            return null;
        }

        return $workflow;
    }

    /**
     * @return array<string, mixed>
     */
    public function formSchema(Workflow $workflow): array
    {
        $config = $this->triggerConfig($workflow);
        $fields = is_array($config['formFields'] ?? null) ? $config['formFields'] : [];

        return [
            'workflow_name' => $workflow->name,
            'form_name' => is_string($config['formName'] ?? null) && $config['formName'] !== '' ? $config['formName'] : $workflow->name,
            'form_description' => is_string($config['description'] ?? null) ? $config['description'] : null,
            'fields' => array_values(array_map(
                fn (array $field): array => [
                    'key' => (string) ($field['key'] ?? ''),
                    'label' => (string) ($field['label'] ?? ''),
                    'type' => (string) ($field['type'] ?? 'text'),
                    'required' => (bool) ($field['required'] ?? false),
                    'options' => is_array($field['options'] ?? null)
                        ? array_values(array_filter($field['options'], 'is_string'))
                        : [],
                ],
                array_values(array_filter($fields, 'is_array')),
            )),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string> validation error messages; empty means the payload is acceptable
     */
    public function validateSubmission(Workflow $workflow, array $payload): array
    {
        $schema = $this->formSchema($workflow);
        $errors = [];

        foreach ($schema['fields'] as $field) {
            $key = $field['key'];
            if ($key === '') {
                continue;
            }

            $value = $payload[$key] ?? null;
            $isEmpty = $value === null || $value === '';

            if ($field['required'] && $isEmpty) {
                $errors[] = "The {$field['label']} field is required.";

                continue;
            }

            if ($isEmpty) {
                continue;
            }

            if (! in_array($field['type'], self::VALID_FIELD_TYPES, true)) {
                continue;
            }

            if ($field['type'] === 'number' && ! is_numeric($value)) {
                $errors[] = "The {$field['label']} field must be a number.";
            }

            if (in_array($field['type'], self::OPTIONS_REQUIRED_TYPES, true)
                && $field['options'] !== []
                && ! in_array((string) $value, $field['options'], true)) {
                $errors[] = "The {$field['label']} field must be one of: ".implode(', ', $field['options']).'.';
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submit(Workflow $workflow, array $payload): WorkflowInstance
    {
        return $this->dispatcher->dispatch($workflow, TriggerType::Form, $payload);
    }

    protected function isPublicFormTrigger(Workflow $workflow): bool
    {
        $trigger = $workflow->currentVersion->definition['trigger'] ?? null;

        if (! is_array($trigger) || ($trigger['type'] ?? null) !== 'form-trigger') {
            return false;
        }

        $config = is_array($trigger['config'] ?? null) ? $trigger['config'] : [];

        return ($config['accessLevel'] ?? null) === 'public';
    }

    /**
     * @return array<string, mixed>
     */
    protected function triggerConfig(Workflow $workflow): array
    {
        $trigger = $workflow->currentVersion->definition['trigger'] ?? [];
        $config = is_array($trigger['config'] ?? null) ? $trigger['config'] : [];

        return $config;
    }
}
