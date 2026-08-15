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

class ClickUpApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(IntegrationsDatabaseSeeder::class);

        config()->set('integrations.providers.clickup.team_url', 'https://api.clickup.com/api/v2/team');
    }

    public function test_workspaces_returns_teams_for_connected_tenant(): void
    {
        $auth = $this->makeAuthenticatedUser(Role::BusinessOwner);
        $this->makeClickUpConnection($auth['tenant']);

        Http::fake([
            'https://api.clickup.com/api/v2/team' => Http::response([
                'teams' => [['id' => 'team-1', 'name' => 'Support']],
            ], 200),
        ]);

        $response = $this->actingAs($auth['user'], 'api')
            ->getJson('/api/v1/integrations/clickup/workspaces');

        $response->assertOk()
            ->assertJsonPath('data.0.id', 'team-1')
            ->assertJsonPath('data.0.name', 'Support');
    }

    public function test_workspaces_allows_manager_role(): void
    {
        $auth = $this->makeAuthenticatedUser(Role::Manager);
        $this->makeClickUpConnection($auth['tenant']);

        Http::fake([
            'https://api.clickup.com/api/v2/team' => Http::response(['teams' => []], 200),
        ]);

        $response = $this->actingAs($auth['user'], 'api')
            ->getJson('/api/v1/integrations/clickup/workspaces');

        $response->assertOk();
    }

    public function test_workspaces_rejects_employee_role(): void
    {
        $auth = $this->makeAuthenticatedUser(Role::Employee);

        $response = $this->actingAs($auth['user'], 'api')
            ->getJson('/api/v1/integrations/clickup/workspaces');

        $response->assertForbidden();
    }

    public function test_workspaces_returns_404_when_not_connected(): void
    {
        $auth = $this->makeAuthenticatedUser(Role::BusinessOwner);

        $response = $this->actingAs($auth['user'], 'api')
            ->getJson('/api/v1/integrations/clickup/workspaces');

        $response->assertNotFound()
            ->assertJsonPath('message', 'No clickup connection found for this tenant. Connect clickup via Integrations first.');
    }

    public function test_lists_aggregates_folder_and_folderless_lists_across_spaces(): void
    {
        $auth = $this->makeAuthenticatedUser(Role::BusinessOwner);
        $this->makeClickUpConnection($auth['tenant']);

        Http::fake([
            'https://api.clickup.com/api/v2/team/900/space?archived=false' => Http::response([
                'spaces' => [['id' => 'space-1', 'name' => 'Engineering']],
            ], 200),
            'https://api.clickup.com/api/v2/space/space-1/list?archived=false' => Http::response([
                'lists' => [['id' => 'list-1', 'name' => 'Backlog']],
            ], 200),
            'https://api.clickup.com/api/v2/space/space-1/folder?archived=false' => Http::response([
                'folders' => [['id' => 'folder-1', 'name' => 'Sprints']],
            ], 200),
            'https://api.clickup.com/api/v2/folder/folder-1/list?archived=false' => Http::response([
                'lists' => [['id' => 'list-2', 'name' => 'Sprint 14']],
            ], 200),
        ]);

        $response = $this->actingAs($auth['user'], 'api')
            ->getJson('/api/v1/integrations/clickup/workspaces/900/lists');

        $response->assertOk();
        $lists = $response->json('data');

        $this->assertCount(2, $lists);
        $this->assertSame(['id' => 'list-1', 'name' => 'Backlog', 'space' => 'Engineering', 'folder' => null], $lists[0]);
        $this->assertSame(['id' => 'list-2', 'name' => 'Sprint 14', 'space' => 'Engineering', 'folder' => 'Sprints'], $lists[1]);
    }

    public function test_lists_propagates_clickup_error_status_and_message(): void
    {
        $auth = $this->makeAuthenticatedUser(Role::BusinessOwner);
        $this->makeClickUpConnection($auth['tenant']);

        Http::fake([
            'https://api.clickup.com/api/v2/team/900/space?archived=false' => Http::response(['err' => 'Team not found'], 404),
        ]);

        $response = $this->actingAs($auth['user'], 'api')
            ->getJson('/api/v1/integrations/clickup/workspaces/900/lists');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'The clickup API returned an error: Team not found');
    }

    protected function makeClickUpConnection(Tenant $tenant): IntegrationConnection
    {
        return IntegrationConnection::query()->create([
            'integration_provider_id' => 'clickup',
            'tenant_id' => $tenant->id,
            'auth_config' => ['access_token' => Crypt::encryptString('clickup-access-token')],
            'config' => ['teams' => []],
        ]);
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
