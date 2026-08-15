<?php

namespace Modules\Workflows\Services\Execution\Executors;

use Modules\Integrations\Exceptions\IntegrationException;
use Modules\Integrations\Models\IntegrationConnection;
use Modules\Integrations\Services\HubSpot\HubSpotClient;
use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;
use Modules\Workflows\Services\Execution\Data\NodeExecutionResult;
use Modules\Workflows\Services\Execution\NodeExecutionContext;

class HubSpotCreateDealExecutor implements NodeExecutor
{
    /** Deal -> Contact, per HubSpot's built-in association type IDs. */
    private const CONTACT_ASSOCIATION_TYPE_ID = 3;

    public function __construct(protected HubSpotClient $hubSpot) {}

    public function type(): string
    {
        return 'hubspot-create-deal';
    }

    public function category(): NodeCategory
    {
        return NodeCategory::Action;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        $config = $context->config();

        // dealStage/pipeline/ownerId are literal IDs picked at design time, not per-execution
        // template values — same treatment as ClickUp's workspaceId/listId.
        $dealStage = trim((string) ($config['dealStage'] ?? ''));
        $pipeline = trim((string) ($config['pipeline'] ?? ''));
        $ownerId = trim((string) ($config['ownerId'] ?? ''));

        $dealName = $context->render((string) ($config['dealName'] ?? ''));
        $amount = isset($config['amount']) && $config['amount'] !== '' ? $context->render((string) $config['amount']) : '';
        $closeDate = isset($config['closeDate']) && $config['closeDate'] !== '' ? $context->render((string) $config['closeDate']) : '';
        $contactId = isset($config['contactId']) && $config['contactId'] !== '' ? $context->render((string) $config['contactId']) : '';

        if ($dealName === '' || $dealStage === '') {
            return NodeExecutionResult::fail('HubSpot: Create Deal node is missing its deal name or deal stage configuration.', false);
        }

        $properties = ['dealname' => $dealName, 'dealstage' => $dealStage];
        if ($pipeline !== '') {
            $properties['pipeline'] = $pipeline;
        }
        if ($amount !== '') {
            $properties['amount'] = $amount;
        }
        if ($closeDate !== '') {
            $properties['closedate'] = $closeDate;
        }
        if ($ownerId !== '') {
            $properties['hubspot_owner_id'] = $ownerId;
        }

        $associations = [];
        if ($contactId !== '' && is_numeric($contactId)) {
            $associations[] = [
                'to' => ['id' => (int) $contactId],
                'types' => [['associationCategory' => 'HUBSPOT_DEFINED', 'associationTypeId' => self::CONTACT_ASSOCIATION_TYPE_ID]],
            ];
        }

        $connection = IntegrationConnection::query()
            ->where('integration_provider_id', 'hubspot')
            ->where('tenant_id', $context->instance()->tenant_id)
            ->first();

        if ($connection === null) {
            return NodeExecutionResult::fail(
                'No HubSpot connection found for this tenant. Connect HubSpot via Integrations to enable deal creation.',
                retryable: false,
            );
        }

        try {
            $deal = $this->hubSpot->createDeal($connection, $properties, $associations);
        } catch (IntegrationException $e) {
            return NodeExecutionResult::fail($e->getMessage(), retryable: $e->status() >= 500 || $e->status() === 429);
        }

        return NodeExecutionResult::proceed(
            $context->plan()->outgoing($context->nodeKey()),
            ['hubspot_deal_id' => $deal['id'] ?? null],
        );
    }
}
