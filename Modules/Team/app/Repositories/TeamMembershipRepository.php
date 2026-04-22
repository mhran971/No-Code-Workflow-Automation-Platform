<?php

namespace Modules\Team\Repositories;

use Modules\Team\Models\TeamMembership;

class TeamMembershipRepository
{
    /**
     * Ensure user has a membership row.
     */
    public function ensureMember(int $tenantId, int $userId): TeamMembership
    {
        return TeamMembership::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $userId],
            ['status' => 'active']
        );
    }

    /**
     * Mark membership as disabled while preserving previous status.
     */
    public function disable(int $tenantId, int $userId): void
    {
        $membership = $this->ensureMember($tenantId, $userId);

        $membership->forceFill([
            'status_before_disable' => $membership->status,
            'status' => 'disabled',
        ])->save();
    }

    /**
     * Restore membership status when user is re-enabled.
     */
    public function restore(int $tenantId, int $userId): void
    {
        $membership = $this->ensureMember($tenantId, $userId);

        $restored = $membership->status_before_disable ?: 'active';

        $membership->forceFill([
            'status' => $restored,
            'status_before_disable' => null,
        ])->save();
    }

    /**
     * Remove membership row for permanently deleted user.
     */
    public function deleteForUser(int $tenantId, int $userId): void
    {
        TeamMembership::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->delete();
    }
}
