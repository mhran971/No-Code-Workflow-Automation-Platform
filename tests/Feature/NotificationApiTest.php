<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\DeviceToken;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;
use Modules\Notifications\Enums\NotificationType;
use Modules\Notifications\Jobs\TaskDueSoonNotificationJob;
use Modules\Notifications\Jobs\TaskOverdueNotificationJob;
use Modules\Notifications\Notifications\TaskAssignedNotification;
use Modules\Notifications\Notifications\TaskDueSoonNotification;
use Modules\Notifications\Notifications\TaskOverdueNotification;
use Modules\Team\Models\Team;
use Modules\Team\Models\TeamMembership;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Models\WorkflowTask;
use Modules\Workflows\Models\WorkflowVersion;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────
    // B-21: Device Token Management
    // ──────────────────────────────────────────────────────────────

    public function test_user_can_register_device_token(): void
    {
        $fixture = $this->createFixture();

        $response = $this->actingAs($fixture['actor'], 'api')->postJson('/api/v1/mobile/device-tokens', [
            'token' => 'fcm-token-abc123',
            'device_name' => 'iPhone 15 Pro',
            'platform' => 'ios',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Device registered.');

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $fixture['actor']->id,
            'token' => 'fcm-token-abc123',
            'device_name' => 'iPhone 15 Pro',
            'platform' => 'ios',
        ]);
    }

    public function test_duplicate_device_token_is_upserted(): void
    {
        $fixture = $this->createFixture();

        // First registration
        $this->actingAs($fixture['actor'], 'api')->postJson('/api/v1/mobile/device-tokens', [
            'token' => 'fcm-token-same',
            'device_name' => 'Old Phone',
            'platform' => 'android',
        ]);

        // Second registration with same token, different name
        $this->actingAs($fixture['actor'], 'api')->postJson('/api/v1/mobile/device-tokens', [
            'token' => 'fcm-token-same',
            'device_name' => 'New Phone',
            'platform' => 'android',
        ]);

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', [
            'token' => 'fcm-token-same',
            'device_name' => 'New Phone',
        ]);
    }

    public function test_user_can_remove_device_token(): void
    {
        $fixture = $this->createFixture();

        DeviceToken::create([
            'user_id' => $fixture['actor']->id,
            'token' => 'fcm-token-to-remove',
            'platform' => 'ios',
        ]);

        $response = $this->actingAs($fixture['actor'], 'api')->deleteJson('/api/v1/mobile/device-tokens', [
            'token' => 'fcm-token-to-remove',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Device unregistered.');

        $this->assertDatabaseMissing('device_tokens', [
            'token' => 'fcm-token-to-remove',
        ]);
    }

    public function test_device_token_requires_valid_platform(): void
    {
        $fixture = $this->createFixture();

        $response = $this->actingAs($fixture['actor'], 'api')->postJson('/api/v1/mobile/device-tokens', [
            'token' => 'fcm-token-abc',
            'platform' => 'windows',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['platform']);
    }

    public function test_unauthenticated_cannot_register_device_token(): void
    {
        $this->postJson('/api/v1/mobile/device-tokens', [
            'token' => 'fcm-token',
            'platform' => 'ios',
        ])->assertUnauthorized();
    }

    // ──────────────────────────────────────────────────────────────
    // B-22: GET /api/v1/mobile/notifications
    // ──────────────────────────────────────────────────────────────

    public function test_user_can_list_notifications(): void
    {
        $fixture = $this->createFixtureWithTask();
        $user = $fixture['actor'];

        // Create notifications via the Notifiable trait
        $user->notify(new TaskAssignedNotification($fixture['task']));

        $response = $this->actingAs($user, 'api')->getJson('/api/v1/mobile/notifications');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', NotificationType::TaskAssigned->value)
            ->assertJsonPath('data.0.is_read', false)
            ->assertJsonStructure([
                'data' => [['id', 'type', 'title', 'body', 'data', 'is_read', 'read_at', 'created_at']],
            ]);
    }

    public function test_notifications_are_paginated(): void
    {
        $fixture = $this->createFixtureWithTask();
        $user = $fixture['actor'];

        // Create 25 notifications (exceeds page size of 20)
        for ($i = 0; $i < 25; $i++) {
            $user->notify(new TaskAssignedNotification($fixture['task']));
        }

        $response = $this->actingAs($user, 'api')->getJson('/api/v1/mobile/notifications');

        $response->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_notifications_can_be_filtered_by_type(): void
    {
        $fixture = $this->createFixtureWithTask();
        $user = $fixture['actor'];

        $user->notify(new TaskAssignedNotification($fixture['task']));
        $user->notify(new TaskDueSoonNotification($fixture['task']));

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/v1/mobile/notifications?type=task_due_soon');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', NotificationType::TaskDueSoon->value);
    }

    public function test_user_only_sees_own_notifications(): void
    {
        $fixture = $this->createFixtureWithTask();
        $otherTenant = $this->createTenant('Other Tenant');
        $otherUser = $this->createUser($otherTenant, Role::Employee, 'other');

        $fixture['actor']->notify(new TaskAssignedNotification($fixture['task']));

        $response = $this->actingAs($otherUser, 'api')->getJson('/api/v1/mobile/notifications');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // ──────────────────────────────────────────────────────────────
    // B-23: PATCH /api/v1/mobile/notifications/{id}/read
    // ──────────────────────────────────────────────────────────────

    public function test_user_can_mark_notification_as_read(): void
    {
        $fixture = $this->createFixtureWithTask();
        $user = $fixture['actor'];

        $user->notify(new TaskAssignedNotification($fixture['task']));
        $notification = $user->notifications()->first();

        $response = $this->actingAs($user, 'api')
            ->patchJson("/api/v1/mobile/notifications/{$notification->id}/read");

        $response->assertNoContent();

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    public function test_marking_already_read_notification_is_idempotent(): void
    {
        $fixture = $this->createFixtureWithTask();
        $user = $fixture['actor'];

        $user->notify(new TaskAssignedNotification($fixture['task']));
        $notification = $user->notifications()->first();
        $notification->markAsRead();

        $response = $this->actingAs($user, 'api')
            ->patchJson("/api/v1/mobile/notifications/{$notification->id}/read");

        $response->assertNoContent();
    }

    public function test_cannot_mark_other_users_notification_as_read(): void
    {
        $fixture = $this->createFixtureWithTask();
        $otherTenant = $this->createTenant('Other Tenant');
        $otherUser = $this->createUser($otherTenant, Role::Employee, 'other');

        $fixture['actor']->notify(new TaskAssignedNotification($fixture['task']));
        $notification = $fixture['actor']->notifications()->first();

        $response = $this->actingAs($otherUser, 'api')
            ->patchJson("/api/v1/mobile/notifications/{$notification->id}/read");

        $response->assertNotFound();
    }

    // ──────────────────────────────────────────────────────────────
    // B-24: PATCH /api/v1/mobile/notifications/read-all
    // ──────────────────────────────────────────────────────────────

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $fixture = $this->createFixtureWithTask();
        $user = $fixture['actor'];

        $user->notify(new TaskAssignedNotification($fixture['task']));
        $user->notify(new TaskDueSoonNotification($fixture['task']));
        $user->notify(new TaskOverdueNotification($fixture['task']));

        $response = $this->actingAs($user, 'api')
            ->patchJson('/api/v1/mobile/notifications/read-all');

        $response->assertOk()
            ->assertJsonPath('updated_count', 3);

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_mark_all_read_returns_zero_when_none_unread(): void
    {
        $fixture = $this->createFixture();
        $user = $fixture['actor'];

        $response = $this->actingAs($user, 'api')
            ->patchJson('/api/v1/mobile/notifications/read-all');

        $response->assertOk()
            ->assertJsonPath('updated_count', 0);
    }

    // ──────────────────────────────────────────────────────────────
    // B-25: GET /api/v1/mobile/notifications/unread-count
    // ──────────────────────────────────────────────────────────────

    public function test_unread_count_returns_correct_badge_number(): void
    {
        $fixture = $this->createFixtureWithTask();
        $user = $fixture['actor'];

        $user->notify(new TaskAssignedNotification($fixture['task']));
        $user->notify(new TaskDueSoonNotification($fixture['task']));

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/v1/mobile/notifications/unread-count');

        $response->assertOk()
            ->assertJsonPath('unread_count', 2);
    }

    public function test_unread_count_decreases_after_marking_read(): void
    {
        $fixture = $this->createFixtureWithTask();
        $user = $fixture['actor'];

        $user->notify(new TaskAssignedNotification($fixture['task']));
        $user->notify(new TaskDueSoonNotification($fixture['task']));

        // Mark one as read
        $notification = $user->notifications()->first();
        $notification->markAsRead();

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/v1/mobile/notifications/unread-count');

        $response->assertOk()
            ->assertJsonPath('unread_count', 1);
    }

    public function test_unread_count_is_zero_for_new_user(): void
    {
        $fixture = $this->createFixture();

        $response = $this->actingAs($fixture['actor'], 'api')
            ->getJson('/api/v1/mobile/notifications/unread-count');

        $response->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    // ──────────────────────────────────────────────────────────────
    // B-26: Scheduled Jobs
    // ──────────────────────────────────────────────────────────────

    public function test_task_due_soon_job_sends_notification(): void
    {
        Notification::fake();

        $fixture = $this->createFixtureWithTask(['due_at' => now()->addHours(12)]);

        (new TaskDueSoonNotificationJob)->handle();

        Notification::assertSentTo($fixture['actor'], TaskDueSoonNotification::class);
    }

    public function test_task_due_soon_job_skips_tasks_beyond_24_hours(): void
    {
        Notification::fake();

        $this->createFixtureWithTask(['due_at' => now()->addHours(48)]);

        (new TaskDueSoonNotificationJob)->handle();

        Notification::assertNothingSent();
    }

    public function test_task_due_soon_job_skips_completed_tasks(): void
    {
        Notification::fake();

        $this->createFixtureWithTask([
            'due_at' => now()->addHours(12),
            'status' => 'completed',
        ]);

        (new TaskDueSoonNotificationJob)->handle();

        Notification::assertNothingSent();
    }

    public function test_task_due_soon_job_does_not_send_duplicate(): void
    {
        $fixture = $this->createFixtureWithTask(['due_at' => now()->addHours(12)]);

        // First run — sends notification (real, not faked)
        (new TaskDueSoonNotificationJob)->handle();

        $this->assertSame(1, $fixture['actor']->notifications()->count());

        // Second run — should not duplicate
        (new TaskDueSoonNotificationJob)->handle();

        $this->assertSame(1, $fixture['actor']->notifications()->count());
    }

    public function test_task_overdue_job_sends_notification(): void
    {
        Notification::fake();

        $fixture = $this->createFixtureWithTask(['due_at' => now()->subHours(2)]);

        (new TaskOverdueNotificationJob)->handle();

        Notification::assertSentTo($fixture['actor'], TaskOverdueNotification::class);
    }

    public function test_task_overdue_job_skips_tasks_not_yet_due(): void
    {
        Notification::fake();

        $this->createFixtureWithTask(['due_at' => now()->addHours(5)]);

        (new TaskOverdueNotificationJob)->handle();

        Notification::assertNothingSent();
    }

    public function test_task_overdue_job_does_not_send_duplicate(): void
    {
        $fixture = $this->createFixtureWithTask(['due_at' => now()->subHours(2)]);

        (new TaskOverdueNotificationJob)->handle();
        $this->assertSame(1, $fixture['actor']->notifications()->count());

        (new TaskOverdueNotificationJob)->handle();
        $this->assertSame(1, $fixture['actor']->notifications()->count());
    }

    // ──────────────────────────────────────────────────────────────
    // B-27: WorkflowTask Observer
    // ──────────────────────────────────────────────────────────────

    public function test_observer_sends_notification_on_task_creation(): void
    {
        Notification::fake();

        $fixture = $this->createFixture();

        // Creating a task should trigger the observer
        $this->createTaskForFixture($fixture, ['assignee_id' => $fixture['actor']->id]);

        Notification::assertSentTo($fixture['actor'], TaskAssignedNotification::class);
    }

    public function test_observer_skips_task_without_assignee(): void
    {
        Notification::fake();

        $fixture = $this->createFixture();

        $this->createTaskForFixture($fixture, ['assignee_id' => null]);

        Notification::assertNothingSent();
    }

    // ──────────────────────────────────────────────────────────────
    // B-28 & B-29: Documents Mobile API
    // ──────────────────────────────────────────────────────────────

    public function test_user_can_list_mobile_documents(): void
    {
        $fixture = $this->createFixture();

        $response = $this->actingAs($fixture['actor'], 'api')
            ->getJson('/api/v1/mobile/documents');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_unauthenticated_cannot_access_mobile_documents(): void
    {
        $this->getJson('/api/v1/mobile/documents')->assertUnauthorized();
    }

    public function test_mobile_document_show_returns_404_for_nonexistent(): void
    {
        $fixture = $this->createFixture();

        $response = $this->actingAs($fixture['actor'], 'api')
            ->getJson('/api/v1/mobile/documents/99999');

        $response->assertNotFound();
    }

    // ──────────────────────────────────────────────────────────────
    // Notification content / i18n
    // ──────────────────────────────────────────────────────────────

    public function test_notification_contains_correct_data_structure(): void
    {
        $fixture = $this->createFixtureWithTask();
        $user = $fixture['actor'];

        $user->notify(new TaskAssignedNotification($fixture['task']));

        $notification = $user->notifications()->first();
        $data = $notification->data;

        $this->assertSame(NotificationType::TaskAssigned->value, $data['type']);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('body', $data);
        $this->assertSame($fixture['task']->id, $data['task_id']);
        $this->assertSame($fixture['task']->instance_id, $data['instance_id']);
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    private function createFixture(): array
    {
        $tenant = $this->createTenant('Test Tenant');
        $owner = $this->createUser($tenant, Role::BusinessOwner, 'owner');
        $manager = $this->createUser($tenant, Role::Manager, 'manager');
        $actor = $this->createUser($tenant, Role::Employee, 'actor');

        $team = $this->createTeam($tenant, $manager, 'Test Team');
        $this->assignToTeam($tenant, $actor, $team);

        return compact('tenant', 'owner', 'manager', 'actor', 'team');
    }

    private function createFixtureWithTask(array $taskOverrides = []): array
    {
        $fixture = $this->createFixture();

        // Disable observer to avoid unwanted notifications during setup
        WorkflowTask::unsetEventDispatcher();

        $task = $this->createTaskForFixtureRaw($fixture, $taskOverrides);

        // Re-enable observer
        WorkflowTask::setEventDispatcher(app('events'));

        return array_merge($fixture, ['task' => $task]);
    }

    private function createTaskForFixture(array $fixture, array $overrides = []): WorkflowTask
    {
        $workflow = Workflow::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'team_id' => $fixture['team']->id,
            'name' => 'Test Workflow',
            'status' => WorkflowStatus::Active,
            'created_by_id' => $fixture['owner']->id,
            'draft_definition' => ['nodes' => [], 'edges' => []],
        ]);

        $version = WorkflowVersion::query()->create([
            'workflow_id' => $workflow->id,
            'tenant_id' => $fixture['tenant']->id,
            'version_number' => 1,
            'version_label' => 'v1.0',
            'published_by_id' => $fixture['owner']->id,
            'published_at' => now(),
            'definition' => ['nodes' => [], 'edges' => []],
        ]);

        $instance = WorkflowInstance::query()->create([
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'tenant_id' => $fixture['tenant']->id,
            'status' => WorkflowInstanceStatus::Running,
            'payload' => [],
            'started_at' => now(),
        ]);

        $execution = WorkflowNodeExecution::query()->create([
            'instance_id' => $instance->id,
            'tenant_id' => $fixture['tenant']->id,
            'node_key' => 'task-node-1',
            'node_type' => 'task-node',
            'status' => 'pending',
            'attempt' => 1,
            'idempotency_key' => 'test-'.uniqid(),
            'started_at' => now(),
        ]);

        return WorkflowTask::query()->create(array_merge([
            'instance_id' => $instance->id,
            'execution_id' => $execution->id,
            'tenant_id' => $fixture['tenant']->id,
            'node_key' => 'task-node-1',
            'assignee_id' => $fixture['actor']->id,
            'title' => 'Test Task',
            'description' => 'A test task for notifications',
            'input_schema' => [],
            'status' => 'open',
        ], $overrides));
    }

    private function createTaskForFixtureRaw(array $fixture, array $overrides = []): WorkflowTask
    {
        $workflow = Workflow::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'team_id' => $fixture['team']->id,
            'name' => 'Test Workflow',
            'status' => WorkflowStatus::Active,
            'created_by_id' => $fixture['owner']->id,
            'draft_definition' => ['nodes' => [], 'edges' => []],
        ]);

        $version = WorkflowVersion::query()->create([
            'workflow_id' => $workflow->id,
            'tenant_id' => $fixture['tenant']->id,
            'version_number' => 1,
            'version_label' => 'v1.0',
            'published_by_id' => $fixture['owner']->id,
            'published_at' => now(),
            'definition' => ['nodes' => [], 'edges' => []],
        ]);

        $instance = WorkflowInstance::query()->create([
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'tenant_id' => $fixture['tenant']->id,
            'status' => WorkflowInstanceStatus::Running,
            'payload' => [],
            'started_at' => now(),
        ]);

        $execution = WorkflowNodeExecution::query()->create([
            'instance_id' => $instance->id,
            'tenant_id' => $fixture['tenant']->id,
            'node_key' => 'task-node-1',
            'node_type' => 'task-node',
            'status' => 'pending',
            'attempt' => 1,
            'idempotency_key' => 'test-'.uniqid(),
            'started_at' => now(),
        ]);

        return WorkflowTask::query()->create(array_merge([
            'instance_id' => $instance->id,
            'execution_id' => $execution->id,
            'tenant_id' => $fixture['tenant']->id,
            'node_key' => 'task-node-1',
            'assignee_id' => $fixture['actor']->id,
            'title' => 'Test Task',
            'description' => 'A test task for notifications',
            'input_schema' => [],
            'status' => 'open',
        ], $overrides));
    }

    private function createTenant(string $businessName): Tenant
    {
        return Tenant::query()->create([
            'business_name' => $businessName,
            'business_type' => BusinessType::SaaS->value,
        ]);
    }

    private function createUser(Tenant $tenant, Role $role, string $prefix): User
    {
        return User::query()->create([
            'first_name' => ucfirst($prefix),
            'last_name' => 'User',
            'name' => ucfirst($prefix).' User',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'password' => Hash::make('Pass1234!'),
            'tenant_id' => $tenant->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function createTeam(Tenant $tenant, User $manager, string $name): Team
    {
        return Team::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'manager_id' => $manager->id,
        ]);
    }

    private function assignToTeam(Tenant $tenant, User $user, Team $team): void
    {
        TeamMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'team_id' => $team->id,
            'status' => 'active',
        ]);
    }
}
