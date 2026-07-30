<?php

namespace Modules\Workflows\Services;

use App\Services\BaseService;
use Illuminate\Auth\Access\AuthorizationException;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\User;
use Modules\Team\Models\Team;
use Modules\Team\Models\TeamMembership;
use Modules\Workflows\Models\Workflow;

class WorkflowAuthorizationService extends BaseService
{
    public function assertCanView(User $actor, Workflow $workflow): void
    {
        if (! $this->canView($actor, $workflow)) {
            throw new AuthorizationException('You are not allowed to view this workflow.');
        }
    }

    public function canView(User $actor, Workflow $workflow): bool
    {
        if ((int) $actor->tenant_id !== (int) $workflow->tenant_id) {
            return false;
        }

        if ($actor->role === Role::BusinessOwner) {
            return true;
        }

        if ($actor->role === Role::Manager) {
            return $this->canManage($actor, $workflow);
        }

        if ($actor->role === Role::Employee) {
            return TeamMembership::query()
                ->where('tenant_id', (int) $actor->tenant_id)
                ->where('team_id', (int) $workflow->team_id)
                ->where('user_id', (int) $actor->id)
                ->where('status', 'active')
                ->exists();
        }

        return false;
    }

    public function assertCanManage(User $actor, Workflow $workflow): void
    {
        if (! $this->canManage($actor, $workflow)) {
            throw new AuthorizationException('You are not allowed to manage this workflow.');
        }
    }

    public function canManage(User $actor, Workflow $workflow): bool
    {
        if ((int) $actor->tenant_id !== (int) $workflow->tenant_id) {
            return false;
        }

        if ($actor->role === Role::BusinessOwner) {
            return true;
        }

        if ($actor->role !== Role::Manager) {
            return false;
        }

        $team = Team::query()
            ->where('tenant_id', (int) $actor->tenant_id)
            ->where('manager_id', (int) $actor->id)
            ->first();

        return $team !== null && (int) $workflow->team_id === (int) $team->id;
    }

    public function assertBusinessOwner(User $actor): void
    {
        if ($actor->role !== Role::BusinessOwner) {
            throw new AuthorizationException('Only business owners can perform this action.');
        }
    }

    public function assertSameTenant(User $actor, Workflow $workflow): void
    {
        if ((int) $actor->tenant_id !== (int) $workflow->tenant_id) {
            throw new AuthorizationException('You can only manage workflows within your tenant.');
        }
    }
}
