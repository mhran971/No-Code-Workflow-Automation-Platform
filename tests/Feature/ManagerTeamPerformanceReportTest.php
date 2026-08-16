<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\User;
use Modules\Team\Models\Team;
use Modules\Team\Models\TeamMembership;
use Modules\Workflows\Models\WorkflowTask;
use Tests\TestCase;

class ManagerTeamPerformanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_fetch_team_performance_report_for_own_team(): void
    {
        $setup = $this->setupTenantWithTeam();
        $manager = $setup['manager'];
        $managerToken = $setup['manager_token'];
        $member = $setup['member'];
        $tenantId = (int) $manager->tenant_id;

        // Create tasks for team member
        WorkflowTask::query()->create([
            'tenant_id' => $tenantId,
            'node_key' => 'review_task',
            'assignee_id' => $member->id,
            'title' => 'Review Application',
            'status' => 'completed',
            'due_at' => Carbon::now()->addDays(2),
            'completed_at' => Carbon::now()->addHours(3),
            'created_at' => Carbon::now()->subDays(1),
        ]);

        WorkflowTask::query()->create([
            'tenant_id' => $tenantId,
            'node_key' => 'approve_task',
            'assignee_id' => $member->id,
            'title' => 'Approve Budget',
            'status' => 'open',
            'due_at' => Carbon::now()->subDays(1), // Overdue
            'created_at' => Carbon::now()->subDays(2),
        ]);

        $response = $this->getJson(
            '/api/v1/workflows/reports/team-performance',
            $this->authHeaders($managerToken)
        );

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('team.id', $setup['team']->id)
            ->assertJsonPath('summary.total_tasks_assigned', 2)
            ->assertJsonPath('summary.total_tasks_completed', 1)
            ->assertJsonPath('summary.total_tasks_open', 1)
            ->assertJsonPath('summary.total_tasks_overdue', 1)
            ->assertJsonPath('summary.completion_rate', 50.0)
            ->assertJsonPath('summary.overdue_rate', 50.0)
            ->assertJsonStructure([
                'status',
                'team' => ['id', 'name', 'manager'],
                'period' => ['from', 'to'],
                'summary' => [
                    'total_members',
                    'total_tasks_assigned',
                    'total_tasks_completed',
                    'total_tasks_open',
                    'total_tasks_overdue',
                    'completion_rate',
                    'overdue_rate',
                    'avg_turnaround_hours',
                ],
                'charts' => [
                    'status_distribution',
                    'completion_trend',
                    'member_ranking',
                ],
                'members',
            ]);
    }

    public function test_manager_cannot_view_data_of_members_from_another_team(): void
    {
        $setup1 = $this->setupTenantWithTeam('Team Alpha');
        $setup2 = $this->setupTenantWithTeam('Team Beta');

        $manager1Token = $setup1['manager_token'];
        $member2 = $setup2['member'];

        // Create task for Team Beta member
        WorkflowTask::query()->create([
            'tenant_id' => (int) $setup2['manager']->tenant_id,
            'node_key' => 'task_beta',
            'assignee_id' => $member2->id,
            'title' => 'Beta Task',
            'status' => 'completed',
            'created_at' => Carbon::now()->subDays(1),
        ]);

        // Manager 1 requests report
        $response = $this->getJson(
            '/api/v1/workflows/reports/team-performance',
            $this->authHeaders($manager1Token)
        );

        $response->assertOk();
        $this->assertEquals(0, $response->json('summary.total_tasks_assigned'));

        // Manager 1 tries to access Team Beta via team_id query parameter
        $forbiddenResponse = $this->getJson(
            '/api/v1/workflows/reports/team-performance?team_id=' . $setup2['team']->id,
            $this->authHeaders($manager1Token)
        );

        $forbiddenResponse->assertForbidden();
    }

    public function test_business_owner_can_view_any_team_performance_via_team_id(): void
    {
        $setup = $this->setupTenantWithTeam('Support Team');
        $ownerToken = $setup['owner_token'];
        $team = $setup['team'];

        $response = $this->getJson(
            '/api/v1/workflows/reports/team-performance?team_id=' . $team->id,
            $this->authHeaders($ownerToken)
        );

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('team.id', $team->id);
    }

    public function test_employee_and_admin_are_forbidden_from_team_performance_report(): void
    {
        $setup = $this->setupTenantWithTeam();
        $member = $setup['member'];
        $memberToken = $this->loginAndGetToken($member->email);

        $response = $this->getJson(
            '/api/v1/workflows/reports/team-performance',
            $this->authHeaders($memberToken)
        );

        $response->assertForbidden();
    }

    public function test_team_performance_date_filters_accurately_filter_tasks(): void
    {
        $setup = $this->setupTenantWithTeam();
        $managerToken = $setup['manager_token'];
        $member = $setup['member'];
        $tenantId = (int) $setup['manager']->tenant_id;

        // Old task (60 days ago)
        WorkflowTask::query()->create([
            'tenant_id' => $tenantId,
            'node_key' => 'old_task',
            'assignee_id' => $member->id,
            'title' => 'Old Task',
            'status' => 'completed',
            'created_at' => Carbon::now()->subDays(60),
            'completed_at' => Carbon::now()->subDays(59),
        ]);

        // Recent task (5 days ago)
        WorkflowTask::query()->create([
            'tenant_id' => $tenantId,
            'node_key' => 'recent_task',
            'assignee_id' => $member->id,
            'title' => 'Recent Task',
            'status' => 'completed',
            'created_at' => Carbon::now()->subDays(5),
            'completed_at' => Carbon::now()->subDays(4),
        ]);

        // Filter last 10 days
        $response = $this->getJson(
            '/api/v1/workflows/reports/team-performance?date_from=' . Carbon::now()->subDays(10)->toDateString() . '&date_to=' . Carbon::now()->toDateString(),
            $this->authHeaders($managerToken)
        );

        $response->assertOk()
            ->assertJsonPath('summary.total_tasks_assigned', 1)
            ->assertJsonPath('summary.total_tasks_completed', 1);
    }

    public function test_manager_can_export_team_performance_as_csv(): void
    {
        $setup = $this->setupTenantWithTeam();
        $managerToken = $setup['manager_token'];

        $response = $this->get(
            '/api/v1/workflows/reports/team-performance/export?format=csv',
            $this->authHeaders($managerToken)
        );

        $response->assertOk();
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment; filename=', (string) $response->headers->get('content-disposition'));
    }

    public function test_manager_can_export_team_performance_as_pdf(): void
    {
        $setup = $this->setupTenantWithTeam();
        $managerToken = $setup['manager_token'];

        $response = $this->get(
            '/api/v1/workflows/reports/team-performance/export?format=pdf',
            $this->authHeaders($managerToken)
        );

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    public function test_unauthenticated_request_is_rejected_with_401(): void
    {
        $response = $this->getJson('/api/v1/workflows/reports/team-performance', [
            'Accept' => 'application/json',
        ]);

        $response->assertUnauthorized();
    }

    private function setupTenantWithTeam(string $teamName = 'Operations Team'): array
    {
        $ownerEmail = 'owner-' . uniqid() . '@example.test';
        $password = 'Pass1234!';

        $this->postJson('/api/v1/register', [
            'first_name' => 'John',
            'last_name' => 'Owner',
            'email' => $ownerEmail,
            'business_type' => BusinessType::SaaS->value,
            'password' => $password,
            'password_confirmation' => $password,
            'captcha_token' => 'test-token',
        ])->assertCreated();

        $ownerToken = $this->loginAndGetToken($ownerEmail, $password);
        $owner = User::query()->where('email', $ownerEmail)->firstOrFail();
        $tenantId = (int) $owner->tenant_id;

        // Create manager
        $managerEmail = 'mgr-' . uniqid() . '@example.test';
        $manager = User::query()->create([
            'first_name' => 'Mary',
            'last_name' => 'Manager',
            'name' => 'Mary Manager',
            'email' => $managerEmail,
            'password' => Hash::make($password),
            'tenant_id' => $tenantId,
            'role' => Role::Manager,
            'is_active' => true,
        ]);
        $managerToken = $this->loginAndGetToken($managerEmail, $password);

        // Create team
        $team = Team::query()->create([
            'tenant_id' => $tenantId,
            'name' => $teamName,
            'manager_id' => $manager->id,
        ]);

        // Create member
        $memberEmail = 'member-' . uniqid() . '@example.test';
        $member = User::query()->create([
            'first_name' => 'Sam',
            'last_name' => 'Member',
            'name' => 'Sam Member',
            'email' => $memberEmail,
            'password' => Hash::make($password),
            'tenant_id' => $tenantId,
            'role' => Role::Employee,
            'is_active' => true,
        ]);

        TeamMembership::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $member->id,
            'team_id' => $team->id,
            'status' => 'active',
        ]);

        TeamMembership::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $manager->id,
            'team_id' => $team->id,
            'status' => 'active',
        ]);

        return [
            'owner' => $owner,
            'owner_token' => $ownerToken,
            'manager' => $manager,
            'manager_token' => $managerToken,
            'team' => $team,
            'member' => $member,
        ];
    }

    private function loginAndGetToken(string $email, string $password = 'Pass1234!'): string
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $response->assertOk();

        return (string) ($response->json('token') ?? $response->json('data.token'));
    }

    private function authHeaders(string $token): array
    {
        return [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ];
    }
}
