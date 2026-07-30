<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;
use Modules\Team\Models\Team;
use Tests\TestCase;

class BusinessOwnerCreateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_owner_can_create_workflow(): void
    {
        $tenant = Tenant::query()->create([
            'business_name' => 'Test Company',
            'business_type' => BusinessType::SaaS->value,
            'industry' => 'IT',
            'subscription_plan' => 'pro',
            'trial_ends_at' => now()->addDays(14),
            'database_connection' => 'sqlite',
        ]);

        $owner = User::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Owner',
            'last_name' => 'User',
            'name' => 'Owner User',
            'position' => 'Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('password'),
            'role' => Role::BusinessOwner->value,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $manager = User::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Manager',
            'last_name' => 'User',
            'name' => 'Manager User',
            'position' => 'Manager',
            'email' => 'manager@example.com',
            'password' => Hash::make('password'),
            'role' => Role::Manager->value,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $team = Team::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Team',
            'manager_id' => $manager->id,
        ]);

        $response = $this->actingAs($owner, 'api')->postJson('/api/v1/workflows', [
            'method' => 'blank',
            'name' => 'Owner Created Workflow',
            'description' => 'Workflow created by business owner',
            'team_id' => $team->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('workflow.name', 'Owner Created Workflow');

        $this->assertDatabaseHas('workflows', [
            'tenant_id' => $tenant->id,
            'name' => 'Owner Created Workflow',
            'team_id' => $team->id,
            'created_by_id' => $owner->id,
        ]);
    }
}
