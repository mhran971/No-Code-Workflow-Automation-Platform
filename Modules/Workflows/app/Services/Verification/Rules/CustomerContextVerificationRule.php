<?php

namespace Modules\Workflows\Services\Verification\Rules;

use Modules\Auth\Models\User;
use Modules\Customers\Models\CustomerSettings;
use Modules\Workflows\Enums\VerificationMode;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;

/**
 * Validates the "customer context" trigger option (manual-trigger / form-trigger only —
 * see Modules\Customers docs). Mirrors FormTriggerVerificationRule's precedent of reading
 * the canonical `definition.trigger` directly rather than dispatching per node type.
 */
class CustomerContextVerificationRule implements VerificationRule
{
    private const APPLICABLE_TRIGGER_TYPES = ['manual-trigger', 'form-trigger'];

    public function skipForSegment(): bool
    {
        // Segments (dynamic-flow sub-flows) have no trigger.
        return true;
    }

    public function verify(
        array $definition,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
        ?Workflow $workflow = null,
        ?User $actor = null,
        VerificationMode $mode = VerificationMode::Full,
    ): void {
        $trigger = $definition['trigger'] ?? null;
        if (! is_array($trigger)) {
            return;
        }

        $type = $trigger['type'] ?? null;
        if (! in_array($type, self::APPLICABLE_TRIGGER_TYPES, true)) {
            return;
        }

        $config = is_array($trigger['config'] ?? null) ? $trigger['config'] : [];
        if (($config['customerContextEnabled'] ?? false) !== true) {
            return;
        }

        $fieldKey = trim((string) ($config['customerContextField'] ?? ''));
        if ($fieldKey === '') {
            $result->addError(
                'customer_context.field_missing',
                'Customer context is enabled but no linking field is selected.',
                'trigger.config.customerContextField',
            );

            return;
        }

        $declaredKey = $type === 'form-trigger' ? 'formFields' : 'variables';
        $declared = is_array($config[$declaredKey] ?? null) ? $config[$declaredKey] : [];

        $match = null;
        foreach ($declared as $entry) {
            if (is_array($entry) && ($entry['key'] ?? null) === $fieldKey) {
                $match = $entry;
                break;
            }
        }

        if ($match === null) {
            $result->addError(
                'customer_context.field_undeclared',
                "Customer linking field '{$fieldKey}' does not match any declared trigger field.",
                'trigger.config.customerContextField',
            );

            return;
        }

        if ($type === 'form-trigger' && ($match['required'] ?? false) !== true) {
            $result->addError(
                'customer_context.field_not_required',
                "Form field '{$fieldKey}' must be marked required to be used as the customer linking field.",
                'trigger.config.customerContextField',
            );
        }

        // manual-trigger: declared presence in `variables[]` is the whole check — a static
        // test variable is always present once declared, even if its value can be empty.

        if ($workflow !== null) {
            $configured = CustomerSettings::query()
                ->where('tenant_id', $workflow->tenant_id)
                ->whereNotNull('linking_field')
                ->exists();

            if (! $configured) {
                $result->addError(
                    'customer_context.tenant_linking_field_not_configured',
                    'Customer context is enabled but this tenant has not chosen a customer linking field yet.',
                    'trigger.config.customerContextField',
                );
            }
        }
    }
}
