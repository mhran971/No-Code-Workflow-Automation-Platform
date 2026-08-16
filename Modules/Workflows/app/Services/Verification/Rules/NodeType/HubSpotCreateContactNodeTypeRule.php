<?php

namespace Modules\Workflows\Services\Verification\Rules\NodeType;

use Modules\Integrations\Models\IntegrationConnection;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\Rules\NodeType\Concerns\VariableAvailability;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;

class HubSpotCreateContactNodeTypeRule implements NodeTypeRule
{
    use VariableAvailability;

    protected const CODE_PREFIX = 'hubspot_create_contact';

    /** Matches the entire value being exactly one template variable: {{context.key}} or {{customer.key}} */
    private const FULL_TEMPLATE_VAR_RE = '/^\{\{\s*(context|customer)\.[a-zA-Z_][a-zA-Z0-9_]*\s*\}\}$/';

    public function nodeType(): string
    {
        return 'hubspot-create-contact';
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

        $firstName = trim((string) ($config['firstName'] ?? ''));
        $lastName = trim((string) ($config['lastName'] ?? ''));
        $email = trim((string) ($config['email'] ?? ''));
        $phone = trim((string) ($config['phone'] ?? ''));

        // HubSpot requires at least one of email/firstname/lastname on a contact.
        if ($firstName === '' && $lastName === '' && $email === '') {
            $result->addError(
                'hubspot_create_contact.identity_missing',
                'HubSpot: Create Contact node needs at least one of first name, last name, or email.',
                "nodes[{$index}].config",
                $nodeId,
            );
        }

        if ($firstName !== '') {
            $this->validateTemplateVariables($firstName, "nodes[{$index}].config.firstName", $nodeId, self::CODE_PREFIX, $graph, $result);
        }

        if ($lastName !== '') {
            $this->validateTemplateVariables($lastName, "nodes[{$index}].config.lastName", $nodeId, self::CODE_PREFIX, $graph, $result);
        }

        if ($email !== '') {
            $this->validateEmailOrVar($email, "nodes[{$index}].config.email", $nodeId, $result);
        }

        if ($phone !== '') {
            $this->validateTemplateVariables($phone, "nodes[{$index}].config.phone", $nodeId, self::CODE_PREFIX, $graph, $result);
        }

        if ($workflow !== null) {
            $hasConnection = IntegrationConnection::query()
                ->where('integration_provider_id', 'hubspot')
                ->where('tenant_id', $workflow->tenant_id)
                ->exists();

            if (! $hasConnection) {
                $result->addError(
                    'hubspot_create_contact.connection_missing',
                    'No HubSpot connection found for this tenant. Connect HubSpot via Integrations before publishing this workflow.',
                    null,
                    $nodeId,
                );
            }
        }
    }

    /**
     * Value must be either a single {{context.<key>}} / {{customer.<key>}} variable
     * or a plain email address. Nothing else is accepted.
     */
    private function validateEmailOrVar(string $value, string $path, ?string $nodeId, WorkflowVerificationResult $result): void
    {
        if (str_starts_with($value, '{{')) {
            if (! preg_match(self::FULL_TEMPLATE_VAR_RE, $value)) {
                $result->addError(
                    'hubspot_create_contact.email_invalid',
                    "The email field has an invalid variable '{$value}'. Use {{context.<key>}} or {{customer.<key>}} with a single identifier.",
                    $path,
                    $nodeId,
                );
            }

            return;
        }

        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $result->addError(
                'hubspot_create_contact.email_invalid',
                'The email field must be a valid email address or a variable like {{context.email}}.',
                $path,
                $nodeId,
            );
        }
    }
}
