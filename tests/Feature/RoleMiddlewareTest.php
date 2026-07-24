<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\User;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_middleware_allows_matching_role_and_blocks_other_roles(): void
    {
        Route::middleware(['auth:api', 'role:manager'])
            ->get('/api/test-role-middleware', fn () => response()->json(['allowed' => true]));

$manager = User::query()->create([
            'first_name' => 'Manager',
            'last_name' => 'User',
            'name' => 'Manager User',
            'email' => 'manager_'.uniqid().'@example.test',
            'password' => \Illuminate\Support\Facades\Hash::make('Pass1234!'),
            'role' => Role::Manager,
        ]);

        $this->actingAs($manager, 'api')
            ->getJson('/api/test-role-middleware')
            ->assertOk()
            ->assertJson(['allowed' => true]);

$employee = User::query()->create([
            'first_name' => 'Employee',
            'last_name' => 'User',
            'name' => 'Employee User',
            'email' => 'employee_'.uniqid().'@example.test',
            'password' => \Illuminate\Support\Facades\Hash::make('Pass1234!'),
            'role' => Role::Employee,
        ]);

        $this->actingAs($employee, 'api')
            ->getJson('/api/test-role-middleware')
            ->assertForbidden()
            ->assertJsonPath('message', 'You do not have the required role to access this resource.');
    }
}
