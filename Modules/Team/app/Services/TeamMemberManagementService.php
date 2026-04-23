<?php

namespace Modules\Team\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\User;
use Modules\Team\Models\Team;
use Modules\Team\Repositories\AuditTrailRepository;
use Modules\Team\Repositories\TeamMembershipRepository;
use Modules\Team\Repositories\UserRepository;

class TeamMemberManagementService
{
    public function __construct(
        protected UserRepository $userRepository,
        protected TeamMembershipRepository $teamMembershipRepository,
        protected AuditTrailRepository $auditTrailRepository
    ) {}

    /**
     * Add tenant user to team.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function addMember(User $actor, Team $team, int $userId): User
    {
        $this->assertCanManageTeamMembers($actor, $team);

        $targetUser = $this->resolveTenantUser($actor, $userId);
        $membership = $this->teamMembershipRepository->findForUser((int) $actor->tenant_id, (int) $targetUser->id);

        if (
            $membership !== null
            && $membership->team_id !== null
            && (int) $membership->team_id !== (int) $team->id
        ) {
            throw ValidationException::withMessages([
                'user_id' => 'This user already belongs to another team.',
            ]);
        }

        DB::transaction(function () use ($actor, $team, $targetUser) {
            $this->teamMembershipRepository->assignToTeam(
                (int) $actor->tenant_id,
                (int) $targetUser->id,
                (int) $team->id
            );

            $this->auditTrailRepository->create([
                'tenant_id' => $actor->tenant_id,
                'actor_user_id' => $actor->id,
                'actor_name' => $actor->name,
                'actor_email' => $actor->email,
                'action' => 'team_member_added',
                'subject_type' => Team::class,
                'subject_id' => $team->id,
                'metadata' => [
                    'team_name' => $team->name,
                    'member_user_id' => $targetUser->id,
                    'member_email' => $targetUser->email,
                ],
            ]);
        });

        return $targetUser->refresh();
    }

    /**
     * Remove tenant user from team.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function removeMember(User $actor, Team $team, User $targetUser): void
    {
        $this->assertCanManageTeamMembers($actor, $team);

        if ((int) $actor->tenant_id !== (int) $targetUser->tenant_id) {
            throw new AuthorizationException('You can only manage users within your tenant.');
        }

        if ((int) $team->manager_id === (int) $targetUser->id) {
            throw ValidationException::withMessages([
                'user_id' => 'Current team manager cannot be removed from team members.',
            ]);
        }

        $membership = $this->teamMembershipRepository->findForUser((int) $actor->tenant_id, (int) $targetUser->id);

        if ($membership === null || (int) $membership->team_id !== (int) $team->id) {
            throw ValidationException::withMessages([
                'user_id' => 'This user is not a member of the selected team.',
            ]);
        }

        DB::transaction(function () use ($actor, $team, $targetUser) {
            $this->teamMembershipRepository->removeFromTeam(
                (int) $actor->tenant_id,
                (int) $targetUser->id,
                (int) $team->id
            );

            $this->auditTrailRepository->create([
                'tenant_id' => $actor->tenant_id,
                'actor_user_id' => $actor->id,
                'actor_name' => $actor->name,
                'actor_email' => $actor->email,
                'action' => 'team_member_removed',
                'subject_type' => Team::class,
                'subject_id' => $team->id,
                'metadata' => [
                    'team_name' => $team->name,
                    'member_user_id' => $targetUser->id,
                    'member_email' => $targetUser->email,
                ],
            ]);
        });
    }

    /**
     * Ensure actor has permission to manage members of this team.
     *
     * @throws AuthorizationException
     */
    protected function assertCanManageTeamMembers(User $actor, Team $team): void
    {
        if ((int) $actor->tenant_id !== (int) $team->tenant_id) {
            throw new AuthorizationException('You can only manage teams within your tenant.');
        }

        if ($actor->role === Role::BusinessOwner) {
            return;
        }

        if ($actor->role === Role::Manager && (int) $team->manager_id === (int) $actor->id) {
            return;
        }

        throw new AuthorizationException('You are not allowed to manage this team.');
    }

    /**
     * Find target user in actor tenant.
     *
     * @throws ValidationException
     */
    protected function resolveTenantUser(User $actor, int $userId): User
    {
        $targetUser = $this->userRepository->findInTenant((int) $actor->tenant_id, $userId);

        if ($targetUser === null) {
            throw ValidationException::withMessages([
                'user_id' => 'Selected user was not found in your tenant.',
            ]);
        }

        return $targetUser;
    }
}

