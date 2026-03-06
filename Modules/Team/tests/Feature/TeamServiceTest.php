<?php

namespace Modules\Team\Tests\Feature;

use Modules\Team\Tests\TestCase;
use Modules\Team\Services\TeamService;
use Modules\Team\app\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Exception;

class TeamServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var TeamService
     */
    protected $service;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = app(TeamService::class);
    }

    /**
     * Test can list teams for tenant.
     */
    public function test_can_list_teams_for_tenant(): void
    {
        $this->actingAsUser();
        
        $this->createTeams(3);

        $teams = $this->service->listTeams($this->tenant->id);

        $this->assertCount(3, $teams);
    }

    /**
     * Test can search teams by name.
     */
    public function test_can_search_teams_by_name(): void
    {
        $this->actingAsUser();
        
        $this->createTeam(['name' => 'Marketing Team']);
        $this->createTeam(['name' => 'Sales Team']);
        $this->createTeam(['name' => 'HR Team']);

        $teams = $this->service->listTeams($this->tenant->id, 'market');

        $this->assertCount(1, $teams);
        $this->assertEquals('Marketing Team', $teams->first()->name);
    }

    /**
     * Test can create a team.
     */
    public function test_can_create_team(): void
    {
        $this->actingAsUser();
        
        $team = $this->service->createTeam([
            'tenant_id' => $this->tenant->id,
            'name' => 'New Team',
            'description' => 'Test description',
        ]);

        $this->assertInstanceOf(Team::class, $team);
        $this->assertEquals('New Team', $team->name);
        $this->assertDatabaseHas('teams', ['name' => 'New Team']);
    }

    /**
     * Test cannot create duplicate team name in tenant.
     */
    public function test_cannot_create_duplicate_team_name_in_tenant(): void
    {
        $this->actingAsUser();
        
        $this->createTeam(['name' => 'Existing Team']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Team name already exists within this tenant.');

        $this->service->createTeam([
            'tenant_id' => $this->tenant->id,
            'name' => 'Existing Team',
        ]);
    }

    /**
     * Test can create team with same name in different tenant.
     */
    public function test_can_create_team_with_same_name_in_different_tenant(): void
    {
        $this->actingAsUser();
        
        $tenant2 = $this->createTenant();
        
        $this->createTeam(['name' => 'Same Name', 'tenant_id' => $tenant2->id]);

        $team = $this->service->createTeam([
            'tenant_id' => $this->tenant->id,
            'name' => 'Same Name',
        ]);

        $this->assertEquals('Same Name', $team->name);
    }

    /**
     * Test can update a team.
     */
    public function test_can_update_team(): void
    {
        $this->actingAsUser();
        
        $team = $this->createTeam(['name' => 'Old Name']);

        $updatedTeam = $this->service->updateTeam($team, [
            'name' => 'New Name',
            'description' => 'New description',
        ]);

        $this->assertEquals('New Name', $updatedTeam->name);
        $this->assertEquals('New description', $updatedTeam->description);
    }

    /**
     * Test can delete a team.
     */
    public function test_can_delete_team(): void
    {
        $this->actingAsUser();
        
        $team = $this->createTeam();

        $result = $this->service->deleteTeam($team);

        $this->assertTrue($result);
        $this->assertSoftDeleted('teams', ['id' => $team->id]);
    }

    /**
     * Test can add member to team.
     */
    public function test_can_add_member_to_team(): void
    {
        $this->actingAsUser();
        
        $team = $this->createTeam();
        $user = $this->actingAsUser();

        $this->service->addMemberToTeam($team, $user->id, 'admin');

        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);
    }

    /**
     * Test cannot add same member twice.
     */
    public function test_cannot_add_same_member_twice(): void
    {
        $this->actingAsUser();
        
        $team = $this->createTeam();
        $user = $this->actingAsUser();

        // Add first time
        $this->service->addMemberToTeam($team, $user->id, 'member');

        // Try to add second time
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User is already a member of this team.');

        $this->service->addMemberToTeam($team, $user->id, 'admin');
    }

    /**
     * Test can remove member from team.
     */
    public function test_can_remove_member_from_team(): void
    {
        $this->actingAsUser();
        
        $team = $this->createTeam();
        $user = $this->actingAsUser();

        // Add member first
        $team->members()->attach($user->id, ['role' => 'member']);

        $this->service->removeMemberFromTeam($team, $user->id);

        $this->assertDatabaseMissing('team_user', [
            'team_id' => $team->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Test removing non-existent member does not throw error.
     */
    public function test_removing_non_existent_member_does_not_throw(): void
    {
        $this->actingAsUser();
        
        $team = $this->createTeam();

        // This should not throw an exception
        $this->service->removeMemberFromTeam($team, 99999);

        $this->assertTrue(true);
    }
}

