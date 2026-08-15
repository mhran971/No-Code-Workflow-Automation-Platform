<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Models\Tenant;
use Modules\Integrations\Exceptions\IntegrationException;
use Modules\Integrations\Models\IntegrationConnection;
use Modules\Integrations\Models\IntegrationProvider;
use Modules\Integrations\Services\HubSpot\HubSpotClient;
use Tests\TestCase;

class HubSpotClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('integrations.providers.hubspot.token_url', 'https://api.hubapi.com/oauth/v1/token');
        config()->set('integrations.providers.hubspot.client_id', 'hubspot-client-id');
        config()->set('integrations.providers.hubspot.client_secret', 'hubspot-client-secret');
    }

    public function test_creates_contact_with_a_still_fresh_token(): void
    {
        $connection = $this->makeConnection(expiresInMinutes: 30);

        Http::fake([
            'https://api.hubapi.com/crm/v3/objects/contacts' => Http::response([
                'id' => '512',
                'properties' => ['email' => 'jane@example.com'],
            ], 200),
        ]);

        $client = new HubSpotClient;
        $result = $client->createContact($connection, ['email' => 'jane@example.com']);

        $this->assertSame('512', $result['id']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.hubapi.com/crm/v3/objects/contacts'
                && $request->hasHeader('Authorization', 'Bearer valid-access-token')
                && $request['properties']['email'] === 'jane@example.com';
        });
    }

    public function test_refreshes_expired_token_before_calling_hubspot(): void
    {
        $connection = $this->makeConnection(expiresInMinutes: -10);

        Http::fake([
            'https://api.hubapi.com/oauth/v1/token' => Http::response([
                'access_token' => 'refreshed-access-token',
                'expires_in' => 1800,
            ], 200),
            'https://api.hubapi.com/crm/v3/objects/contacts' => Http::response(['id' => '512'], 200),
        ]);

        $client = new HubSpotClient;
        $client->createContact($connection, ['email' => 'jane@example.com']);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.hubapi.com/oauth/v1/token'
            && $request['grant_type'] === 'refresh_token');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.hubapi.com/crm/v3/objects/contacts'
            && $request->hasHeader('Authorization', 'Bearer refreshed-access-token'));

        $connection->refresh();
        $this->assertSame('refreshed-access-token', Crypt::decryptString($connection->auth_config['access_token']));
    }

    public function test_throws_with_hubspot_status_and_message_on_failure(): void
    {
        $connection = $this->makeConnection(expiresInMinutes: 30);

        Http::fake([
            'https://api.hubapi.com/crm/v3/objects/contacts' => Http::response([
                'message' => 'Contact already exists',
            ], 409),
        ]);

        $client = new HubSpotClient;

        try {
            $client->createContact($connection, ['email' => 'jane@example.com']);
            $this->fail('Expected IntegrationException was not thrown.');
        } catch (IntegrationException $e) {
            $this->assertSame(409, $e->status());
            $this->assertStringContainsString('Contact already exists', $e->getMessage());
        }
    }

    private function makeConnection(int $expiresInMinutes): IntegrationConnection
    {
        $tenant = Tenant::query()->create(['business_name' => 'Acme', 'business_type' => BusinessType::SaaS->value]);

        IntegrationProvider::query()->firstOrCreate(['id' => 'hubspot'], [
            'id' => 'hubspot',
            'name' => 'HubSpot',
            'auth_type' => 'oauth2',
            'config_schema' => [],
            'auth_schema' => [],
            'is_active' => true,
        ]);

        return IntegrationConnection::query()->create([
            'integration_provider_id' => 'hubspot',
            'tenant_id' => $tenant->id,
            'auth_config' => [
                'access_token' => Crypt::encryptString('valid-access-token'),
                'refresh_token' => Crypt::encryptString('refresh-token'),
                'expires_at' => now()->addMinutes($expiresInMinutes)->toIso8601String(),
            ],
            'config' => ['portal_id' => '12345'],
        ]);
    }
}
