<?php

namespace Modules\Team\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\Database\Factories\TenantFactory;

class TeamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'name' => fake()->company() . ' Team',
            'description' => fake()->sentence(),
        ];
    }
}
