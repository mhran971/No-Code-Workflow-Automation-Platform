<?php

namespace Modules\Integrations\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Models\Tenant;
use Modules\Integrations\Contracts\IntegrationAction;
use Modules\Integrations\Contracts\IntegrationDriver;
use Modules\Integrations\Exceptions\IntegrationException;
use Modules\Integrations\Models\IntegrationConnection;
use Modules\Integrations\Models\IntegrationProvider;

class IntegrationManager
{
    public function __construct(
        protected SchemaValidator $schemaValidator
    ) {}

    public function connect(IntegrationProvider $provider, Tenant $tenant): string
    {
        if (! $provider->is_active) {
            throw IntegrationException::inactiveProvider($provider->id);
        }

        return $this->driverFor($provider)->connect($provider, $tenant);
    }

    /**
     * @throws ValidationException
     */
    public function callback(string $state, string $code): IntegrationConnection
    {
        $payload = $this->decodeState($state);
        $provider = IntegrationProvider::query()->whereKey($payload['provider'])->first();

        if ($provider === null) {
            throw IntegrationException::unknownProvider((string) $payload['provider']);
        }

        if (! $provider->is_active) {
            throw IntegrationException::inactiveProvider($provider->id);
        }

        $driver = $this->driverFor($provider);
        $connectionData = $driver->callback($provider, $payload, $code);

        $this->schemaValidator->validateForProvider(
            $provider,
            $connectionData['auth_config'] ?? [],
            $connectionData['config'] ?? []
        );

        $tenant = Tenant::query()->findOrFail((int) $payload['tenant_id']);

        return $driver->hydrateConnection(
            $provider,
            $tenant,
            $connectionData['auth_config'] ?? [],
            $connectionData['config'] ?? []
        );
    }

    public function runAction(string $action, array $payload, ?IntegrationConnection $connection = null): mixed
    {
        $actionClass = config("integrations.actions.{$action}.driver");

        if (! is_string($actionClass) || $actionClass === '' || ! class_exists($actionClass)) {
            throw IntegrationException::actionNotConfigured($action);
        }

        $resolved = app($actionClass);

        if (! $resolved instanceof IntegrationAction) {
            throw IntegrationException::actionNotConfigured($action);
        }

        return $resolved->handle($payload, $connection);
    }

    protected function driverFor(IntegrationProvider $provider): IntegrationDriver
    {
        $driverClass = config("integrations.providers.{$provider->id}.driver");

        if (! is_string($driverClass) || $driverClass === '' || ! class_exists($driverClass)) {
            throw IntegrationException::unknownProvider($provider->id);
        }

        $driver = app($driverClass);

        if (! $driver instanceof IntegrationDriver) {
            throw IntegrationException::unknownProvider($provider->id);
        }

        return $driver;
    }

    protected function decodeState(string $state): array
    {
        try {
            $decoded = json_decode(Crypt::decryptString($state), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw IntegrationException::invalidState();
        }

        if (! is_array($decoded)) {
            throw IntegrationException::invalidState();
        }

        foreach (['provider', 'tenant_id'] as $required) {
            if (! array_key_exists($required, $decoded)) {
                throw IntegrationException::invalidState();
            }
        }

        return $decoded;
    }
}
