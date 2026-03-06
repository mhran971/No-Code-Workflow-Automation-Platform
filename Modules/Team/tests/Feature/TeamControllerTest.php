<?php

namespace Modules\Team\Tests\Feature;

use Modules\Team\Tests\TestCase;
use Modules\Team\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TeamControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that unauthenticated requests are rejected.
     */
    public function test_unauthenticated_requests_are_rejected(): void
    {
        $response = $this->getJson('/api/teams');
        $response->assertStatus(401);
    }

    /**
     * Test listing teams returns success.
     */
    public function test_can_list_teams(): void
    {
        $this->actingAsUser();
        $this->createTeams(3);

        $response = $this->getJson('/api/teams');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    /**
     * Test listing teams with search filter.
     */
    public function test_can_list_teams_with_search(): void
    {
        $this->actingAsUser();
        
        $this->createTeam(['name' => 'Marketing Team']);
        $this->createTeam(['name' => 'Sales Team']);
        $this->createTeam(['name' => 'HR Team']);

        $response = $this->getJson('/api/teams?search=market');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Marketing Team');
    }

    /**
     * Test listing teams returns empty when no match.
     */
    public function test_list_teams_returns_empty_when_no_match(): void
    {
        $this->actingAsUser();
        
        $this->createTeam(['name' => 'Marketing Team']);

        $response = $this->getJson('/api/teams?search=nonexistent');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    /**
     * Test can create a team.
     */
    public function test_can_create_team(): void
    {
        $this->actingAsUser();
        
        $teamData = [
            'name' => 'New Team',
            'description' => 'A new team description',
        ];

        $response = $this->postJson('/api/teams', $teamData);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'New Team')
            ->assertJsonPath('data.description', 'A new team description');

        $this->assertDatabaseHas('teams', [
            'name' => 'New Team',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /**
     * Test create team validation - name required.
     */
    public function test_create_team_requires_name(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/teams', [
            'description' => 'Test description',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * Test create team validation - name max length.
     */
    public function test_create_team_name_max_length(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/teams', [
            'name' => str_repeat('a', 256),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * Test create team validation - description max length.
     */
    public function test_create_team_description_max_length(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/teams', [
            'name' => 'Test Team',
            'description' => str_repeat('a', 1001),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    /**
     * Test create team - duplicate name within tenant.
     */
    public function test_cannot_create_duplicate_team_name_in_tenant(): void
    {
        $this->actingAsUser();
        
        $this->createTeam(['name' => 'Existing Team']);

        $response = $this->postJson('/api/teams', [
            'name' => 'Existing Team',
        ]);

        $response->assertStatus(500);
    }

    /**
     * Test can update a team.
     */
    public function test_can_update_team(): void
    {
        $this->actingAsUser();
        
        $team = $this->createTeam(['name' => 'Old Name']);

        $response = $this->putJson("/api/teams/{$team->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.description', 'Updated description');

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => 'Updated Name',
        ]);
    }

    /**
     * Test update team - not found.
     */
    public function test_update_team_returns_404_when_not_found(): void
    {
        $this->actingAsUser();

        $response = $this->putJson('/api/teams/99999', [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(404);
    }

    /**
     * Test can delete a team (soft delete).
     */
    public function test_can_delete_team(): void
    {
        $this->actingAsUser();
        
        $team = $this->createTeam();

        $response = $this->deleteJson("/api/teams/{$team->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Team deleted successfully');

        // Verify soft delete
        $this->assertSoftDeleted('teams', ['id' => $team->id]);
    }

    /**
     * Test delete team - not found.
     */
    public function test_delete_team_returns_404_when_not_found(): void
    {
        $this->actingAsUser();

        $response = $this->deleteJson('/api/teams/99999');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Team not found');
    }

    /**
     * Test can add a member to team.
     */
    public function test_can_add_member_to_team(): void
    {
        $this->actingAsUser();
        
        $team = $this->createTeam();
        $user = $this->actingAsUser();

        $response = $this->postJson("/api/teams/{$team->id}/members", [
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Member added successfully');

        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);
    }

    /**
     * Test add member validation - user_id required.
     */
    public function test_add_member_requires_user_id(): void
    {
        $this->actingAsUser();
        
        $team = $this->createTeam();

        $response = $this->postJson("/api/teams/{$team->id}/members", [
            'role' => 'member',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    /**
     * Test add member validation - user exists.
     */
    public function test_add_member_validates_user_exists(): void
    {
        $this->actingAsUser();
        
        $team = $this->createTeam();

        $response = $this->postJson("/api/teams/{$team->id}/members", [
            'user_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    /**
     * Test add member validation - role is valid.
     */
    public function test_add_member_validates_role(): void
    {
        $this->actingAsUser();
        
        $team = $this->createTeam();
        $user = $this->actingAsUser();

        $response = $this->postJson("/api/teams/{$team->id}/members", [
            'user_id' => $user->id,
            'role' => 'invalid_role',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    /**
     * Test add member - cannot add same user twice.
     */
    public function test_cannot_add_same_member_twice(): void
    {
        $this->actingAsUser();
        
        $team = $this->createTeam();
        $user = $this->actingAsUser();

        // Add first time
        $this->postJson("/api/teams/{$team->id}/members", [
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        // Try to add second time
        $response = $this->postJson("/api/teams/{$team->id}/members", [
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $response->assertStatus(500);
    }

    /**
     * Test can remove a member from team.
     */
    public function test_can_remove_member_from_team(): void
    {
        $this->actingAsUser();
        
        $team = $this->createTeam();
        $user = $this->actingAsUser();

        // Add member first
        $team->members()->attach($user->id, ['role' => 'member']);

        $response = $this->deleteJson("/api/teams/{$team->id}/members", [
            'user_id' => $user->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Member removed successfully');

        $this->assertDatabaseMissing('team_user', [
            'team_id' => $team->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Test remove member validation - user_id required.
     */
    public function test_remove_member_requires_user_id(): void
    {
        $this->actingAsUser();
        
        $team = $this->createTeam();

        $response = $this->deleteJson("/api/teams/{$team->id}/members");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    /**
     * Test add member with X-Tenant-ID header.
     */
    public function test_can_add_member_with_tenant_header(): void
    {
        $this->actingAsUser();
        
        $team = $this->createTeam();
        $user = $this->actingAsUser();

        $response = $this->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson("/api/teams/{$team->id}/members", [
                'user_id' => $user->id,
                'role' => 'member',
            ]);

        $response->assertStatus(200);
    }

    /**
     * Test team resource structure.
     */
    public function test_team_resource_has_correct_structure(): void
    {
        $this->actingAsUser();
        
        $team = $this->createTeam();

        $response = $this->getJson('/api/teams');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'member_count',
                        'members',
                        'created_at',
                    ],
                ],
            ]);
    }

    /**
     * Test that team belongs to correct tenant (check via database, not response).
     */
    public function test_team_belongs_to_correct_tenant(): void
    {
        $this->actingAsUser();
        
        $team = $this->createTeam();

        // Verify the team was created with correct tenant_id in database
        $this->assertEquals($this->tenant->id, $team->tenant_id);
    }
}

