<?php

namespace Modules\Integrations\Services\Drivers;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Modules\Auth\Models\Tenant;
use Modules\Integrations\Exceptions\IntegrationException;
use Modules\Integrations\Models\IntegrationProvider;

class ClickUpDriver extends AbstractOAuthDriver
{
    public function key(): string
    {
        return 'clickup';
    }

    protected function authorizationParameters(IntegrationProvider $provider, Tenant $tenant, string $state): array
    {
        return [];
    }

    public function callback(IntegrationProvider $provider, array $state, string $code): array
    {
        $response = Http::asForm()->post($this->tokenUrl($provider), [
            'client_id' => $this->clientId($provider),
            'client_secret' => $this->clientSecret($provider),
            'code' => $code,
        ])->throw()->json();

        $accessToken = (string) ($response['access_token'] ?? '');

        if ($accessToken === '') {
            throw IntegrationException::tokenExchangeFailed($provider->id);
        }

        $teamsResponse = Http::withToken($accessToken)
            ->get((string) config('integrations.providers.clickup.team_url'))
            ->throw()
            ->json();

        return [
            'auth_config' => [
                'access_token' => Crypt::encryptString($accessToken),
            ],
            'config' => [
                'teams' => $teamsResponse['teams'] ?? [],
            ],
        ];
    }
}
