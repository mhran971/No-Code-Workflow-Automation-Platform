<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;
use Modules\Integrations\Database\Seeders\IntegrationsDatabaseSeeder;
use Modules\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class IntegrationsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(IntegrationsDatabaseSeeder::class);

        config()->set('integrations.providers.clickup.client_id', 'clickup-client-id');
        config()->set('integrations.providers.clickup.client_secret', 'clickup-client-secret');
        config()->set('integrations.providers.clickup.authorization_url', 'https://app.clickup.com/api');
        config()->set('integrations.providers.clickup.token_url', 'https://api.clickup.com/api/v2/oauth/token');
        config()->set('integrations.providers.clickup.team_url', 'https://api.clickup.com/api/v2/team');
    }

    public function test_connect_redirects_to_provider_with_encrypted_state(): void
    {
        foreach ([Role::BusinessOwner, Role::Manager] as $role) {
            $auth = $this->makeAuthenticatedUser($role);

            $response = $this->actingAs($auth['user'], 'api')
                ->post('/api/v1/integrations/clickup/connect');

            $response->assertRedirect();

            $location = $response->headers->get('Location');
            $this->assertNotNull($location);

            $query = [];
            parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

            $this->assertSame('clickup-client-id', $query['client_id'] ?? null);
            $this->assertArrayHasKey('state', $query);

            $state = json_decode(Crypt::decryptString($query['state']), true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame('clickup', $state['provider']);
            $this->assertSame((int) $auth['tenant']->id, (int) $state['tenant_id']);
            $this->assertArrayNotHasKey('user_id', $state);
        }
    }

    public function test_connect_rejects_unauthorized_roles(): void
    {
        $auth = $this->makeAuthenticatedUser(Role::Employee);

        $response = $this->actingAs($auth['user'], 'api')
            ->post('/api/v1/integrations/clickup/connect');

        $response->assertForbidden()
            ->assertJsonPath('message', 'You do not have the required role to access this resource.');
    }

    public function test_callback_stores_connection_and_redirects_back_to_integrations_page(): void
    {
        $auth = $this->makeAuthenticatedUser();
        $state = Crypt::encryptString(json_encode([
            'provider' => 'clickup',
            'tenant_id' => (int) $auth['tenant']->id,
            'nonce' => 'test-nonce',
        ], JSON_THROW_ON_ERROR));

        Http::fake([
            'https://api.clickup.com/api/v2/oauth/token' => Http::response([
                'access_token' => 'clickup-access-token',
            ], 200),
            'https://api.clickup.com/api/v2/team' => Http::response([
                'teams' => [
                    ['id' => 'team-1', 'name' => 'Support'],
                ],
            ], 200),
        ]);

        $response = $this->get('/api/v1/integrations/callback?code=test-code&state='.urlencode($state));

        $response->assertRedirect(route('integrations.index').'?success=clickup');

        $connection = IntegrationConnection::query()->firstOrFail();

        $this->assertSame('clickup', $connection->integration_provider_id);
        $this->assertSame((int) $auth['tenant']->id, (int) $connection->tenant_id);
        $this->assertSame('Support', $connection->config['teams'][0]['name']);
        $this->assertSame('clickup-access-token', Crypt::decryptString($connection->auth_config['access_token']));
    }

    public function test_callback_rejects_invalid_state(): void
    {
        $this->makeAuthenticatedUser();

        $response = $this->getJson('/api/v1/integrations/callback?code=test-code&state=not-a-valid-state');

        $response->assertStatus(422)
            ->assertJsonPath('message', 'The integration state is invalid or expired.');
    }

    protected function makeAuthenticatedUser(Role $role = Role::BusinessOwner): array
    {
        $tenant = Tenant::query()->create([
            'business_name' => 'Acme Inc',
            'business_type' => BusinessType::SaaS->value,
        ]);

        $user = User::query()->create([
            'first_name' => 'Ava',
            'last_name' => 'Owner',
            'name' => 'Ava Owner',
            'email' => 'ava-owner-'.uniqid().'@example.test',
            'password' => 'Password123!',
            'tenant_id' => $tenant->id,
            'role' => $role,
            'is_active' => true,
        ]);

        return [
            'tenant' => $tenant,
            'user' => $user,
        ];
    }
}
