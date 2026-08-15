<?php

namespace Modules\Workflows\Services\Execution\Executors;

use Modules\Integrations\Exceptions\IntegrationException;
use Modules\Integrations\Models\IntegrationConnection;
use Modules\Integrations\Services\HubSpot\HubSpotClient;
use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;
use Modules\Workflows\Services\Execution\Data\NodeExecutionResult;
use Modules\Workflows\Services\Execution\NodeExecutionContext;

class HubSpotCreateContactExecutor implements NodeExecutor
{
    public function __construct(protected HubSpotClient $hubSpot) {}

    public function type(): string
    {
        return 'hubspot-create-contact';
    }

    public function category(): NodeCategory
    {
        return NodeCategory::Action;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        $config = $context->config();

        $properties = [];
        foreach (['firstName' => 'firstname', 'lastName' => 'lastname', 'email' => 'email', 'phone' => 'phone'] as $configKey => $hubspotProperty) {
            $value = isset($config[$configKey]) ? $context->render((string) $config[$configKey]) : '';
            if ($value !== '') {
                $properties[$hubspotProperty] = $value;
            }
        }

        if (! isset($properties['firstname']) && ! isset($properties['lastname']) && ! isset($properties['email'])) {
            return NodeExecutionResult::fail('HubSpot: Create Contact node needs at least one of first name, last name, or email.', false);
        }

        $connection = IntegrationConnection::query()
            ->where('integration_provider_id', 'hubspot')
            ->where('tenant_id', $context->instance()->tenant_id)
            ->first();

        if ($connection === null) {
            return NodeExecutionResult::fail(
                'No HubSpot connection found for this tenant. Connect HubSpot via Integrations to enable contact creation.',
                retryable: false,
            );
        }

        try {
            $contact = $this->hubSpot->createContact($connection, $properties);
        } catch (IntegrationException $e) {
            return NodeExecutionResult::fail($e->getMessage(), retryable: $e->status() >= 500 || $e->status() === 429);
        }

        return NodeExecutionResult::proceed(
            $context->plan()->outgoing($context->nodeKey()),
            ['hubspot_contact_id' => $contact['id'] ?? null],
        );
    }
}
