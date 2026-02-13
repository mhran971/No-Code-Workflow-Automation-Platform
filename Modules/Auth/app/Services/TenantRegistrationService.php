<?php

namespace Modules\Auth\Services;

use Illuminate\Support\Facades\DB;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\User;
use Modules\Auth\Repositories\TenantRepository;
use Modules\Auth\Repositories\UserRepository;

class TenantRegistrationService
{
    public function __construct(
        protected TenantRepository $tenantRepository,
        protected UserRepository $userRepository
    ) {}

    /**
     * Register a new tenant and user with business owner role.
     */
    public function register(array $validated): User
    {
        return DB::transaction(function () use ($validated) {
            $tenant = $this->tenantRepository->create([
                'business_name' => "{$validated['first_name']}'s business",
                'business_type' => $validated['business_type'],
            ]);

            $user = $this->userRepository->create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'tenant_id' => $tenant->id,
                'role' => Role::BusinessOwner,
            ]);

            return $user->load('tenant');
        });
    }
}
