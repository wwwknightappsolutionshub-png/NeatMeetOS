<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\BookableService;
use App\Domains\Booking\Models\ReservationPaymentDocument;
use App\Domains\Staff\Models\StaffAvailabilityRule;
use App\Domains\Staff\Models\StaffProfile;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class ReservationPaymentDocumentTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function seedContext(bool $withDeposit = true): array
    {
        $ctx = $this->seedTenantContext([
            'booking.view',
            'booking.manage',
            'crm.view',
            'crm.manage',
            'staff.view',
            'staff.manage',
            'payments.view',
            'payments.manage',
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
            'name' => 'Cut with fee',
            'duration_minutes' => 60,
            'is_active' => true,
            'is_bookable_online' => true,
            'deposit_required' => $withDeposit,
            'deposit_amount_cents' => $withDeposit ? 1500 : null,
        ]);

        $ctx['tenant']->setPaymentsSettings([
            'bank_account_name' => 'Neat Meet Salon',
            'bank_name' => 'Test Bank',
            'bank_sort_code' => '12-34-56',
            'bank_account_number' => '12345678',
            'bank_reference_hint' => 'Use your mobile number',
        ]);
        $ctx['tenant']->save();

        return array_merge($ctx, compact('service'));
    }

    public function test_catalog_exposes_reservation_payment_bank_details(): void
    {
        $ctx = $this->seedContext();

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/book/catalog')
            ->assertOk()
            ->assertJsonPath('data.reservation_payment.transfer_ready', true)
            ->assertJsonPath('data.reservation_payment.bank_details.account_name', 'Neat Meet Salon')
            ->assertJsonPath('data.reservation_payment.min_fee_cents', 1000);
    }

    public function test_booking_with_reservation_fee_requires_proof(): void
    {
        $ctx = $this->seedContext();
        $date = Carbon::now()->next(Carbon::MONDAY)->toDateString();

        $slots = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/book/slots?'.http_build_query([
                'booking_service_id' => $ctx['service']->id,
                'location_id' => $ctx['location']->id,
                'date' => $date,
                'team_member_id' => $ctx['teamMember']->id,
            ]))
            ->assertOk()
            ->json('data.slots');

        $this->assertNotEmpty($slots);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/book/appointments', [
                'booking_service_id' => $ctx['service']->id,
                'location_id' => $ctx['location']->id,
                'team_member_id' => $ctx['teamMember']->id,
                'workspace_id' => $slots[0]['workspace_id'] ?? $ctx['workspace']->id,
                'starts_at' => $slots[0]['starts_at'],
                'phone' => '+447700900999',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reservation_document_id']);
    }

    public function test_transfer_proof_upload_and_book_then_tenant_confirm(): void
    {
        Storage::fake('public');
        $ctx = $this->seedContext();
        $date = Carbon::now()->next(Carbon::MONDAY)->toDateString();

        $slots = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/book/slots?'.http_build_query([
                'booking_service_id' => $ctx['service']->id,
                'location_id' => $ctx['location']->id,
                'date' => $date,
                'team_member_id' => $ctx['teamMember']->id,
            ]))
            ->json('data.slots');

        $file = UploadedFile::fake()->image('proof.jpg', 200, 200);

        $upload = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->post('/api/v1/book/reservation-proof', [
                'booking_service_id' => $ctx['service']->id,
                'payment_method' => 'transfer',
                'proof' => $file,
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data');

        $this->assertNotEmpty($upload['id']);

        $created = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/book/appointments', [
                'booking_service_id' => $ctx['service']->id,
                'location_id' => $ctx['location']->id,
                'team_member_id' => $ctx['teamMember']->id,
                'workspace_id' => $slots[0]['workspace_id'] ?? $ctx['workspace']->id,
                'starts_at' => $slots[0]['starts_at'],
                'phone' => '+447700900888',
                'first_name' => 'Fee',
                'last_name' => 'Guest',
                'reservation_document_id' => $upload['id'],
                'payment_method' => 'transfer',
            ])
            ->assertCreated()
            ->assertJsonPath('data.booking_source', Appointment::SOURCE_ONLINE);

        $this->assertDatabaseHas('booking_reservation_payment_documents', [
            'id' => $upload['id'],
            'appointment_id' => $created->json('data.id'),
            'status' => ReservationPaymentDocument::STATUS_PENDING_REVIEW,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/payments/reservation-documents?status=pending_review')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $upload['id']);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/payments/reservation-documents/'.$upload['id'].'/confirm', [
                'note' => 'Looks good',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ReservationPaymentDocument::STATUS_CONFIRMED);

        $this->assertDatabaseHas('appointments', [
            'id' => $created->json('data.id'),
            'deposit_status' => Appointment::DEPOSIT_SATISFIED,
        ]);
    }

    public function test_tenant_can_update_bank_details(): void
    {
        $ctx = $this->seedContext(false);

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/payments/settings', [
                'bank_account_name' => 'Updated Account',
                'bank_sort_code' => '11-22-33',
                'bank_account_number' => '87654321',
            ])
            ->assertOk()
            ->assertJsonPath('data.bank_account_name', 'Updated Account');
    }

    public function test_reservation_fee_must_be_at_least_ten_pounds(): void
    {
        $ctx = $this->seedContext(false);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/booking-services', [
                'name' => 'Too low fee',
                'duration_minutes' => 30,
                'deposit_required' => true,
                'deposit_amount_cents' => 500,
                'is_bookable_online' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['deposit_amount_cents']);
    }
}
