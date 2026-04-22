<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_business_owner_and_tenant(): void
    {
        $payload = [
            'first_name' => 'Omar',
            'last_name' => 'Owner',
            'email' => 'owner@example.test',
            'business_type' => BusinessType::SaaS->value,
            'password' => 'Pass1234!',
            'password_confirmation' => 'Pass1234!',
            'captcha_token' => 'test-token',
        ];

        $response = $this->postJson('/api/v1/register', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.message', 'Registration successful.')
            ->assertJsonPath('data.user.email', 'owner@example.test')
            ->assertJsonPath('data.user.first_name', 'Omar')
            ->assertJsonPath('data.user.last_name', 'Owner');

        $this->assertDatabaseHas('tenants', [
            'business_name' => "Omar's business",
            'business_type' => BusinessType::SaaS->value,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'owner@example.test',
            'first_name' => 'Omar',
            'last_name' => 'Owner',
        ]);

        $user = User::query()->where('email', 'owner@example.test')->firstOrFail();

        $this->assertSame('business_owner', $user->role->value);
        $this->assertNotNull($user->tenant_id);

        $tenant = Tenant::query()->findOrFail($user->tenant_id);
        $this->assertSame("Omar's business", $tenant->business_name);
    }

    public function test_login_returns_jwt_token_for_registered_user(): void
    {
        $this->postJson('/api/v1/register', [
            'first_name' => 'Sara',
            'last_name' => 'Admin',
            'email' => 'sara@example.test',
            'business_type' => BusinessType::TechStartup->value,
            'password' => 'Pass1234!',
            'password_confirmation' => 'Pass1234!',
            'captcha_token' => 'test-token',
        ])->assertCreated();

        $response = $this->postJson('/api/v1/login', [
            'email' => 'sara@example.test',
            'password' => 'Pass1234!',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.message', 'Login successful.')
            ->assertJsonStructure([
                'data' => [
                    'message',
                    'redirect_url',
                    'expires_in_minutes',
                    'user' => [
                        'id',
                        'email',
                        'first_name',
                        'last_name',
                        'tenant' => [
                            'id',
                            'business_name',
                        ],
                    ],
                ],
                'token',
            ]);

        $this->assertNotEmpty($response->json('token'));
    }
}
