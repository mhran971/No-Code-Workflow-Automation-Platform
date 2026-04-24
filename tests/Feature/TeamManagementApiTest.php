<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;
use Modules\Team\Models\Team;
use Tests\TestCase;

class TeamManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_owner_can_create_team_and_promote_manager_with_audit_trail(): void
    {
        $ownerAuth = $this->registerAndLoginOwner();
        $owner = $ownerAuth['owner'];

        $candidate = $this->createTenantUser(
            (int) $owner->tenant_id,
            'manager-candidate-'.uniqid().'@example.test'
        );

        $response = $this->postJson('/api/v1/team/teams', [
            'name' => 'Operations',
            'manager_id' => $candidate->id,
        ], $this->authHeaders($ownerAuth['token']));

        $response->assertCreated()
            ->assertJsonPath('message', 'Team created successfully.')
            ->assertJsonPath('team.name', 'Operations')
            ->assertJsonPath('team.manager.id', $candidate->id)
            ->assertJsonPath('team.manager.role', Role::Manager->value);

        $teamId = (int) $response->json('team.id');

        $this->assertDatabaseHas('teams', [
            'id' => $teamId,
            'tenant_id' => $owner->tenant_id,
            'name' => 'Operations',
            'manager_id' => $candidate->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $candidate->id,
            'role' => Role::Manager->value,
        ]);

        $this->assertDatabaseHas('team_memberships', [
            'tenant_id' => $owner->tenant_id,
            'user_id' => $candidate->id,
            'team_id' => $teamId,
        ]);

        $this->assertDatabaseHas('audit_trails', [
            'tenant_id' => $owner->tenant_id,
            'actor_user_id' => $owner->id,
            'action' => 'team_created',
            'subject_type' => Team::class,
            'subject_id' => $teamId,
        ]);
    }

    public function test_team_creation_requires_manager(): void
    {
        $ownerAuth = $this->registerAndLoginOwner();

        $response = $this->postJson('/api/v1/team/teams', [
            'name' => 'Support',
        ], $this->authHeaders($ownerAuth['token']));

        $response->assertStatus(422)->assertJsonValidationErrors(['manager_id']);
    }

    public function test_team_name_must_be_unique_per_tenant(): void
    {
        $ownerAuth = $this->registerAndLoginOwner();
        $owner = $ownerAuth['owner'];

        $firstManager = $this->createTenantUser(
            (int) $owner->tenant_id,
            'first-manager-'.uniqid().'@example.test'
        );

        $secondManager = $this->createTenantUser(
            (int) $owner->tenant_id,
            'second-manager-'.uniqid().'@example.test'
        );

        $this->createTeam($ownerAuth['token'], 'Sales', $firstManager->id);

        $duplicateResponse = $this->postJson('/api/v1/team/teams', [
            'name' => 'Sales',
            'manager_id' => $secondManager->id,
        ], $this->authHeaders($ownerAuth['token']));

        $duplicateResponse->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_business_owner_can_replace_manager_and_previous_manager_is_demoted(): void
    {
        $ownerAuth = $this->registerAndLoginOwner();
        $owner = $ownerAuth['owner'];

        $previousManager = $this->createTenantUser(
            (int) $owner->tenant_id,
            'previous-manager-'.uniqid().'@example.test'
        );

        $newManager = $this->createTenantUser(
            (int) $owner->tenant_id,
            'new-manager-'.uniqid().'@example.test'
        );

        $teamId = $this->createTeam($ownerAuth['token'], 'Marketing', $previousManager->id);

        $response = $this->patchJson("/api/v1/team/teams/{$teamId}/manager", [
            'manager_id' => $newManager->id,
        ], $this->authHeaders($ownerAuth['token']));

        $response->assertOk()
            ->assertJsonPath('message', 'Team manager updated successfully.')
            ->assertJsonPath('team.id', $teamId)
            ->assertJsonPath('team.manager.id', $newManager->id)
            ->assertJsonPath('team.manager.role', Role::Manager->value);

        $this->assertDatabaseHas('teams', [
            'id' => $teamId,
            'manager_id' => $newManager->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $previousManager->id,
            'role' => Role::Employee->value,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $newManager->id,
            'role' => Role::Manager->value,
        ]);

        $this->assertDatabaseHas('audit_trails', [
            'tenant_id' => $owner->tenant_id,
            'actor_user_id' => $owner->id,
            'action' => 'team_manager_updated',
            'subject_type' => Team::class,
            'subject_id' => $teamId,
        ]);
    }

    public function test_business_owner_can_add_and_remove_members_with_audit_trail(): void
    {
        $ownerAuth = $this->registerAndLoginOwner();
        $owner = $ownerAuth['owner'];

        $manager = $this->createTenantUser((int) $owner->tenant_id, 'team-manager-'.uniqid().'@example.test');
        $member = $this->createTenantUser((int) $owner->tenant_id, 'member-'.uniqid().'@example.test');

        $teamId = $this->createTeam($ownerAuth['token'], 'Quality', $manager->id);

        $addResponse = $this->postJson("/api/v1/team/teams/{$teamId}/members", [
            'user_id' => $member->id,
        ], $this->authHeaders($ownerAuth['token']));

        $addResponse->assertOk()
            ->assertJsonPath('message', 'Team member added successfully.')
            ->assertJsonPath('member.id', $member->id);

        $this->assertDatabaseHas('team_memberships', [
            'tenant_id' => $owner->tenant_id,
            'user_id' => $member->id,
            'team_id' => $teamId,
        ]);

        $this->assertDatabaseHas('audit_trails', [
            'tenant_id' => $owner->tenant_id,
            'actor_user_id' => $owner->id,
            'action' => 'team_member_added',
            'subject_type' => Team::class,
            'subject_id' => $teamId,
        ]);

        $teamDetailsResponse = $this->getJson(
            "/api/v1/team/teams/{$teamId}",
            $this->authHeaders($ownerAuth['token'])
        );

        $teamDetailsResponse->assertOk()
            ->assertJsonPath('data.id', $teamId)
            ->assertJsonFragment([
                'id' => $member->id,
                'email' => $member->email,
            ]);

        $removeResponse = $this->deleteJson(
            "/api/v1/team/teams/{$teamId}/members/{$member->id}",
            [],
            $this->authHeaders($ownerAuth['token'])
        );

        $removeResponse->assertOk()
            ->assertJsonPath('message', 'Team member removed successfully.');

        $this->assertDatabaseHas('team_memberships', [
            'tenant_id' => $owner->tenant_id,
            'user_id' => $member->id,
            'team_id' => null,
        ]);

        $this->assertDatabaseHas('audit_trails', [
            'tenant_id' => $owner->tenant_id,
            'actor_user_id' => $owner->id,
            'action' => 'team_member_removed',
            'subject_type' => Team::class,
            'subject_id' => $teamId,
        ]);
    }

    public function test_manager_can_manage_only_own_team_and_cannot_access_other_teams(): void
    {
        $ownerAuth = $this->registerAndLoginOwner();
        $owner = $ownerAuth['owner'];

        $managerOne = $this->createTenantUser((int) $owner->tenant_id, 'manager-one-'.uniqid().'@example.test');
        $managerTwo = $this->createTenantUser((int) $owner->tenant_id, 'manager-two-'.uniqid().'@example.test');
        $member = $this->createTenantUser((int) $owner->tenant_id, 'member-user-'.uniqid().'@example.test');

        $teamOneId = $this->createTeam($ownerAuth['token'], 'Team One', $managerOne->id);
        $teamTwoId = $this->createTeam($ownerAuth['token'], 'Team Two', $managerTwo->id);

        $managerOneToken = $this->loginAndGetToken($managerOne->email);

        $this->postJson("/api/v1/team/teams/{$teamOneId}/members", [
            'user_id' => $member->id,
        ], $this->authHeaders($managerOneToken))->assertOk();

        $this->deleteJson(
            "/api/v1/team/teams/{$teamOneId}/members/{$member->id}",
            [],
            $this->authHeaders($managerOneToken)
        )->assertOk();

        $this->postJson("/api/v1/team/teams/{$teamTwoId}/members", [
            'user_id' => $member->id,
        ], $this->authHeaders($managerOneToken))->assertForbidden();

        $teamsResponse = $this->getJson('/api/v1/team/teams', $this->authHeaders($managerOneToken));
        $teamsResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $teamOneId);

        $this->getJson("/api/v1/team/teams/{$teamTwoId}", $this->authHeaders($managerOneToken))
            ->assertForbidden();
    }

    public function test_user_cannot_be_added_to_two_teams(): void
    {
        $ownerAuth = $this->registerAndLoginOwner();
        $owner = $ownerAuth['owner'];

        $managerOne = $this->createTenantUser((int) $owner->tenant_id, 'manager-a-'.uniqid().'@example.test');
        $managerTwo = $this->createTenantUser((int) $owner->tenant_id, 'manager-b-'.uniqid().'@example.test');
        $member = $this->createTenantUser((int) $owner->tenant_id, 'shared-member-'.uniqid().'@example.test');

        $teamOneId = $this->createTeam($ownerAuth['token'], 'Alpha', $managerOne->id);
        $teamTwoId = $this->createTeam($ownerAuth['token'], 'Beta', $managerTwo->id);

        $this->postJson("/api/v1/team/teams/{$teamOneId}/members", [
            'user_id' => $member->id,
        ], $this->authHeaders($ownerAuth['token']))->assertOk();

        $secondAddResponse = $this->postJson("/api/v1/team/teams/{$teamTwoId}/members", [
            'user_id' => $member->id,
        ], $this->authHeaders($ownerAuth['token']));

        $secondAddResponse->assertStatus(422)->assertJsonValidationErrors(['user_id']);
    }

    public function test_manager_cannot_create_new_user_accounts(): void
    {
        $ownerAuth = $this->registerAndLoginOwner();
        $owner = $ownerAuth['owner'];

        $manager = $this->createTenantUser((int) $owner->tenant_id, 'manager-create-user-'.uniqid().'@example.test');
        $this->createTeam($ownerAuth['token'], 'User Admin Team', $manager->id);

        $managerToken = $this->loginAndGetToken($manager->email);

        $response = $this->postJson('/api/v1/team/users', [
            'first_name' => 'Blocked',
            'last_name' => 'User',
            'email' => 'blocked-'.uniqid().'@example.test',
        ], $this->authHeaders($managerToken));

        $response->assertForbidden();
    }

    public function test_business_owner_can_add_many_members_in_one_request(): void
    {
        $ownerAuth = $this->registerAndLoginOwner();
        $owner = $ownerAuth['owner'];

        $manager = $this->createTenantUser((int) $owner->tenant_id, 'bulk-manager-'.uniqid().'@example.test');
        $memberOne = $this->createTenantUser((int) $owner->tenant_id, 'bulk-member-1-'.uniqid().'@example.test');
        $memberTwo = $this->createTenantUser((int) $owner->tenant_id, 'bulk-member-2-'.uniqid().'@example.test');

        $teamId = $this->createTeam($ownerAuth['token'], 'Bulk Team', $manager->id);

        $response = $this->postJson("/api/v1/team/teams/{$teamId}/members", [
            'members' => [$memberOne->id, $memberTwo->id],
        ], $this->authHeaders($ownerAuth['token']));

        $response->assertOk()
            ->assertJsonPath('message', 'Team members added successfully.')
            ->assertJsonCount(2, 'members');

        $ids = collect($response->json('members'))->pluck('id')->all();
        $this->assertContains($memberOne->id, $ids);
        $this->assertContains($memberTwo->id, $ids);

        $this->assertDatabaseHas('team_memberships', [
            'tenant_id' => $owner->tenant_id,
            'user_id' => $memberOne->id,
            'team_id' => $teamId,
        ]);

        $this->assertDatabaseHas('team_memberships', [
            'tenant_id' => $owner->tenant_id,
            'user_id' => $memberTwo->id,
            'team_id' => $teamId,
        ]);

        $this->assertDatabaseHas('audit_trails', [
            'tenant_id' => $owner->tenant_id,
            'actor_user_id' => $owner->id,
            'action' => 'team_member_added',
            'subject_type' => Team::class,
            'subject_id' => $teamId,
        ]);
    }

    public function test_business_owner_can_list_same_tenant_manager_candidates_only(): void
    {
        $ownerAuth = $this->registerAndLoginOwner();
        $owner = $ownerAuth['owner'];

        $eligibleUser = $this->createTenantUser(
            (int) $owner->tenant_id,
            'eligible-user-'.uniqid().'@example.test'
        );

        $alreadyAssignedManager = $this->createTenantUser(
            (int) $owner->tenant_id,
            'assigned-manager-'.uniqid().'@example.test'
        );

        $inactiveUser = $this->createTenantUser(
            (int) $owner->tenant_id,
            'inactive-user-'.uniqid().'@example.test'
        );
        $inactiveUser->forceFill(['is_active' => false])->save();

        $this->createTeam($ownerAuth['token'], 'Assigned Team', $alreadyAssignedManager->id);

        $otherTenant = Tenant::query()->create([
            'business_name' => 'Other Tenant',
            'business_type' => BusinessType::SaaS->value,
        ]);

        $otherTenantUser = $this->createTenantUser(
            (int) $otherTenant->id,
            'other-tenant-user-'.uniqid().'@example.test'
        );

        $response = $this->getJson(
            '/api/v1/team/manager-candidates',
            $this->authHeaders($ownerAuth['token'])
        );

        $response->assertOk();

        $ids = collect($response->json('data'))
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $this->assertContains((int) $eligibleUser->id, $ids);
        $this->assertNotContains((int) $alreadyAssignedManager->id, $ids);
        $this->assertNotContains((int) $inactiveUser->id, $ids);
        $this->assertNotContains((int) $owner->id, $ids);
        $this->assertNotContains((int) $otherTenantUser->id, $ids);
    }

    private function createTeam(string $ownerToken, string $name, int $managerId): int
    {
        $response = $this->postJson('/api/v1/team/teams', [
            'name' => $name,
            'manager_id' => $managerId,
        ], $this->authHeaders($ownerToken));

        $response->assertCreated();

        return (int) $response->json('team.id');
    }

    private function registerAndLoginOwner(): array
    {
        $email = 'owner-'.uniqid().'@example.test';
        $password = 'Pass1234!';

        $this->postJson('/api/v1/register', [
            'first_name' => 'Omar',
            'last_name' => 'Owner',
            'email' => $email,
            'business_type' => BusinessType::SaaS->value,
            'password' => $password,
            'password_confirmation' => $password,
            'captcha_token' => 'test-token',
        ])->assertCreated();

        $token = $this->loginAndGetToken($email, $password);

        return [
            'owner' => User::query()->where('email', $email)->firstOrFail(),
            'token' => $token,
        ];
    }

    private function createTenantUser(
        int $tenantId,
        string $email,
        Role $role = Role::Employee,
        string $password = 'Pass1234!'
    ): User {
        return User::query()->create([
            'first_name' => 'Team',
            'last_name' => 'Member',
            'name' => 'Team Member',
            'email' => $email,
            'password' => Hash::make($password),
            'tenant_id' => $tenantId,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function loginAndGetToken(string $email, string $password = 'Pass1234!'): string
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $response->assertOk();

        return (string) ($response->json('token') ?? $response->json('data.token'));
    }

    private function authHeaders(string $token): array
    {
        return [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ];
    }
}
