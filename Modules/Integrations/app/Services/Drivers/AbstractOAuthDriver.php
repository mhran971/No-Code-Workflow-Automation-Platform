<?php

namespace Modules\Integrations\Services\Drivers;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Auth\Models\Tenant;
use Modules\Integrations\Contracts\IntegrationDriver;
use Modules\Integrations\Exceptions\IntegrationException;
use Modules\Integrations\Models\IntegrationConnection;
use Modules\Integrations\Models\IntegrationProvider;

abstract class AbstractOAuthDriver implements IntegrationDriver
{
    public function connect(IntegrationProvider $provider, Tenant $tenant): string
    {
        $state = $this->makeState($provider, $tenant);

        $parameters = array_merge($this->authorizationParameters($provider, $tenant, $state), [
            'state' => $state,
            'redirect_uri' => route('api.integrations.callback', ['provider' => $provider]),
            'client_id' => $this->clientId($provider),
        ]);

        return $this->authorizationUrl($provider).'?'.http_build_query($parameters);
    }

    public function hydrateConnection(IntegrationProvider $provider, Tenant $tenant, array $authConfig, array $config): IntegrationConnection
    {
        return IntegrationConnection::query()->updateOrCreate([
            'integration_provider_id' => $provider->id,
            'tenant_id' => (int) $tenant->id,
        ], [
            'auth_config' => $authConfig,
            'config' => $config,
        ]);
    }

    protected function makeState(IntegrationProvider $provider, Tenant $tenant): string
    {
        return Crypt::encryptString(json_encode([
            'provider' => $provider->id,
            'tenant_id' => (int) $tenant->id,
            'nonce' => (string) Str::uuid(),
        ], JSON_THROW_ON_ERROR));
    }

    protected function clientId(IntegrationProvider $provider): string
    {
        return (string) config("integrations.providers.{$provider->id}.client_id");
    }

    protected function clientSecret(IntegrationProvider $provider): string
    {
        return (string) config("integrations.providers.{$provider->id}.client_secret");
    }

    protected function scopes(IntegrationProvider $provider): ?string
    {
        $scopes = config("integrations.providers.{$provider->id}.scopes");

        return is_string($scopes) && $scopes !== '' ? $scopes : null;
    }

    protected function authorizationUrl(IntegrationProvider $provider): string
    {
        return (string) config("integrations.providers.{$provider->id}.authorization_url");
    }

    protected function tokenUrl(IntegrationProvider $provider): string
    {
        return (string) config("integrations.providers.{$provider->id}.token_url");
    }

    protected function getStateData(array $state, string $key, mixed $default = null): mixed
    {
        return $state[$key] ?? $default;
    }

    protected function postForm(string $providerKey, string $url, array $payload): array
    {
        try {
            return Http::asForm()->post($url, $payload)->throw()->json();
        } catch (RequestException) {
            throw IntegrationException::tokenExchangeFailed($providerKey);
        }
    }

    abstract protected function authorizationParameters(IntegrationProvider $provider, Tenant $tenant, string $state): array;

    abstract public function callback(IntegrationProvider $provider, array $state, string $code): array;
}
