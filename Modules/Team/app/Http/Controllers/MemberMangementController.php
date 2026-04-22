<?php

namespace Modules\Team\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
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
}
