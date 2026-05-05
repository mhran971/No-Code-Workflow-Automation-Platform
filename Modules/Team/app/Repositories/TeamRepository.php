<?php

namespace Modules\Team\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Team\Models\Team;

class TeamRepository
{
    /**
     * Create a new team record.
     */
    public function create(array $data): Team
    {
        return Team::create($data);
    }

    /**
     * Find a tenant team by id.
     */
    public function findInTenant(int $tenantId, int $teamId): ?Team
    {
        return Team::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $teamId)
            ->first();
    }

    /**
     * Return all teams in tenant.
     */
    public function listInTenant(int $tenantId): Collection
    {
        return Team::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Save team changes.
     */
    public function save(Team $team): Team
    {
        $team->save();

        return $team;
    }
}
