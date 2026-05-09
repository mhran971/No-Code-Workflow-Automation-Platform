<?php

namespace Modules\Integrations\Contracts;

use Modules\Integrations\Models\IntegrationConnection;

interface IntegrationAction
{
    public function key(): string;

    public function handle(array $payload, ?IntegrationConnection $connection = null): mixed;
}
