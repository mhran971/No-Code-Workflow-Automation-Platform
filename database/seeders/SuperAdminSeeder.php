<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\User;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'superadmin@platform.test'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'name' => 'Super Admin',
                'password' => Hash::make('Password123!'),
                'role' => Role::SuperAdmin,
                'is_active' => true,
                'tenant_id' => null,
            ]
        );
    }
}
