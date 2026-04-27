<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Auth\Enums\BusinessType;
use Modules\Team\Mail\NewUserCredentialsMail;
use Tests\TestCase;

class TeamApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_owner_can_create_disable_enable_and_delete_employee(): void
    {
        Mail::fake();

        $owner = $this->registerAndLoginOwner();

        $createResponse = $this->postJson('/api/v1/team/users', [
            'first_name' => 'Sara',
            'last_name' => 'Employee',
            'email' => 'sara.employee@example.test',
        ], $this->authHeaders($owner['token']));

        $createResponse->assertCreated()
            ->assertJsonPath('message', 'User account created successfully.')
            ->assertJsonPath('user.email', 'sara.employee@example.test')
            ->assertJsonPath('user.role', 'employee');

        Mail::assertSent(NewUserCredentialsMail::class);

        $employeeId = $createResponse->json('user.id');

        $this->assertDatabaseHas('users', [
            'id' => $employeeId,
            'email' => 'sara.employee@example.test',
            'role' => 'employee',
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('team_memberships', [
            'user_id' => $employeeId,
            'status' => 'active',
        ]);

        $disableResponse = $this->patchJson(
            "/api/v1/team/users/{$employeeId}/disable",
            [],
            $this->authHeaders($owner['token'])
        );

        $disableResponse->assertOk()
            ->assertJsonPath('message', 'User disabled successfully.')
            ->assertJsonPath('reassigned_tasks_count', 0)
            ->assertJsonPath('target_user.is_active', false);

        $this->assertDatabaseHas('users', [
            'id' => $employeeId,
            'is_active' => 0,
        ]);

        $this->assertDatabaseHas('team_memberships', [
            'user_id' => $employeeId,
            'status' => 'disabled',
        ]);

        $enableResponse = $this->patchJson(
            "/api/v1/team/users/{$employeeId}/enable",
            [],
            $this->authHeaders($owner['token'])
        );

        $enableResponse->assertOk()
            ->assertJsonPath('message', 'User enabled successfully.')
            ->assertJsonPath('user.id', $employeeId)
            ->assertJsonPath('user.is_active', true);

        $this->assertDatabaseHas('users', [
            'id' => $employeeId,
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('team_memberships', [
            'user_id' => $employeeId,
            'status' => 'active',
        ]);

        $deleteResponse = $this->deleteJson(
            "/api/v1/team/users/{$employeeId}",
            [],
            $this->authHeaders($owner['token'])
        );

        $deleteResponse->assertOk()
            ->assertJsonPath('message', 'User deleted successfully.');

        $this->assertDatabaseMissing('users', [
            'id' => $employeeId,
        ]);

        $this->assertDatabaseMissing('team_memberships', [
            'user_id' => $employeeId,
        ]);
    }

    public function test_business_owner_can_create_employee_with_position(): void
    {
        Mail::fake();

        $owner = $this->registerAndLoginOwner();

        $createResponse = $this->postJson('/api/v1/team/users', [
            'first_name' => 'Nora',
            'last_name' => 'Analyst',
            'position' => 'Data Analyst',
            'email' => 'nora.analyst@example.test',
        ], $this->authHeaders($owner['token']));

        $createResponse->assertCreated()
            ->assertJsonPath('user.email', 'nora.analyst@example.test')
            ->assertJsonPath('user.position', 'Data Analyst');

        $employeeId = $createResponse->json('user.id');

        $this->assertDatabaseHas('users', [
            'id' => $employeeId,
            'position' => 'Data Analyst',
        ]);

        $listResponse = $this->getJson('/api/v1/team/users', $this->authHeaders($owner['token']));

        $listResponse->assertOk()->assertJsonFragment([
            'id' => $employeeId,
            'position' => 'Data Analyst',
        ]);
    }

    private function registerAndLoginOwner(): array
    {
        $email = 'owner-'.uniqid(). '@example.test';
        $password = 'Pass1234!';

        $this->postJson('/api/v1/register', [
            'first_name' => 'Omar',
            'last_name' => 'Owner',
            'email' => $email,
            'business_type' => BusinessType::SaaS->value,
            'password' => $password,
            'password_confirmation' => $password,
            'captcha_token' => 'test-token',
        ])->assertCreated();

        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $loginResponse->assertOk();

        return [
            'email' => $email,
            'password' => $password,
            'token' => $loginResponse->json('data.token'),
        ];
    }

    private function authHeaders(string $token): array
    {
        return [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ];
    }
}
