<?php

namespace App\Domains\Crm\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Services\AppointmentBookingService;
use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientNotice;
use App\Domains\Crm\Models\ClientVisit;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\TenantOwnerNotice;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\TenantEntitlementService;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class NextVisitSchedulingService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantEntitlementService $entitlements,
        private readonly AppointmentBookingService $booking,
        private readonly ClientNoticeService $clientNotices,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function assertFeatureEnabled(?Tenant $tenant = null): void
    {
        $tenant ??= $this->tenantContext->get();
        if (! $this->entitlements->isEnabled($tenant, 'next_visit')) {
            throw ValidationException::withMessages([
                'next_visit' => ['Next visit planning is not enabled for this salon.'],
            ]);
        }
    }

    /**
     * Schedule a real Booking appointment from a member check-in prompt.
     *
     * @param  array{
     *     starts_at: string,
     *     team_member_id: string,
     *     location_id: string,
     *     workspace_id?: string|null,
     *     services: list<array{booking_service_id: string, quantity?: int|float}>,
     *     client_notes?: string|null
     * }  $data
     */
    public function scheduleFromCheckIn(Client $client, string $visitId, array $data): Appointment
    {
        $this->assertFeatureEnabled();
        $this->assertTenantClient($client);

        $visit = ClientVisit::query()
            ->where('client_id', $client->id)
            ->findOrFail($visitId);

        if ($visit->next_visit_appointment_id) {
            throw ValidationException::withMessages([
                'visit_id' => ['This visit already has a next visit booked.'],
            ]);
        }

        return DB::transaction(function () use ($client, $visit, $data) {
            $appointment = $this->booking->create([
                'client_id' => $client->id,
                'team_member_id' => $data['team_member_id'],
                'location_id' => $data['location_id'],
                'workspace_id' => $data['workspace_id'] ?? null,
                'starts_at' => $data['starts_at'],
                'services' => $data['services'],
                'status' => Appointment::STATUS_CONFIRMED,
                'booking_source' => Appointment::SOURCE_NEXT_VISIT,
                'client_notes' => $data['client_notes'] ?? null,
            ]);

            $appointment->forceFill([
                'origin_visit_id' => $visit->id,
            ])->save();

            $visit->forceFill([
                'next_visit_appointment_id' => $appointment->id,
                'next_visit_prompted_at' => now(),
            ])->save();

            $startsLabel = $appointment->starts_at?->timezone(
                $this->tenantContext->get()?->timezone ?? config('app.timezone')
            )->format('D j M Y H:i');

            $this->clientNotices->createForClient($client, [
                'type' => ClientNotice::TYPE_OPERATIONAL_IN_APP,
                'title' => 'Next visit booked',
                'body' => 'Your next visit is confirmed for '.$startsLabel.'.',
                'href' => '/member',
                'data' => [
                    'appointment_id' => $appointment->id,
                    'origin_visit_id' => $visit->id,
                    'source' => 'next_visit',
                ],
            ]);

            $this->notifyOwnersOfSchedule($client, $appointment);

            if (filled($client->email)) {
                try {
                    $salon = $this->tenantContext->get()?->trading_name
                        ?: $this->tenantContext->get()?->name
                        ?: 'your salon';
                    Mail::html(
                        '<p>Hi '.e($client->first_name ?: 'there').',</p>'
                        .'<p>Your next visit at <strong>'.e($salon).'</strong> is booked for <strong>'.e((string) $startsLabel).'</strong>.</p>',
                        function ($message) use ($client, $salon) {
                            $message->to($client->email)->subject('Next visit confirmed — '.$salon);
                        }
                    );
                } catch (\Throwable) {
                    // Email delivery must not block booking.
                }
            }

            $this->auditLogger->log('next_visit.scheduled', $appointment, null, [
                'client_id' => $client->id,
                'origin_visit_id' => $visit->id,
                'booking_source' => Appointment::SOURCE_NEXT_VISIT,
            ]);

            return $appointment->fresh()->load(['client', 'teamMember', 'location', 'serviceLines']);
        });
    }

    public function listUpcomingForTenant(): Collection
    {
        $this->assertFeatureEnabled();

        return Appointment::query()
            ->with(['client', 'teamMember', 'location', 'serviceLines'])
            ->where('booking_source', Appointment::SOURCE_NEXT_VISIT)
            ->where('starts_at', '>=', now())
            ->whereNotIn('status', [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW])
            ->orderBy('starts_at')
            ->limit(200)
            ->get();
    }

    public function listForClient(Client $client): Collection
    {
        $this->assertTenantClient($client);

        return Appointment::query()
            ->with(['teamMember', 'location', 'serviceLines'])
            ->where('client_id', $client->id)
            ->where('booking_source', Appointment::SOURCE_NEXT_VISIT)
            ->whereNotIn('status', [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW])
            ->orderBy('starts_at')
            ->limit(50)
            ->get();
    }

    public function shouldPromptNextVisit(ClientVisit $visit, ?Tenant $tenant = null): bool
    {
        $tenant ??= $this->tenantContext->get();

        return $this->entitlements->isEnabled($tenant, 'next_visit')
            && $visit->next_visit_appointment_id === null;
    }

    private function notifyOwnersOfSchedule(Client $client, Appointment $appointment): void
    {
        $tenant = $this->tenantContext->get();
        if ($tenant === null) {
            return;
        }

        $owners = TeamMember::query()
            ->where('employment_type', TeamMember::EMPLOYMENT_OWNER)
            ->where('is_active', true)
            ->whereNotNull('user_id')
            ->get();

        $clientName = trim(($client->first_name ?? '').' '.($client->last_name ?? '')) ?: 'A client';
        $starts = $appointment->starts_at?->toIso8601String();

        foreach ($owners as $owner) {
            TenantOwnerNotice::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $owner->user_id,
                'type' => 'next_visit.scheduled',
                'title' => 'Next visit booked',
                'body' => $clientName.' booked a next visit for '.$starts.'.',
                'href' => '/admin/booking',
                'data' => [
                    'appointment_id' => $appointment->id,
                    'client_id' => $client->id,
                ],
            ]);

            $user = User::query()->find($owner->user_id);
            if ($user && filled($user->email)) {
                try {
                    Mail::raw(
                        $clientName.' booked a next visit for '.$starts.'.',
                        function ($message) use ($user) {
                            $message->to($user->email)->subject('Next visit booked');
                        }
                    );
                } catch (\Throwable) {
                    // ignore
                }
            }
        }
    }

    private function assertTenantClient(Client $client): void
    {
        if ($client->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['client' => ['Client not found.']]);
        }
    }
}
