<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\BookableService;
use App\Domains\Booking\Models\BookingChangeRequest;
use App\Domains\Booking\Models\BookingPolicySetting;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\User;
use App\Domains\Staff\Models\StaffAvailabilityRule;
use App\Domains\Staff\Models\StaffProfile;
use App\Shared\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class BookingPolicyChangeRequestTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function seedContext(): array
    {
        $ctx = $this->seedTenantContext([
            'booking.view',
            'booking.manage',
            'crm.view',
            'crm.manage',
            'staff.view',
            'staff.manage',
        ]);

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
                'start_time' => '09:00',
                'end_time' => '17:00',
                'is_active' => true,
            ]);
        }

        $service = BookableService::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Policy Cut',
            'duration_minutes' => 60,
            'is_active' => true,
            'is_bookable_online' => true,
            'deposit_required' => true,
            'deposit_amount_cents' => 2000,
        ]);

        return array_merge($ctx, compact('service'));
    }

    public function test_platform_admin_can_update_tenant_booking_policy(): void
    {
        $ctx = $this->seedContext();
        $admin = User::factory()->create([
            'name' => 'Owner',
            'email' => 'owner-policy@example.com',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
            'platform_role' => 'owner',
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/platform/tenants/'.$ctx['tenant']->id.'/booking-policy')
            ->assertOk()
            ->assertJsonPath('data.min_advance_notice_minutes', 30);

        $this->putJson('/api/v1/platform/tenants/'.$ctx['tenant']->id.'/booking-policy', [
            'min_advance_notice_minutes' => 45,
            'free_change_window_minutes' => 20,
            'late_cancel_fee_percent' => 50,
            'approval_reminder_max_count' => 3,
        ])
            ->assertOk()
            ->assertJsonPath('data.min_advance_notice_minutes', 45)
            ->assertJsonPath('data.free_change_window_minutes', 20);

        $this->assertDatabaseHas('booking_policy_settings', [
            'tenant_id' => $ctx['tenant']->id,
            'min_advance_notice_minutes' => 45,
        ]);
    }

    public function test_advance_notice_filters_slots_and_blocks_book(): void
    {
        $ctx = $this->seedContext();
        BookingPolicySetting::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'min_advance_notice_minutes' => 120,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-19 10:00:00'));

        $today = Carbon::parse('2026-08-19')->toDateString();
        $slots = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/book/slots?'.http_build_query([
                'booking_service_id' => $ctx['service']->id,
                'location_id' => $ctx['location']->id,
                'date' => $today,
                'team_member_id' => $ctx['teamMember']->id,
            ]))
            ->assertOk()
            ->json('data.slots');

        foreach ($slots as $slot) {
            $this->assertTrue(
                Carbon::parse($slot['starts_at'])->gte(now()->addMinutes(120)),
                'Slot too soon: '.$slot['starts_at'],
            );
        }

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/book/appointments', [
                'booking_service_id' => $ctx['service']->id,
                'location_id' => $ctx['location']->id,
                'team_member_id' => $ctx['teamMember']->id,
                'starts_at' => now()->addMinutes(30)->toIso8601String(),
                'first_name' => 'Too',
                'last_name' => 'Soon',
                'phone' => '+447700900999',
            ])
            ->assertStatus(422);

        Carbon::setTestNow();
    }

    public function test_free_cancel_cannot_be_declined_and_late_cancel_applies_fee(): void
    {
        $ctx = $this->seedContext();
        app(TenantContext::class)->set($ctx['tenant']);

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Free',
            'last_name' => 'Cancel',
            'phone' => '+447700900111',
            'phone_normalized' => '447700900111',
            'is_active' => true,
            'primary_location_id' => $ctx['location']->id,
        ]);

        $appointment = Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $client->id,
            'team_member_id' => $ctx['teamMember']->id,
            'workspace_id' => $ctx['workspace']->id,
            'starts_at' => now()->addHours(3),
            'ends_at' => now()->addHours(4),
            'status' => Appointment::STATUS_CONFIRMED,
            'booking_source' => Appointment::SOURCE_ONLINE,
            'booking_reference' => 'NM-TESTFREE1',
            'public_manage_token' => Str::lower(Str::random(40)),
            'deposit_status' => Appointment::DEPOSIT_PENDING,
            'deposit_required_cents' => 2000,
        ]);

        $created = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/book/appointments/'.$appointment->booking_reference.'/cancel', [
                'token' => $appointment->public_manage_token,
            ])
            ->assertCreated()
            ->assertJsonPath('data.decline_allowed', false)
            ->assertJsonPath('data.late_fee_applies', false);

        $requestId = $created->json('data.id');
        $token = $created->json('data.action_links')['manage_url'] ?? null;
        $this->assertNotNull($requestId);

        $change = BookingChangeRequest::withoutGlobalScopes()->findOrFail($requestId);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/book/change-requests/resolve', [
                'id' => $change->id,
                'token' => $change->action_token,
                'action' => 'decline',
            ])
            ->assertStatus(422);

        $late = Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $client->id,
            'team_member_id' => $ctx['teamMember']->id,
            'workspace_id' => $ctx['workspace']->id,
            'starts_at' => now()->addMinutes(10),
            'ends_at' => now()->addMinutes(70),
            'status' => Appointment::STATUS_CONFIRMED,
            'booking_source' => Appointment::SOURCE_ONLINE,
            'booking_reference' => 'NM-TESTLATE1',
            'public_manage_token' => Str::lower(Str::random(40)),
            'deposit_status' => Appointment::DEPOSIT_PENDING,
            'deposit_required_cents' => 2000,
            'deposit_rule_snapshot' => ['deposit_amount_cents' => 2000],
        ]);

        $lateReq = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/book/appointments/'.$late->booking_reference.'/cancel', [
                'token' => $late->public_manage_token,
            ])
            ->assertCreated()
            ->assertJsonPath('data.decline_allowed', true)
            ->assertJsonPath('data.late_fee_applies', true)
            ->assertJsonPath('data.late_fee_cents', 1000);

        $lateChange = BookingChangeRequest::withoutGlobalScopes()->findOrFail($lateReq->json('data.id'));

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/book/change-requests/resolve', [
                'id' => $lateChange->id,
                'token' => $lateChange->action_token,
                'action' => 'accept',
            ])
            ->assertOk();

        $late->refresh();
        $this->assertSame(Appointment::STATUS_CANCELLED, $late->status);
        $this->assertSame(1000, $late->deposit_rule_snapshot['late_cancel_fee_cents'] ?? null);
    }

    public function test_approval_reminders_auto_accept_after_max(): void
    {
        $ctx = $this->seedContext();
        app(TenantContext::class)->set($ctx['tenant']);

        BookingPolicySetting::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $ctx['tenant']->id],
            [
                'approval_reminder_interval_minutes' => 2,
                'approval_reminder_max_count' => 2,
            ],
        );

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Auto',
            'last_name' => 'Accept',
            'phone' => '+447700900222',
            'phone_normalized' => '447700900222',
            'is_active' => true,
            'primary_location_id' => $ctx['location']->id,
        ]);

        $appointment = Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $client->id,
            'team_member_id' => $ctx['teamMember']->id,
            'workspace_id' => $ctx['workspace']->id,
            'starts_at' => now()->addHours(5),
            'ends_at' => now()->addHours(6),
            'status' => Appointment::STATUS_CONFIRMED,
            'booking_source' => Appointment::SOURCE_ONLINE,
            'booking_reference' => 'NM-TESTAUTO1',
            'public_manage_token' => Str::lower(Str::random(40)),
        ]);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/book/appointments/'.$appointment->booking_reference.'/cancel', [
                'token' => $appointment->public_manage_token,
            ])
            ->assertCreated();

        $change = BookingChangeRequest::withoutGlobalScopes()
            ->where('appointment_id', $appointment->id)
            ->firstOrFail();

        $change->last_reminded_at = now()->subMinutes(5);
        $change->save();

        Artisan::call('booking:dispatch-policy-reminders');
        $change->refresh();
        $this->assertSame(1, (int) $change->reminder_count);
        $this->assertSame(BookingChangeRequest::STATUS_PENDING, $change->status);

        $change->last_reminded_at = now()->subMinutes(5);
        $change->save();

        Artisan::call('booking:dispatch-policy-reminders');
        $change->refresh();
        $this->assertSame(BookingChangeRequest::STATUS_AUTO_ACCEPTED, $change->status);
        $this->assertSame(Appointment::STATUS_CANCELLED, $appointment->fresh()->status);
    }
}
