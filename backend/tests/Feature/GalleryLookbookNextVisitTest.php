<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\BookableService;
use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientVisit;
use App\Domains\Crm\Services\NextVisitReminderService;
use App\Domains\Identity\Models\TenantModuleOverride;
use App\Domains\Lookbook\Models\LookbookItem;
use App\Domains\Lookbook\Services\LookbookSeedService;
use App\Domains\Staff\Models\StaffAvailabilityRule;
use App\Domains\Staff\Models\StaffProfile;
use App\Shared\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class GalleryLookbookNextVisitTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    protected function galleryPermissions(): array
    {
        return [
            'identity.view',
            'gallery.view',
            'gallery.manage',
            'lookbook.view',
            'lookbook.manage',
            'next_visit.view',
            'next_visit.manage',
            'crm.view',
            'crm.manage',
            'booking.view',
            'booking.manage',
            'staff.view',
            'staff.manage',
            'memberships.view',
            'memberships.manage',
        ];
    }

    public function test_gallery_admin_create_gated_by_permission(): void
    {
        $ctx = $this->seedTenantContext($this->galleryPermissions());

        $created = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/gallery/works', [
                'image_url' => 'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=800',
                'caption' => 'Colour finish',
                'service_tag' => 'colour',
            ]);

        $created->assertCreated()
            ->assertJsonPath('data.caption', 'Colour finish');

        $this->assertDatabaseHas('audit_logs', ['action' => 'gallery.work.created']);

        $this->withTenantAuth($ctx['viewerToken'])
            ->postJson('/api/v1/admin/gallery/works', [
                'image_url' => 'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=800',
                'caption' => 'Blocked',
            ])
            ->assertForbidden();
    }

    public function test_gallery_admin_create_gated_by_feature(): void
    {
        $ctx = $this->seedTenantContext($this->galleryPermissions());

        TenantModuleOverride::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'module_key' => 'gallery',
            'enabled' => false,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/gallery/works', [
                'image_url' => 'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=800',
                'caption' => 'Blocked by feature',
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'module_upgrade_required');
    }

    public function test_lookbook_seeds_20_items_for_boutique_tenant(): void
    {
        $ctx = $this->seedTenantContext($this->galleryPermissions());
        $ctx['tenant']->forceFill(['business_type' => 'boutique'])->save();

        app(TenantContext::class)->set($ctx['tenant']);
        $result = app(LookbookSeedService::class)->seedForTenant($ctx['tenant']->fresh());

        $this->assertTrue($result['seeded']);
        $this->assertSame(20, $result['count']);
        $this->assertSame('boutique', $result['category']);
        $this->assertEquals(20, LookbookItem::withoutGlobalScopes()
            ->where('tenant_id', $ctx['tenant']->id)
            ->where('is_seeded', true)
            ->count());

        $again = app(LookbookSeedService::class)->seedForTenant($ctx['tenant']->fresh());
        $this->assertFalse($again['seeded']);
        $this->assertSame(20, $again['count']);
    }

    public function test_public_lookbook_empty_or_403_when_feature_off(): void
    {
        $ctx = $this->seedTenantContext($this->galleryPermissions());
        $ctx['tenant']->forceFill(['business_type' => 'boutique'])->save();
        app(TenantContext::class)->set($ctx['tenant']);
        app(LookbookSeedService::class)->seedForTenant($ctx['tenant']->fresh());

        TenantModuleOverride::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'module_key' => 'lookbook',
            'enabled' => false,
        ]);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/lookbook/items')
            ->assertForbidden()
            ->assertJsonPath('code', 'feature_disabled');
    }

    public function test_member_check_in_returns_prompt_next_visit_when_feature_on(): void
    {
        $ctx = $this->seedTenantContext($this->galleryPermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Next',
            'last_name' => 'Visit',
            'email' => 'nextvisit@example.test',
            'phone' => '+447700900801',
            'is_active' => true,
            'primary_location_id' => $ctx['location']->id,
        ]);

        $login = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/member/login', [
                'email' => 'nextvisit@example.test',
                'phone' => '+447700900801',
            ]);
        $login->assertOk();
        $token = $login->json('data.token');

        $checkIn = $this->withHeaders([
            'X-Tenant-Slug' => $ctx['tenant']->slug,
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/v1/member/check-in', [
            'location_id' => $ctx['location']->id,
        ]);

        $checkIn->assertOk()
            ->assertJsonPath('data.prompt_next_visit', true)
            ->assertJsonPath('data.visit.next_visit_appointment_id', null);

        $this->assertNotEmpty($checkIn->json('data.visit.id'));
    }

    public function test_schedule_creates_appointment_with_booking_source_next_visit(): void
    {
        $ctx = $this->seedTenantContext($this->galleryPermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        StaffProfile::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'team_member_id' => $ctx['teamMember']->id,
            'is_bookable' => true,
        ]);
        $ctx['teamMember']->operatingLocations()->sync([$ctx['location']->id]);

        foreach ([1, 2, 3, 4, 5, 6, 7] as $day) {
            StaffAvailabilityRule::withoutGlobalScopes()->create([
                'tenant_id' => $ctx['tenant']->id,
                'team_member_id' => $ctx['teamMember']->id,
                'location_id' => $ctx['location']->id,
                'workspace_id' => $ctx['workspace']->id,
                'day_of_week' => $day,
                'start_time' => '08:00',
                'end_time' => '20:00',
                'is_active' => true,
            ]);
        }

        $service = BookableService::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Cut',
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Book',
            'last_name' => 'Next',
            'email' => 'booknext@example.test',
            'phone' => '+447700900802',
            'is_active' => true,
            'primary_location_id' => $ctx['location']->id,
        ]);

        $login = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/member/login', [
                'email' => 'booknext@example.test',
                'phone' => '+447700900802',
            ]);
        $token = $login->json('data.token');
        $headers = [
            'X-Tenant-Slug' => $ctx['tenant']->slug,
            'Authorization' => 'Bearer '.$token,
        ];

        $checkIn = $this->withHeaders($headers)
            ->postJson('/api/v1/member/check-in', [
                'location_id' => $ctx['location']->id,
            ]);
        $checkIn->assertOk();
        $visitId = $checkIn->json('data.visit.id');

        $startsAt = Carbon::now($ctx['tenant']->timezone)->next('Tuesday')->setTime(10, 0);

        $schedule = $this->withHeaders($headers)
            ->postJson('/api/v1/member/next-visit/schedule', [
                'visit_id' => $visitId,
                'starts_at' => $startsAt->toDateTimeString(),
                'team_member_id' => $ctx['teamMember']->id,
                'location_id' => $ctx['location']->id,
                'workspace_id' => $ctx['workspace']->id,
                'services' => [['booking_service_id' => $service->id]],
            ]);

        $schedule->assertCreated()
            ->assertJsonPath('data.booking_source', Appointment::SOURCE_NEXT_VISIT)
            ->assertJsonPath('data.status', Appointment::STATUS_CONFIRMED);

        $appointmentId = $schedule->json('data.id');
        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'booking_source' => Appointment::SOURCE_NEXT_VISIT,
            'origin_visit_id' => $visitId,
        ]);
        $this->assertDatabaseHas('client_visits', [
            'id' => $visitId,
            'next_visit_appointment_id' => $appointmentId,
        ]);
    }

    public function test_reminder_service_marks_72h(): void
    {
        $ctx = $this->seedTenantContext($this->galleryPermissions());
        app(TenantContext::class)->set($ctx['tenant']);
        $ctx['tenant']->forceFill(['owner_whatsapp' => '+447700900900'])->save();

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Remind',
            'last_name' => 'Me',
            'email' => 'remind@example.test',
            'phone' => '+447700900803',
            'is_active' => true,
        ]);

        $startsAt = now()->addHours(72);
        $appointment = Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $client->id,
            'team_member_id' => $ctx['teamMember']->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'status' => Appointment::STATUS_CONFIRMED,
            'booking_source' => Appointment::SOURCE_NEXT_VISIT,
            'deposit_status' => Appointment::DEPOSIT_NOT_REQUIRED,
            'deposit_required_cents' => 0,
            'billing_settlement_status' => 'unsettled',
            'booking_reference' => 'NV-TEST-72',
        ]);

        $result = app(NextVisitReminderService::class)->dispatchForCurrentTenant(45);

        $this->assertSame(1, $result['sent_72h']);
        $appointment->refresh();
        $this->assertNotNull($appointment->next_visit_reminded_72h_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'next_visit.reminder_sent']);
    }
}
