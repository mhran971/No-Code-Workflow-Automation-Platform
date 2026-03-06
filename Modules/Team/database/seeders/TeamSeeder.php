<?php

namespace Modules\Team\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\Models\Tenant;
use Modules\Team\app\Models\Team;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $numTeams = rand(5, 10);

            for ($i = 0; $i < $numTeams; $i++) {
                Team::create([
                    'tenant_id' => $tenant->id,
                    'name' => fake()->randomElement([
                        'Development',
                        'Design',
                        'Marketing',
                        'Sales',
                        'Support',
                        'Operations',
                        'Finance',
                        'HR',
                        'QA',
                        'Security',
                        'DevOps',
                        'Product',
                    ]) . ' ' . fake()->randomElement(['Team', 'Group', 'Unit', 'Division']),
                    'description' => fake()->sentence(),
                ]);
            }
        }

        $totalTeams = Team::count();
        $this->command->info("Seeded {$totalTeams} teams.");
    }
}

