<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\PlatformAnnouncement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;
use Modules\Team\Models\Team;
use Modules\Workflows\Enums\TriggerType;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowInstance;
use Tests\TestCase;

class SuperAdminFilamentPanelTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected Tenant $tenant;
    protected User $businessOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'name' => 'Super Admin',
            'email' => 'superadmin@platform.test',
            'password' => Hash::make('Password123!'),
            'role' => Role::SuperAdmin,
            'is_active' => true,
            'tenant_id' => null,
        ]);

        $this->tenant = Tenant::create([
            'business_name' => 'Acme Corp',
            'business_type' => BusinessType::SaaS,
            'is_active' => true,
            'maintenance_mode' => false,
        ]);

        $this->businessOwner = User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'name' => 'John Doe',
            'email' => 'john@acme.test',
            'password' => Hash::make('Password123!'),
            'role' => Role::BusinessOwner,
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_unauthenticated_user_is_redirected_to_admin_login(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/admin/login');
    }

    public function test_regular_tenant_user_cannot_access_super_admin_panel(): void
    {
        $response = $this->actingAs($this->businessOwner)->get('/admin');

        $response->assertForbidden();
    }

    public function test_super_admin_can_access_super_admin_panel_dashboard(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/admin');

        $response->assertOk();
    }

    public function test_super_admin_can_view_tenants_and_teams_and_users_pages(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/admin/tenants')
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->get('/admin/teams')
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->get('/admin/users')
            ->assertOk();
    }

    public function test_super_admin_can_view_workflows_metadata_and_runs_pages(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/admin/workflows')
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->get('/admin/workflow-instances')
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->get('/admin/admin-audit-logs')
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->get('/admin/platform-announcements')
            ->assertOk();
    }

    public function test_deactivated_tenant_user_cannot_login_via_api(): void
    {
        $this->tenant->update([
            'is_active' => false,
            'deactivated_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $this->businessOwner->email,
            'password' => 'Password123!',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'This tenant workspace has been deactivated. Please contact platform support.');
    }

    public function test_tenant_maintenance_mode_is_enforced_by_api_middleware(): void
    {
        $this->tenant->update([
            'maintenance_mode' => true,
            'maintenance_message' => 'Custom maintenance notice for testing.',
        ]);

        $token = auth('api')->login($this->businessOwner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me');

        $response->assertStatus(503)
            ->assertJsonPath('message', 'Tenant workspace is currently under maintenance.')
            ->assertJsonPath('notice', 'Custom maintenance notice for testing.');
    }

    public function test_audit_log_records_super_admin_actions(): void
    {
        $log = AdminAuditLog::record(
            'tenant.deactivated',
            "Deactivated tenant '{$this->tenant->business_name}'",
            $this->tenant,
            ['is_active' => false]
        );

        $this->assertDatabaseHas('admin_audit_logs', [
            'id' => $log->id,
            'action' => 'tenant.deactivated',
            'target_type' => Tenant::class,
            'target_id' => $this->tenant->id,
        ]);
    }

    public function test_platform_announcements_scope_filters_by_tenant(): void
    {
        // Global announcement
        PlatformAnnouncement::create([
            'title' => 'Global Notice',
            'message' => 'System wide update',
            'type' => 'info',
            'target_tenant_id' => null,
            'is_active' => true,
        ]);

        // Specific tenant announcement
        PlatformAnnouncement::create([
            'title' => 'Acme Special Notice',
            'message' => 'Welcome Acme',
            'type' => 'success',
            'target_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        // Other tenant announcement
        $otherTenant = Tenant::create([
            'business_name' => 'Other Corp',
            'business_type' => BusinessType::DigitalAgency,
        ]);

        PlatformAnnouncement::create([
            'title' => 'Other Notice',
            'message' => 'Not for Acme',
            'type' => 'warning',
            'target_tenant_id' => $otherTenant->id,
            'is_active' => true,
        ]);

        $announcementsForAcme = PlatformAnnouncement::activeForTenant($this->tenant->id)->get();
        $this->assertCount(2, $announcementsForAcme);
        $this->assertTrue($announcementsForAcme->contains('title', 'Global Notice'));
        $this->assertTrue($announcementsForAcme->contains('title', 'Acme Special Notice'));
        $this->assertFalse($announcementsForAcme->contains('title', 'Other Notice'));
    }
}
