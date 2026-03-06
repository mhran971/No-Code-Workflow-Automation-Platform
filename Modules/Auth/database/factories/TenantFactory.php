<?php

namespace Modules\Auth\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\Enums\BusinessType;

class TenantFactory extends Factory
{
    public function definition(): array
    {
        $businessTypes = array_column(BusinessType::cases(), 'value');

        return [
            'business_name' => fake()->company(),
            'business_type' => fake()->randomElement($businessTypes),
        ];
    }
}
