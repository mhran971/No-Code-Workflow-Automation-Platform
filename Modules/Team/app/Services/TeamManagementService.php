<?php

namespace Modules\Team\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\User;
use Modules\Team\Models\Team;
use Modules\Team\Repositories\AuditTrailRepository;
use Modules\Team\Repositories\TeamMembershipRepository;
use Modules\Team\Repositories\TeamRepository;
use Modules\Team\Repositories\UserRepository;

class TeamManagementService
{
    public function __construct(
        protected TeamRepository $teamRepository,
        protected UserRepository $userRepository,
        protected TeamMembershipRepository $teamMembershipRepository,
        protected AuditTrailRepository $auditTrailRepository
    ) {}

    /**
     * List teams visible to actor.
     *
     * @throws AuthorizationException
     */
    public function listVisibleTeams(User $actor): Collection
    {
        $relations = [
            'manager:id,first_name,last_name,name,email,role',
            'members:id,first_name,last_name,name,email,role',
        ];

        $query = Team::query()
            ->where('tenant_id', (int) $actor->tenant_id)
            ->with($relations)
            ->orderBy('name');

        if ($actor->role === Role::BusinessOwner) {
            return $query->get();
        }

        if ($actor->role === Role::Manager) {
            return $query->where('manager_id', (int) $actor->id)->get();
        }

        throw new AuthorizationException('Only business owners or managers can view teams.');
    }

    /**
     * Return team when visible to actor.
     *
     * @throws AuthorizationException
     */
    public function getVisibleTeam(User $actor, Team $team): Team
    {
        $this->assertCanAccessTeam($actor, $team);

        return $team->load([
            'manager:id,first_name,last_name,name,email,role',
            'members:id,first_name,last_name,name,email,role',
        ]);
    }

    /**
     * Create a new team in tenant and assign manager.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function createByBusinessOwner(User $businessOwner, array $validated): Team
    {
        $this->assertBusinessOwner($businessOwner);

        $manager = $this->resolveEligibleManager($businessOwner, (int) $validated['manager_id']);
        $teamName = trim((string) $validated['name']);

        $this->assertUserCanJoinTeam((int) $businessOwner->tenant_id, (int) $manager->id, null);

        return DB::transaction(function () use ($businessOwner, $manager, $teamName) {
            $team = $this->teamRepository->create([
                'tenant_id' => (int) $businessOwner->tenant_id,
                'name' => $teamName,
                'manager_id' => (int) $manager->id,
            ]);

            $this->userRepository->updateRole($manager, Role::Manager);
            $this->teamMembershipRepository->assignToTeam(
                (int) $businessOwner->tenant_id,
                (int) $manager->id,
                (int) $team->id
            );

            $this->auditTrailRepository->create([
                'tenant_id' => $businessOwner->tenant_id,
                'actor_user_id' => $businessOwner->id,
                'actor_name' => $businessOwner->name,
                'actor_email' => $businessOwner->email,
                'action' => 'team_created',
                'subject_type' => Team::class,
                'subject_id' => $team->id,
                'metadata' => [
                    'team_name' => $team->name,
                    'manager_id' => $manager->id,
                    'manager_email' => $manager->email,
                ],
            ]);

            return $team->load([
                'manager:id,first_name,last_name,name,email,role',
                'members:id,first_name,last_name,name,email,role',
            ]);
        });
    }

    /**
     * Replace team manager and synchronize roles.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function updateManagerByBusinessOwner(User $businessOwner, Team $team, int $newManagerId): Team
    {
        $this->assertBusinessOwner($businessOwner);
        $this->assertTeamInTenant($businessOwner, $team);

        $newManager = $this->resolveEligibleManager($businessOwner, $newManagerId);
        $this->assertUserCanJoinTeam((int) $businessOwner->tenant_id, (int) $newManager->id, (int) $team->id);

        $previousManager = $this->userRepository->findInTenant(
            (int) $businessOwner->tenant_id,
            (int) $team->manager_id
        );

        return DB::transaction(function () use ($businessOwner, $team, $newManager, $previousManager) {
            $previousManagerId = (int) $team->manager_id;

            $team->manager_id = (int) $newManager->id;
            $this->teamRepository->save($team);

            $this->userRepository->updateRole($newManager, Role::Manager);
            $this->teamMembershipRepository->assignToTeam(
                (int) $businessOwner->tenant_id,
                (int) $newManager->id,
                (int) $team->id
            );

            if (
                $previousManager !== null
                && (int) $previousManager->id !== (int) $newManager->id
                && $previousManager->role !== Role::BusinessOwner
            ) {
                $this->userRepository->updateRole($previousManager, Role::Employee);
            }

            $this->auditTrailRepository->create([
                'tenant_id' => $businessOwner->tenant_id,
                'actor_user_id' => $businessOwner->id,
                'actor_name' => $businessOwner->name,
                'actor_email' => $businessOwner->email,
                'action' => 'team_manager_updated',
                'subject_type' => Team::class,
                'subject_id' => $team->id,
                'metadata' => [
                    'team_name' => $team->name,
                    'previous_manager_id' => $previousManagerId,
                    'new_manager_id' => $newManager->id,
                    'new_manager_email' => $newManager->email,
                ],
            ]);

            return $team->refresh()->load([
                'manager:id,first_name,last_name,name,email,role',
                'members:id,first_name,last_name,name,email,role',
            ]);
        });
    }

    /**
     * Ensure actor can access this team.
     *
     * @throws AuthorizationException
     */
    protected function assertCanAccessTeam(User $actor, Team $team): void
    {
        $this->assertTeamInTenant($actor, $team);

        if ($actor->role === Role::BusinessOwner) {
            return;
        }

        if ($actor->role === Role::Manager && (int) $team->manager_id === (int) $actor->id) {
            return;
        }

        throw new AuthorizationException('You are not allowed to access this team.');
    }

    /**
     * Ensure the actor is a business owner.
     *
     * @throws AuthorizationException
     */
    protected function assertBusinessOwner(User $user): void
    {
        if ($user->role !== Role::BusinessOwner) {
            throw new AuthorizationException('Only business owners can perform this action.');
        }
    }

    /**
     * Ensure the team belongs to actor tenant.
     *
     * @throws AuthorizationException
     */
    protected function assertTeamInTenant(User $actor, Team $team): void
    {
        if ((int) $actor->tenant_id !== (int) $team->tenant_id) {
            throw new AuthorizationException('You can only manage teams within your tenant.');
        }
    }

    /**
     * Resolve and validate manager candidate.
     *
     * @throws ValidationException
     */
    protected function resolveEligibleManager(User $businessOwner, int $managerId): User
    {
        $candidate = $this->userRepository->findInTenant((int) $businessOwner->tenant_id, $managerId);

        if ($candidate === null) {
            throw ValidationException::withMessages([
                'manager_id' => 'Selected manager was not found in your tenant.',
            ]);
        }

        if ($candidate->role === Role::BusinessOwner) {
            throw ValidationException::withMessages([
                'manager_id' => 'Business owner cannot be assigned as team manager.',
            ]);
        }

        if (! $candidate->is_active) {
            throw ValidationException::withMessages([
                'manager_id' => 'Only active users can be assigned as manager.',
            ]);
        }

        return $candidate;
    }

    /**
     * Ensure user is not already assigned to another team.
     *
     * @throws ValidationException
     */
    protected function assertUserCanJoinTeam(int $tenantId, int $userId, ?int $targetTeamId): void
    {
        $membership = $this->teamMembershipRepository->findForUser($tenantId, $userId);

        if (
            $membership !== null
            && $membership->team_id !== null
            && (int) $membership->team_id !== (int) $targetTeamId
        ) {
            throw ValidationException::withMessages([
                'manager_id' => 'The selected user already belongs to another team.',
            ]);
        }
    }
}
