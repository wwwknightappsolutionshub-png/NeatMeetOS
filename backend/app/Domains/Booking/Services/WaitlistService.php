<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\WaitlistEntry;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WaitlistService
{
    public function __construct(
        private readonly BookingScopeValidator $scope,
        private readonly BookableServiceCatalogService $catalogService,
        private readonly AppointmentBookingService $bookingService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = WaitlistEntry::query()
            ->with(['client', 'location', 'teamMember', 'workspace', 'bookableServices', 'fulfilledAppointment'])
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }

        if (! empty($filters['team_member_id'])) {
            $query->where('team_member_id', $filters['team_member_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('preferred_starts_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('preferred_starts_at', '<=', $filters['to']);
        }

        if (! empty($filters['booking_service_id'])) {
            $query->whereHas('bookableServices', fn ($q) => $q->where('booking_services.id', $filters['booking_service_id']));
        }

        return $query->get();
    }

    public function find(string $id): WaitlistEntry
    {
        $entry = WaitlistEntry::query()
            ->with(['client', 'location', 'teamMember', 'workspace', 'bookableServices', 'fulfilledAppointment'])
            ->findOrFail($id);

        $this->scope->assertTenantModel($entry);

        return $entry;
    }

    public function create(array $data, ?string $createdByTeamMemberId = null): WaitlistEntry
    {
        $this->scope->findClient($data['client_id']);
        $this->scope->findLocation($data['location_id']);

        if (! empty($data['team_member_id'])) {
            $this->scope->findTeamMember($data['team_member_id']);
        }

        $this->scope->findWorkspace($data['workspace_id'] ?? null);

        $services = $data['services'] ?? [];
        $resolved = $services !== [] ? $this->catalogService->resolveServiceLines($services) : null;

        return DB::transaction(function () use ($data, $createdByTeamMemberId, $services, $resolved) {
            $entry = WaitlistEntry::query()->create([
                'tenant_id' => $this->scope->tenantId(),
                'client_id' => $data['client_id'],
                'location_id' => $data['location_id'],
                'team_member_id' => $data['team_member_id'] ?? null,
                'workspace_id' => $data['workspace_id'] ?? null,
                'workspace_type_preference' => $data['workspace_type_preference'] ?? null,
                'preferred_starts_at' => $data['preferred_starts_at'] ?? null,
                'preferred_ends_at' => $data['preferred_ends_at'] ?? null,
                'availability_notes' => $data['availability_notes'] ?? null,
                'status' => WaitlistEntry::STATUS_WAITING,
                'notes' => $data['notes'] ?? null,
                'created_by_team_member_id' => $createdByTeamMemberId,
            ]);

            if ($resolved !== null) {
                foreach ($resolved['lines'] as $line) {
                    $entry->bookableServices()->attach($line['booking_service_id'], [
                        'service_name' => $line['service_name'],
                        'sort_order' => $line['sort_order'],
                    ]);
                }
            }

            $this->auditLogger->log('waitlist_entry.created', $entry, null, $entry->toArray());

            return $entry->load(['client', 'location', 'teamMember', 'bookableServices']);
        });
    }

    public function update(WaitlistEntry $entry, array $data): WaitlistEntry
    {
        $this->scope->assertTenantModel($entry);

        if (in_array($entry->status, [WaitlistEntry::STATUS_BOOKED, WaitlistEntry::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages([
                'waitlist' => ['This waitlist entry cannot be updated.'],
            ]);
        }

        $old = $entry->toArray();
        $entry->fill(collect($data)->only([
            'status',
            'notes',
            'availability_notes',
            'preferred_starts_at',
            'preferred_ends_at',
            'team_member_id',
        ])->all());

        if (isset($data['status']) && $data['status'] === WaitlistEntry::STATUS_CONTACTED && $entry->contacted_at === null) {
            $entry->contacted_at = now();
        }

        $entry->save();

        $this->auditLogger->log('waitlist_entry.updated', $entry, $old, $entry->toArray());

        return $entry->fresh()->load(['client', 'location', 'teamMember', 'bookableServices']);
    }

    public function fulfill(WaitlistEntry $entry, array $appointmentData, ?string $createdByTeamMemberId = null): array
    {
        $this->scope->assertTenantModel($entry);

        if ($entry->status === WaitlistEntry::STATUS_BOOKED) {
            throw ValidationException::withMessages([
                'waitlist' => ['Waitlist entry is already fulfilled.'],
            ]);
        }

        $services = $appointmentData['services'] ?? $entry->bookableServices->map(fn ($s) => [
            'booking_service_id' => $s->id,
            'sort_order' => $s->pivot->sort_order,
        ])->all();

        if ($services === []) {
            throw ValidationException::withMessages([
                'services' => ['At least one service is required to fulfil waitlist.'],
            ]);
        }

        $appointment = $this->bookingService->create([
            'client_id' => $entry->client_id,
            'team_member_id' => $appointmentData['team_member_id'] ?? $entry->team_member_id,
            'location_id' => $appointmentData['location_id'] ?? $entry->location_id,
            'workspace_id' => $appointmentData['workspace_id'] ?? $entry->workspace_id,
            'starts_at' => $appointmentData['starts_at'],
            'client_notes' => $appointmentData['client_notes'] ?? null,
            'internal_notes' => $appointmentData['internal_notes'] ?? $entry->notes,
            'booking_source' => Appointment::SOURCE_WAITLIST,
            'services' => $services,
        ], $createdByTeamMemberId);

        $entry->status = WaitlistEntry::STATUS_BOOKED;
        $entry->fulfilled_appointment_id = $appointment->id;
        $entry->save();

        $this->auditLogger->log('waitlist_entry.fulfilled', $entry, null, [
            'appointment_id' => $appointment->id,
        ]);

        return [
            'waitlist' => $entry->fresh()->load(['fulfilledAppointment']),
            'appointment' => $appointment,
        ];
    }
}
