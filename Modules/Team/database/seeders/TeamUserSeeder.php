<?php

namespace Modules\Team\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\Models\User;
use Modules\Team\app\Models\Team;

class TeamUserSeeder extends Seeder
{
    public function run(): void
    {
        $teams = Team::all();
        $teamRoles = ['owner', 'admin', 'member'];

        foreach ($teams as $team) {
            $tenantUsers = User::where('tenant_id', $team->tenant_id)->get();

            if ($tenantUsers->isEmpty()) {
                continue;
            }

            $numMembers = rand(3, 8);
            $selectedMembers = $tenantUsers->random(min($numMembers, $tenantUsers->count()));

            foreach ($selectedMembers as $index => $member) {
                $team->members()->attach($member->id, [
                    'role' => $index === 0 ? 'owner' : fake()->randomElement($teamRoles),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Seeded team_user pivot table with members.');
    }
}

