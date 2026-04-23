<?php

namespace Modules\Team\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\User;
use Modules\Team\Http\Requests\CreateUserRequest;
use Modules\Team\Http\Requests\DisableUserRequest;
use Modules\Team\Services\TenantUserManagementService;

class MemberMangementController extends Controller
{
    public function __construct(
        protected TenantUserManagementService $tenantUserManagementService
    ) {}

    /**
     * List existing tenant users.
     * @throws AuthorizationException
     */
    public function index(): JsonResponse
    {
        $actor = $this->resolveTenantActor();
        $users = $this->serializeUsers($this->baseTenantUsersQuery($actor)->get());

        return response()->json([
            'data' => $users,
        ]);
    }

    /**
     * List eligible manager candidates in same tenant.
     *
     * @throws AuthorizationException
     */
    public function managerCandidates(): JsonResponse
    {
        $actor = $this->resolveTenantActor();

        $users = $this->serializeUsers(
            $this->baseTenantUsersQuery($actor)
                ->where('users.is_active', true)
                ->where('users.role', '!=', Role::BusinessOwner->value)
                ->whereNull('tm.team_id')
                ->get()
        );

        return response()->json([
            'data' => $users,
        ]);
    }

    /**
     * Create a new employee user in the current business owner's tenant.
     */
    public function __invoke(CreateUserRequest $request): JsonResponse
    {
        $createdUser = $this->tenantUserManagementService->createByBusinessOwner(
            auth('api')->user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'User account created successfully.',
            'user' => [
                'id' => $createdUser->id,
                'first_name' => $createdUser->first_name,
                'last_name' => $createdUser->last_name,
                'email' => $createdUser->email,
                'role' => $createdUser->role?->value,
            ],
        ], 201);
    }

    /**
     * Disable an active user after reassigning all active tasks.
     */
    public function disable(DisableUserRequest $request, User $user): JsonResponse
    {
        $result = $this->tenantUserManagementService->disableByBusinessOwner(
            auth('api')->user(),
            $user,
            $request->validated()['reassign_to_user_id'] ?? null
        );

        return response()->json([
            'message' => 'User disabled successfully.',
            'reassigned_tasks_count' => $result['reassigned_tasks_count'],
            'target_user' => $result['user'],
        ]);
    }

    /**
     * Re-enable a previously disabled user.
     */
    public function enable(User $user): JsonResponse
    {
        $enabledUser = $this->tenantUserManagementService->enableByBusinessOwner(
            auth('api')->user(),
            $user
        );

        return response()->json([
            'message' => 'User enabled successfully.',
            'user' => [
                'id' => $enabledUser->id,
                'email' => $enabledUser->email,
                'is_active' => (bool) $enabledUser->is_active,
            ],
        ]);
    }

    /**
     * Permanently delete a user when no historical data exists.
     */
    public function destroy(User $user): JsonResponse
    {
        $result = $this->tenantUserManagementService->deleteByBusinessOwner(
            auth('api')->user(),
            $user
        );

        if (! $result['deleted']) {
            return response()->json([
                'message' => 'User cannot be deleted because historical data exists.',
                'blocking_records' => $result['blocking_records'],
            ], 422);
        }

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }

    /**
     * Ensure current actor can view tenant users.
     *
     * @throws AuthorizationException
     */
    protected function resolveTenantActor(): User
    {
        /** @var User $actor */
        $actor = auth('api')->user();

        if (! in_array($actor->role, [Role::BusinessOwner, Role::Manager], true)) {
            throw new AuthorizationException('Only business owners or managers can view tenant users.');
        }

        return $actor;
    }

    /**
     * Base query for tenant users with membership context.
     */
    protected function baseTenantUsersQuery(User $actor)
    {
        return User::query()
            ->leftJoin('team_memberships as tm', function ($join) use ($actor): void {
                $join->on('tm.user_id', '=', 'users.id')
                    ->where('tm.tenant_id', '=', (int) $actor->tenant_id);
            })
            ->where('users.tenant_id', (int) $actor->tenant_id)
            ->select([
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.role',
                'users.is_active',
                'tm.team_id',
            ])
            ->orderBy('users.first_name')
            ->orderBy('users.last_name');
    }

    /**
     * Transform users list to API payload.
     */
    protected function serializeUsers($users)
    {
        return $users->map(static function ($user) {
            return [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => (bool) $user->is_active,
                'team_id' => $user->team_id,
            ];
        })->values();
    }
}
