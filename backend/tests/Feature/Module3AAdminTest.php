<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Workspace;
use App\Domains\Staff\Models\StaffAbsence;
use App\Domains\Staff\Models\StaffAvailabilityRule;
use App\Domains\Staff\Models\StaffProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module3AAdminTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    protected function staffPermissions(): array
    {
        return [
            'identity.view',
            'identity.manage',
            'crm.view',
            'crm.manage',
            'staff.view',
            'staff.manage',
        ];
    }

    public function test_provider_profile_update_and_audit(): void
    {
        $ctx = $this->seedTenantContext($this->staffPermissions());

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/staff/'.$ctx['teamMember']->id.'/profile', [
                'is_bookable' => true,
                'show_in_online_booking' => true,
                'accepts_walk_ins' => false,
                'booking_display_name' => 'Test Stylist',
                'min_lead_time_minutes' => 30,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_bookable', true)
            ->assertJsonPath('data.booking_display_name', 'Test Stylist');

        $this->assertDatabaseHas('staff_profiles', [
            'team_member_id' => $ctx['teamMember']->id,
            'is_bookable' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.profile_updated']);
    }

    public function test_availability_crud_with_tenant_isolation(): void
    {
        $ctx = $this->seedTenantContext($this->staffPermissions());

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/staff/'.$ctx['teamMember']->id.'/availability', [
                'location_id' => $ctx['location']->id,
                'workspace_id' => $ctx['workspace']->id,
                'day_of_week' => 1,
                'start_time' => '09:00',
                'end_time' => '13:00',
            ]);

        $create->assertCreated();
        $ruleId = $create->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/staff/'.$ctx['teamMember']->id.'/availability/'.$ruleId, [
                'end_time' => '14:00',
            ])
            ->assertOk()
            ->assertJsonPath('data.end_time', '14:00');

        $foreignLocation = Location::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'name' => 'Foreign',
            'slug' => 'foreign',
            'timezone' => 'Europe/London',
            'is_active' => true,
        ]);

        $foreignMember = TeamMember::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'user_id' => $ctx['user']->id,
            'employment_type' => TeamMember::EMPLOYMENT_EMPLOYEE,
            'display_name' => 'Foreign',
            'is_active' => true,
        ]);

        $foreignRule = StaffAvailabilityRule::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'team_member_id' => $foreignMember->id,
            'location_id' => $foreignLocation->id,
            'day_of_week' => 2,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/staff/'.$foreignMember->id.'/availability/'.$foreignRule->id, [
                'end_time' => '12:00',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.availability_created']);
    }

    public function test_absence_create_and_cancel(): void
    {
        $ctx = $this->seedTenantContext($this->staffPermissions());

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/staff/'.$ctx['teamMember']->id.'/absences', [
                'category' => StaffAbsence::CATEGORY_HOLIDAY,
                'starts_at' => now()->addWeek()->toDateTimeString(),
                'ends_at' => now()->addWeek()->addDays(2)->toDateTimeString(),
                'note' => 'Break',
            ]);

        $create->assertCreated();
        $absenceId = $create->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->patchJson('/api/v1/admin/staff/'.$ctx['teamMember']->id.'/absences/'.$absenceId.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', StaffAbsence::STATUS_CANCELLED);

        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.absence_created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.absence_cancelled']);
    }

    public function test_operating_scope_validation(): void
    {
        $ctx = $this->seedTenantContext($this->staffPermissions());

        $foreignLocation = Location::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'name' => 'Other HQ',
            'slug' => 'other-hq',
            'timezone' => 'Europe/London',
            'is_active' => true,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/staff/'.$ctx['teamMember']->id.'/operating-scope', [
                'location_ids' => [$foreignLocation->id],
            ])
            ->assertStatus(422);

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/staff/'.$ctx['teamMember']->id.'/operating-scope', [
                'location_ids' => [$ctx['location']->id],
                'workspace_ids' => [$ctx['workspace']->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.operating_location_ids.0', $ctx['location']->id);

        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.operating_scope_updated']);
    }

    public function test_viewer_cannot_manage_staff(): void
    {
        $ctx = $this->seedTenantContext($this->staffPermissions());

        $this->withTenantAuth($ctx['viewerToken'])
            ->putJson('/api/v1/admin/staff/'.$ctx['teamMember']->id.'/profile', [
                'is_bookable' => true,
            ])
            ->assertForbidden();
    }
}
