<?php

namespace Modules\Integrations\Contracts;

use Modules\Auth\Models\Tenant;
use Modules\Integrations\Models\IntegrationConnection;
use Modules\Integrations\Models\IntegrationProvider;

interface IntegrationDriver
{
    public function key(): string;

    public function connect(IntegrationProvider $provider, Tenant $tenant): string;

    public function callback(IntegrationProvider $provider, array $state, string $code): array;

    public function hydrateConnection(IntegrationProvider $provider, Tenant $tenant, array $authConfig, array $config): IntegrationConnection;
}
