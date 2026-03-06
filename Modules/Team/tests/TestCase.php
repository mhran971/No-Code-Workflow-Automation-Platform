<?php

namespace Modules\Team\Tests;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Modules\Auth\Database\Factories\TenantFactory;
use Modules\Auth\Database\Factories\UserFactory;
use Modules\Auth\Models\User;
use Modules\Team\Database\Factories\TeamFactory;
use Modules\Team\app\Models\Team;

abstract class TestCase extends BaseTestCase
{
    /**
     * The currently authenticated user.
     */
    protected ?User $user = null;

    /**
     * The current tenant for testing.
     */
    protected $tenant = null;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Refresh migrations for each test
        $this->artisan('migrate:fresh', ['--force' => true]);
    }

    /**
     * Create a tenant for testing.
     */
    protected function createTenant()
    {
        return $this->tenant = TenantFactory::new()->create();
    }

    /**
     * Create an authenticated user for testing.
     */
    protected function actingAsUser(?array $overrides = []): User
    {
        $tenant = $this->tenant ?? $this->createTenant();

        $user = UserFactory::new()
            ->create(array_merge([
                'tenant_id' => $tenant->id,
            ], $overrides));

        $this->user = $user;

        return $this->actingAs($user, 'sanctum');
    }

    /**
     * Create a team for testing.
     */
    protected function createTeam(array $overrides = []): Team
    {
        $tenant = $this->tenant ?? $this->createTenant();

        return TeamFactory::new()
            ->create(array_merge([
                'tenant_id' => $tenant->id,
            ], $overrides));
    }

    /**
     * Create multiple teams for testing.
     */
    protected function createTeams(int $count, array $overrides = []): Collection
    {
        $tenant = $this->tenant ?? $this->createTenant();

        return TeamFactory::new()
            ->count($count)
            ->create(array_merge([
                'tenant_id' => $tenant->id,
            ], $overrides));
    }

    /**
     * Get the authentication token for API requests.
     */
    protected function getAuthToken(User $user): string
    {
        return $user->createToken('test-token')->plainTextToken;
    }
}

