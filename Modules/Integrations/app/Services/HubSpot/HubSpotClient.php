<?php

namespace Modules\Integrations\Services\HubSpot;

use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Modules\Integrations\Exceptions\IntegrationException;
use Modules\Integrations\Models\IntegrationConnection;

/**
 * Authenticated calls to the HubSpot CRM API on behalf of a stored IntegrationConnection.
 * Unlike ClickUp, HubSpot OAuth access tokens expire — mirrors GmailClient's proactive
 * refresh-before-expiry pattern.
 */
class HubSpotClient
{
    private const API_BASE = 'https://api.hubapi.com';

    /**
     * @param  array<string, string>  $properties  HubSpot contact property name => value (blank entries should already be filtered out by the caller)
     * @return array<string, mixed> The created contact, as returned by HubSpot.
     */
    public function createContact(IntegrationConnection $connection, array $properties): array
    {
        $token = $this->freshAccessToken($connection);

        $response = Http::withToken($token)->post(self::API_BASE.'/crm/v3/objects/contacts', [
            'properties' => $properties,
        ]);

        return $this->jsonOrFail($response);
    }

    /**
     * @param  array<string, string>  $properties  HubSpot deal property name => value (blank entries should already be filtered out by the caller)
     * @param  list<array{to: array{id: int}, types: list<array{associationCategory: string, associationTypeId: int}>}>  $associations
     * @return array<string, mixed> The created deal, as returned by HubSpot.
     */
    public function createDeal(IntegrationConnection $connection, array $properties, array $associations = []): array
    {
        $token = $this->freshAccessToken($connection);

        $body = ['properties' => $properties];
        if ($associations !== []) {
            $body['associations'] = $associations;
        }

        $response = Http::withToken($token)->post(self::API_BASE.'/crm/v3/objects/deals', $body);

        return $this->jsonOrFail($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonOrFail(Response $response): array
    {
        if ($response->failed()) {
            $detail = $response->json('message') ?? $response->body();
            throw IntegrationException::apiCallFailed('hubspot', (string) $detail, $response->status());
        }

        return $response->json();
    }

    // ─── Token management ─────────────────────────────────────────────────────

    private function freshAccessToken(IntegrationConnection $connection): string
    {
        $authConfig = $connection->auth_config;

        $expiresAt = isset($authConfig['expires_at'])
            ? Carbon::parse($authConfig['expires_at'])
            : null;

        // Refresh proactively 5 minutes before expiry so we never send with a
        // token that might expire mid-flight.
        if ($expiresAt === null || $expiresAt->subMinutes(5)->isPast()) {
            return $this->refreshAccessToken($connection);
        }

        return Crypt::decryptString($authConfig['access_token']);
    }

    private function refreshAccessToken(IntegrationConnection $connection): string
    {
        $authConfig = $connection->auth_config;

        if (empty($authConfig['refresh_token'])) {
            throw new IntegrationException(
                'HubSpot connection has no refresh token. Please reconnect HubSpot via Integrations.',
            );
        }

        $response = Http::asForm()->post(config('integrations.providers.hubspot.token_url'), [
            'grant_type' => 'refresh_token',
            'client_id' => config('integrations.providers.hubspot.client_id'),
            'client_secret' => config('integrations.providers.hubspot.client_secret'),
            'refresh_token' => Crypt::decryptString($authConfig['refresh_token']),
        ]);

        if ($response->failed()) {
            throw new IntegrationException(
                'Failed to refresh HubSpot access token. Please reconnect HubSpot via Integrations.',
            );
        }

        $data = $response->json();
        $newToken = (string) $data['access_token'];
        $expiresIn = (int) ($data['expires_in'] ?? 1800);

        // Persist the new token and updated expiry; leave refresh_token unchanged.
        $connection->update([
            'auth_config' => array_merge($authConfig, [
                'access_token' => Crypt::encryptString($newToken),
                'expires_at' => now()->addSeconds($expiresIn)->toIso8601String(),
            ]),
        ]);

        return $newToken;
    }
}
