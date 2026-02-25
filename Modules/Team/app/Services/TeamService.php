<?php

namespace  Modules\Teams\Services;

use App\Modules\Teams\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Team\app\Repositories\TeamRepository;
use Exception;

class TeamService
{
    protected $repository;

    public function __construct(TeamRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get filtered list of teams.
     * Input JSON: { "tenant_id": 1, "search": "sales" }
     *
     * @param int $tenantId
     * @param string|null $search
     * @return Collection
     */
    public function listTeams(int $tenantId, ?string $search = null)
    {
        // Logic Improvement: Could add caching here
        return $this->repository->getAllByTenant($tenantId, $search);
    }

    /**
     * Create a team with validation logic.
     * Input JSON: { "tenant_id": 1, "name": "HR", "description": "Human Resources" }
     *
     * @param array $data
     * @return Team
     * @throws Exception
     */
    public function createTeam(array $data): Team
    {
        // Business Rule: Ensure team name is unique within tenant
        $exists = Team::where('tenant_id', $data['tenant_id'])
            ->where('name', $data['name'])
            ->first();

        if ($exists) {
            throw new Exception('Team name already exists within this tenant.');
        }

        return DB::transaction(function () use ($data) {
            $team = $this->repository->create($data);

            // Optional: Auto-assign creator as owner
            // $this->repository->addMember($team, $data['creator_id'], 'owner');

            return $team;
        });
    }

    /**
     * Update team details.
     * Input JSON: { "team": TeamObject, "data": { "name": "Updated" } }
     *
     * @param Team $team
     * @param array $data
     * @return Team
     */
    public function updateTeam(Team $team, array $data): Team
    {
        $this->repository->update($team, $data);
        return $team->fresh();
    }

    /**
     * Delete a team safely.
     * Input JSON: { "team": TeamObject }
     *
     * @param Team $team
     * @return bool
     * @throws Exception
     */
    public function deleteTeam(Team $team): bool
    {
        // Business Rule: Prevent deletion if team is critical (Example logic)
        // if ($team->name === 'Default') throw new Exception('Cannot delete default team');

        return $this->repository->delete($team);
    }

    /**
     * Add member to team.
     * Input JSON: { "team": TeamObject, "user_id": 5, "role": "member" }
     *
     * @param Team $team
     * @param int $userId
     * @param string $role
     * @return void
     */
    public function addMemberToTeam(Team $team, int $userId, string $role = 'member'): void
    {
        // Logic: Check if user already exists
        if ($team->members()->where('users.id', $userId)->exists()) {
            throw new Exception('User is already a member of this team.');
        }

        $this->repository->addMember($team, $userId, $role);
    }

    /**
     * Remove member from team.
     * Input JSON: { "team": TeamObject, "user_id": 5 }
     *
     * @param Team $team
     * @param int $userId
     * @return void
     */
    public function removeMemberFromTeam(Team $team, int $userId): void
    {
        $this->repository->removeMember($team, $userId);
    }
}
