<?php

namespace Modules\Team\Repositories;

use Illuminate\Support\Facades\Hash;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\User;

class UserRepository
{
    /**
     * Create a new user.
     */
    public function create(array $data): User
    {
        $data['is_active'] = $data['is_active'] ?? true;
        $data['password'] = Hash::make($data['password']);
        $data['name'] = trim($data['first_name'].' '.$data['last_name']);

        return User::create($data);
    }

    /**
     * Find a tenant user by id.
     */
    public function findInTenant(int $tenantId, int $userId): ?User
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $userId)
            ->first();
    }

    /**
     * Update active state.
     */
    public function updateActive(User $user, bool $isActive): void
    {
        $user->forceFill(['is_active' => $isActive])->save();
    }

    /**
     * Update user role.
     */
    public function updateRole(User $user, Role $role): void
    {
        $user->forceFill(['role' => $role])->save();
    }

    /**
     * Permanently delete a user.
     */
    public function delete(User $user): void
    {
        $user->delete();
    }
}
