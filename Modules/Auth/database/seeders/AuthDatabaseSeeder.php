<?php

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;

class AuthDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $businessTypes = [
            'saas',
            'software_house',
            'digital_agency',
            'tech_startup',
            'it_consulting_firm',
            'cloud_service_provider',
            'cybersecurity_company',
            'game_development_studio',
            'iot_solutions_provider',
            'devops_services_company',
            'ar_vr_development_company',
            'robotics_and_automation_firm',
        ];

        $tenants = [];
        for ($i = 0; $i < 15; $i++) {
            $tenants[] = Tenant::create([
                'business_name' => fake()->company() . ' ' . fake()->randomElement(['Inc.', 'LLC', 'Corp', 'Solutions', 'Systems']),
                'business_type' => fake()->randomElement($businessTypes),
            ]);
        }

        $firstNames = ['John', 'Jane', 'Michael', 'Sarah', 'David', 'Emily', 'Robert', 'Lisa', 'William', 'Jennifer', 'James', 'Amanda', 'Christopher', 'Ashley', 'Daniel', 'Jessica', 'Matthew', 'Nicole', 'Andrew', 'Stephanie', 'Joshua', 'Heather', 'Brian', 'Michelle', 'Kevin', 'Laura', 'Ryan', 'Megan', 'Brandon', 'Melissa'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee', 'Perez', 'Thompson', 'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson'];

        $roles = [Role::BusinessOwner, Role::Admin];

        for ($i = 0; $i < 30; $i++) {
            $firstName = $firstNames[$i % count($firstNames)];
            $lastName = $lastNames[$i % count($lastNames)];
            $tenant = fake()->randomElement($tenants);

            User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'name' => $firstName . ' ' . $lastName,
                'email' => strtolower($firstName) . '.' . strtolower($lastName) . ($i > 0 ? $i : '') . '@' . strtolower(str_replace(' ', '', $tenant->business_name)) . '.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'role' => $i < 15 ? Role::BusinessOwner : fake()->randomElement($roles),
            ]);
        }

        $this->command->info('Seeded 15 tenants and 30 users with authentication data.');
    }
}
