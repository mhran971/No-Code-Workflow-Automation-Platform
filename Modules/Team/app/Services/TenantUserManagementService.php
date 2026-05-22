<?php

namespace Modules\Team\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\User;
use Modules\Team\Mail\NewUserCredentialsMail;
use Modules\Team\Repositories\AuditTrailRepository;
use Modules\Team\Repositories\TeamMembershipRepository;
use Modules\Team\Repositories\UserRepository;

class TenantUserManagementService
{
    public function __construct(
        protected UserRepository $userRepository,
        protected AuditTrailRepository $auditTrailRepository,
        protected TeamMembershipRepository $teamMembershipRepository,
        protected ActiveTaskReassignmentService $activeTaskReassignmentService,
        protected HistoricalDataGuardService $historicalDataGuardService
    ) {}

    /**
     * Create an employee user within the business owner's tenant.
     *
     * @throws AuthorizationException
     */
    public function createByBusinessOwner(User $businessOwner, array $validated): User
    {
        if ($businessOwner->role !== Role::BusinessOwner) {
            throw new AuthorizationException('Only business owners can create users.');
        }

//        $temporaryPassword = (string) Str::password(12, true, true, true, false);
            $special = '!@#$%^&*';

            $password =
                Str::upper(Str::random(1)) .
                Str::lower(Str::random(5)) .
                rand(0, 9) .
                $special[rand(0, strlen($special) - 1)] .
                Str::random(4);

            $temporaryPassword = str_shuffle($password);


        return DB::transaction(function () use ($businessOwner, $validated, $temporaryPassword) {
            $createdUser = $this->userRepository->create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'position' => $validated['position'] ?? null,
                'email' => $validated['email'],
                'password' => $temporaryPassword,
                'tenant_id' => $businessOwner->tenant_id,
                'role' => Role::Employee,
            ]);

            $this->auditTrailRepository->create([
                'tenant_id' => $businessOwner->tenant_id,
                'actor_user_id' => $businessOwner->id,
                'actor_name' => $businessOwner->name,
                'actor_email' => $businessOwner->email,
                'action' => 'user_created',
                'subject_type' => User::class,
                'subject_id' => $createdUser->id,
                'metadata' => [
                    'created_user_email' => $createdUser->email,
                    'created_user_role' => Role::Employee->value,
                    'created_user_position' => $createdUser->position,
                ],
            ]);

            $this->teamMembershipRepository->ensureMember(
                (int) $businessOwner->tenant_id,
                (int) $createdUser->id
            );

            DB::afterCommit(function () use ($createdUser, $temporaryPassword) {
                Mail::to($createdUser->email)->send(new NewUserCredentialsMail(
                    userName: $createdUser->name,
                    email: $createdUser->email,
                    temporaryPassword: $temporaryPassword
                ));
            });

            return $createdUser;
        });
    }

    /**
     * Disable a user after active tasks are reassigned.
     *
     * @throws AuthorizationException
     */
    public function disableByBusinessOwner(User $businessOwner, User $targetUser, ?int $reassignToUserId = null): array
    {
        $this->assertOwnerCanManageTarget($businessOwner, $targetUser);

        if (! $targetUser->is_active) {
            return [
                'reassigned_tasks_count' => 0,
                'user' => [
                    'id' => $targetUser->id,
                    'email' => $targetUser->email,
                    'is_active' => false,
                ],
            ];
        }

        $assignee = $this->resolveTaskAssignee($businessOwner, $targetUser, $reassignToUserId);

        return DB::transaction(function () use ($businessOwner, $targetUser, $assignee) {
            $reassignResult = $this->activeTaskReassignmentService->reassign(
                (int) $businessOwner->tenant_id,
                (int) $targetUser->id,
                (int) $assignee->id
            );

            if ($reassignResult['errors'] !== []) {
                throw new HttpResponseException(response()->json([
                    'message' => 'User disable failed because active tasks could not be fully reassigned.',
                    'errors' => $reassignResult['errors'],
                ], 422));
            }

            $this->userRepository->updateActive($targetUser, false);
            $this->teamMembershipRepository->disable((int) $businessOwner->tenant_id, (int) $targetUser->id);

            $this->auditTrailRepository->create([
                'tenant_id' => $businessOwner->tenant_id,
                'actor_user_id' => $businessOwner->id,
                'actor_name' => $businessOwner->name,
                'actor_email' => $businessOwner->email,
                'action' => 'user_disabled',
                'subject_type' => User::class,
                'subject_id' => $targetUser->id,
                'metadata' => [
                    'reassigned_to_user_id' => $assignee->id,
                    'reassigned_tasks_count' => $reassignResult['reassigned_total'],
                    'reassigned_tables' => $reassignResult['processed_tables'],
                ],
            ]);

            return [
                'reassigned_tasks_count' => $reassignResult['reassigned_total'],
                'user' => [
                    'id' => $targetUser->id,
                    'email' => $targetUser->email,
                    'is_active' => false,
                ],
            ];
        });
    }

    /**
     * Re-enable disabled user and restore membership status.
     *
     * @throws AuthorizationException
     */
    public function enableByBusinessOwner(User $businessOwner, User $targetUser): User
    {
        $this->assertOwnerCanManageTarget($businessOwner, $targetUser);

        return DB::transaction(function () use ($businessOwner, $targetUser) {
            $this->userRepository->updateActive($targetUser, true);
            $this->teamMembershipRepository->restore((int) $businessOwner->tenant_id, (int) $targetUser->id);

            $this->auditTrailRepository->create([
                'tenant_id' => $businessOwner->tenant_id,
                'actor_user_id' => $businessOwner->id,
                'actor_name' => $businessOwner->name,
                'actor_email' => $businessOwner->email,
                'action' => 'user_enabled',
                'subject_type' => User::class,
                'subject_id' => $targetUser->id,
                'metadata' => [
                    'restored_login_access' => true,
                ],
            ]);

            return $targetUser->refresh();
        });
    }

    /**
     * Delete user when no historical references exist.
     *
     * @throws AuthorizationException
     */
    public function deleteByBusinessOwner(User $businessOwner, User $targetUser): array
    {
        $this->assertOwnerCanManageTarget($businessOwner, $targetUser);

        $blockingRecords = $this->historicalDataGuardService->findBlockingRecords(
            (int) $businessOwner->tenant_id,
            (int) $targetUser->id
        );

        if ($blockingRecords !== []) {
            return [
                'deleted' => false,
                'blocking_records' => $blockingRecords,
            ];
        }

        DB::transaction(function () use ($businessOwner, $targetUser) {
            $this->teamMembershipRepository->deleteForUser((int) $businessOwner->tenant_id, (int) $targetUser->id);

            $this->auditTrailRepository->create([
                'tenant_id' => $businessOwner->tenant_id,
                'actor_user_id' => $businessOwner->id,
                'actor_name' => $businessOwner->name,
                'actor_email' => $businessOwner->email,
                'action' => 'user_deleted',
                'subject_type' => User::class,
                'subject_id' => $targetUser->id,
                'metadata' => [
                    'deleted_user_email' => $targetUser->email,
                ],
            ]);

            $this->userRepository->delete($targetUser);
        });

        return [
            'deleted' => true,
            'blocking_records' => [],
        ];
    }

    /**
     * Ensure owner can manage this target user.
     *
     * @throws AuthorizationException
     */
    protected function assertOwnerCanManageTarget(User $businessOwner, User $targetUser): void
    {
        if ($businessOwner->role !== Role::BusinessOwner) {
            throw new AuthorizationException('Only business owners can manage users.');
        }

        if ((int) $businessOwner->tenant_id !== (int) $targetUser->tenant_id) {
            throw new AuthorizationException('You can only manage users within your tenant.');
        }

        if ((int) $businessOwner->id === (int) $targetUser->id) {
            throw new AuthorizationException('You cannot perform this action on your own account.');
        }
    }

    /**
     * Resolve task assignee for automatic reassignment.
     *
     * @throws AuthorizationException
     */
    protected function resolveTaskAssignee(User $businessOwner, User $targetUser, ?int $reassignToUserId): User
    {
        if ($reassignToUserId === null) {
            return $businessOwner;
        }

        $assignee = $this->userRepository->findInTenant((int) $businessOwner->tenant_id, $reassignToUserId);

        if ($assignee === null) {
            throw new AuthorizationException('Reassignment user not found in your tenant.');
        }

        if ((int) $assignee->id === (int) $targetUser->id) {
            throw new AuthorizationException('Tasks cannot be reassigned to the same user being disabled.');
        }

        if (! $assignee->is_active) {
            throw new AuthorizationException('Tasks can only be reassigned to an active user.');
        }

        return $assignee;
    }
}
