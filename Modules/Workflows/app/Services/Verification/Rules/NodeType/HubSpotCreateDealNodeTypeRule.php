<?php

namespace Modules\Workflows\Services\Verification\Rules\NodeType;

use Modules\Integrations\Models\IntegrationConnection;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\Rules\NodeType\Concerns\VariableAvailability;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;

class HubSpotCreateDealNodeTypeRule implements NodeTypeRule
{
    use VariableAvailability;

    protected const CODE_PREFIX = 'hubspot_create_deal';

    public function nodeType(): string
    {
        return 'hubspot-create-deal';
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

        $dealName = trim((string) ($config['dealName'] ?? ''));
        if ($dealName === '') {
            $result->addError(
                'hubspot_create_deal.dealname_missing',
                'HubSpot: Create Deal node must have a deal name.',
                "nodes[{$index}].config.dealName",
                $nodeId,
            );
        } else {
            $this->validateTemplateVariables($dealName, "nodes[{$index}].config.dealName", $nodeId, self::CODE_PREFIX, $graph, $result);
        }

        $dealStage = trim((string) ($config['dealStage'] ?? ''));
        if ($dealStage === '') {
            $result->addError(
                'hubspot_create_deal.dealstage_missing',
                'HubSpot: Create Deal node must specify a deal stage.',
                "nodes[{$index}].config.dealStage",
                $nodeId,
            );
        }

        $amount = trim((string) ($config['amount'] ?? ''));
        if ($amount !== '') {
            $this->validateTemplateVariables($amount, "nodes[{$index}].config.amount", $nodeId, self::CODE_PREFIX, $graph, $result);
        }

        $closeDate = trim((string) ($config['closeDate'] ?? ''));
        if ($closeDate !== '') {
            $this->validateTemplateVariables($closeDate, "nodes[{$index}].config.closeDate", $nodeId, self::CODE_PREFIX, $graph, $result);
        }

        $contactId = trim((string) ($config['contactId'] ?? ''));
        if ($contactId !== '') {
            $this->validateTemplateVariables($contactId, "nodes[{$index}].config.contactId", $nodeId, self::CODE_PREFIX, $graph, $result);
        }

        if ($workflow !== null) {
            $hasConnection = IntegrationConnection::query()
                ->where('integration_provider_id', 'hubspot')
                ->where('tenant_id', $workflow->tenant_id)
                ->exists();

            if (! $hasConnection) {
                $result->addError(
                    'hubspot_create_deal.connection_missing',
                    'No HubSpot connection found for this tenant. Connect HubSpot via Integrations before publishing this workflow.',
                    null,
                    $nodeId,
                );
            }
        }
    }
}
