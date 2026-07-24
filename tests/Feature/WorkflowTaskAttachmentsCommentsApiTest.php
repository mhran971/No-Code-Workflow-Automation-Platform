<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;
use Modules\Team\Models\Team;
use Modules\Team\Models\TeamMembership;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowInstanceComment;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Models\WorkflowTask;
use Modules\Workflows\Models\WorkflowVersion;
use Modules\Workflows\Repositories\WorkflowInstanceAttachmentRepository;
use Tests\TestCase;

class WorkflowTaskAttachmentsCommentsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_member_can_upload_list_and_delete_task_attachment(): void
    {
        Storage::fake('public');

        $fixture = $this->createWorkflowTaskFixture();

        $uploadResponse = $this->actingAs($fixture['actor'], 'api')->postJson("/api/v1/workflows/tasks/{$fixture['task']->id}/files", [
            'file' => UploadedFile::fake()->create('contract.pdf', 256, 'application/pdf'),
        ]);

        $uploadResponse->assertCreated()
            ->assertJsonPath('data.file_name', 'contract.pdf')
            ->assertJsonPath('data.mime_type', 'application/pdf');

        $attachmentId = (int) $uploadResponse->json('data.id');

        $this->assertDatabaseHas('workflow_instance_attachments', [
            'id' => $attachmentId,
            'workflow_instance_id' => $fixture['instance']->id,
            'uploaded_by' => $fixture['actor']->id,
            'file_name' => 'contract.pdf',
        ]);

        $this->actingAs($fixture['actor'], 'api')
            ->getJson("/api/v1/workflows/tasks/{$fixture['task']->id}/files")
            ->assertOk()
            ->assertJsonPath('data.0.id', $attachmentId)
            ->assertJsonPath('data.0.uploaded_by.id', $fixture['actor']->id);

        $this->actingAs($fixture['actor'], 'api')
            ->deleteJson("/api/v1/workflows/tasks/{$fixture['task']->id}/files/{$attachmentId}")
            ->assertOk()
            ->assertJsonPath('message', 'Attachment deleted.');

        $this->assertDatabaseMissing('workflow_instance_attachments', [
            'id' => $attachmentId,
        ]);
    }

    public function test_outside_tenant_cannot_read_or_write_task_comments(): void
    {
        $fixture = $this->createWorkflowTaskFixture();
        $otherTenant = $this->createTenant('Other Tenant');
        $outsider = $this->createUser($otherTenant, Role::Employee, 'outsider');

        $this->actingAs($outsider, 'api')
            ->getJson("/api/v1/workflows/tasks/{$fixture['task']->id}/comments")
            ->assertForbidden();

        $this->actingAs($outsider, 'api')
            ->postJson("/api/v1/workflows/tasks/{$fixture['task']->id}/comments", [
                'body' => 'Should not be stored',
            ])
            ->assertForbidden();
    }

    public function test_comments_are_returned_oldest_first_and_can_be_deleted(): void
    {
        $fixture = $this->createWorkflowTaskFixture();

        $firstComment = WorkflowInstanceComment::query()->create([
            'workflow_instance_id' => $fixture['instance']->id,
            'user_id' => $fixture['actor']->id,
            'body' => 'First note',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $secondComment = WorkflowInstanceComment::query()->create([
            'workflow_instance_id' => $fixture['instance']->id,
            'user_id' => $fixture['actor']->id,
            'body' => 'Second note',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($fixture['actor'], 'api')
            ->getJson("/api/v1/workflows/tasks/{$fixture['task']->id}/comments")
            ->assertOk()
            ->assertJsonPath('data.0.id', $firstComment->id)
            ->assertJsonPath('data.1.id', $secondComment->id);

        $this->actingAs($fixture['actor'], 'api')
            ->deleteJson("/api/v1/workflows/tasks/{$fixture['task']->id}/comments/{$secondComment->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Comment deleted.');

        $this->assertSoftDeleted('workflow_instance_comments', [
            'id' => $secondComment->id,
        ]);
    }

    public function test_attachment_storage_is_rolled_back_when_database_insert_fails(): void
    {
        Storage::fake('public');

        $fixture = $this->createWorkflowTaskFixture();

        $this->mock(WorkflowInstanceAttachmentRepository::class, function ($mock): void {
            $mock->shouldReceive('listByWorkflowInstance')->andReturn(collect());
            $mock->shouldReceive('deleteByIdAndWorkflowInstance')->andReturn(true);
            $mock->shouldReceive('create')->andThrow(new \RuntimeException('db failed'));
        });

        $this->actingAs($fixture['actor'], 'api')
            ->postJson("/api/v1/workflows/tasks/{$fixture['task']->id}/files", [
                'file' => UploadedFile::fake()->create('rollback.pdf', 128, 'application/pdf'),
            ])
            ->assertServerError();

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    private function createWorkflowTaskFixture(): array
    {
        $tenant = $this->createTenant('Acme Workflows');
        $owner = $this->createUser($tenant, Role::BusinessOwner, 'owner');
        $manager = $this->createUser($tenant, Role::Manager, 'manager');
        $actor = $this->createUser($tenant, Role::Employee, 'employee');
        $team = $this->createTeam($tenant, $manager, 'Operations');
        $this->assignToTeam($tenant, $manager, $team);
        $this->assignToTeam($tenant, $actor, $team);

        $workflow = Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'created_by_id' => $owner->id,
            'name' => 'Support Flow',
            'description' => 'Workflow for support tasks',
            'status' => WorkflowStatus::Active,
            'draft_definition' => [
                'trigger' => null,
                'nodes' => [],
                'edges' => [],
                'settings' => [],
            ],
            'draft_revision' => 1,
        ]);

        $version = WorkflowVersion::query()->create([
            'workflow_id' => $workflow->id,
            'tenant_id' => $tenant->id,
            'version_number' => 1,
            'version_label' => 'v1.0.0',
            'definition' => ['nodes' => [], 'edges' => [], 'settings' => []],
            'release_note' => null,
            'published_by_id' => $owner->id,
            'published_at' => now(),
        ]);

        $instance = WorkflowInstance::query()->create([
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'tenant_id' => $tenant->id,
            'status' => WorkflowInstanceStatus::Running,
            'payload' => [],
            'started_at' => now(),
        ]);

        $execution = WorkflowNodeExecution::query()->create([
            'instance_id' => $instance->id,
            'tenant_id' => $tenant->id,
            'node_key' => 'review-documents-node',
            'node_type' => 'task-node',
            'status' => 'pending',
            'attempt' => 1,
            'idempotency_key' => 'task-fixture-'.$instance->id,
            'started_at' => now(),
        ]);

        $task = WorkflowTask::query()->create([
            'instance_id' => $instance->id,
            'execution_id' => $execution->id,
            'tenant_id' => $tenant->id,
            'node_key' => 'review-documents',
            'assignee_id' => $actor->id,
            'title' => 'Review documents',
            'description' => 'Review the uploaded attachments',
            'input_schema' => [],
            'status' => 'open',
        ]);

        return compact('tenant', 'owner', 'manager', 'actor', 'team', 'workflow', 'version', 'instance', 'task');
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
