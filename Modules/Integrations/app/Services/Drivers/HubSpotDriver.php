<?php

namespace Modules\Integrations\Services\Drivers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Modules\Auth\Models\Tenant;
use Modules\Integrations\Exceptions\IntegrationException;
use Modules\Integrations\Models\IntegrationProvider;

class HubSpotDriver extends AbstractOAuthDriver
{
    public function key(): string
    {
        return 'hubspot';
    }

    protected function authorizationParameters(IntegrationProvider $provider, Tenant $tenant, string $state): array
    {
        return [
            'scope' => $this->scopes($provider),
            'state' => $state,
        ];
    }

    public function callback(IntegrationProvider $provider, array $state, string $code): array
    {
        $response = Http::asForm()->post($this->tokenUrl($provider), [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId($provider),
            'client_secret' => $this->clientSecret($provider),
            'redirect_uri' => route('api.integrations.callback'),
            'code' => $code,
        ])->throw()->json();

        $accessToken = (string) ($response['access_token'] ?? '');

        if ($accessToken === '') {
            throw IntegrationException::tokenExchangeFailed($provider->id);
        }

        return [
            'auth_config' => [
                'access_token' => Crypt::encryptString($accessToken),
                'refresh_token' => Crypt::encryptString((string) ($response['refresh_token'] ?? '')),
                'expires_at' => isset($response['expires_in'])
                    ? CarbonImmutable::now()->addSeconds((int) $response['expires_in'])->toIso8601String()
                    : null,
            ],
            'config' => [
                'portal_id' => $response['hub_id'] ?? null,
            ],
        ];
    }
}
