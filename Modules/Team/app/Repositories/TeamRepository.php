<?php

namespace Modules\Team\app\Repositories;

use App\Modules\Teams\Models\Team;
use Illuminate\Database\Eloquent\Collection;

class TeamRepository
{
    protected $model;

    public function __construct(Team $model)
    {
        $this->model = $model;
    }

    /**
     * Retrieve all teams for a tenant with members loaded.
     * Input JSON: { "tenant_id": 1, "search": "dev" }
     *
     * @param int $tenantId
     * @param string|null $search
     * @return Collection
     */
    public function getAllByTenant(int $tenantId, ?string $search = null): Collection
    {
        return $this->model
            ::forTenant($tenantId)
            ->search($search)
            ->with(['members']) // Eager loading to prevent N+1
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Find a team by ID and tenant.
     * Input JSON: { "tenant_id": 1, "team_id": 5 }
     *
     * @param int $tenantId
     * @param int $teamId
     * @return Team|null
     */
    public function find(int $tenantId, int $teamId): ?Team
    {
        return $this->model
            ::forTenant($tenantId)
            ->with('members')
            ->findOrFail($teamId);
    }

    /**
     * Create a new team.
     * Input JSON: { "tenant_id": 1, "name": "Marketing", "description": "..." }
     *
     * @param array $data
     * @return Team
     */
    public function create(array $data): Team
    {
        return $this->model::create($data);
    }

    /**
     * Update an existing team.
     * Input JSON: { "team_id": 5, "name": "New Name", "description": "..." }
     *
     * @param Team $team
     * @param array $data
     * @return bool
     */
    public function update(Team $team, array $data): bool
    {
        return $team->update($data);
    }

    /**
     * Delete a team (Soft Delete).
     * Input JSON: { "team_id": 5 }
     *
     * @param Team $team
     * @return bool|null
     */
    public function delete(Team $team): ?bool
    {
        return $team->delete();
    }

    /**
     * Attach a member to a team.
     * Input JSON: { "team_id": 5, "user_id": 10, "role": "admin" }
     *
     * @param Team $team
     * @param int $userId
     * @param string $role
     * @return void
     */
    public function addMember(Team $team, int $userId, string $role = 'member'): void
    {
        $team->members()->attach($userId, ['role' => $role]);
    }

    /**
     * Remove a member from a team.
     * Input JSON: { "team_id": 5, "user_id": 10 }
     *
     * @param Team $team
     * @param int $userId
     * @return void
     */
    public function removeMember(Team $team, int $userId): void
    {
        $team->members()->detach($userId);
    }
}

