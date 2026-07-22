<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\AppointmentRecurrenceSeries;
use App\Shared\Audit\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecurrenceSeriesService
{
    public function __construct(
        private readonly BookingScopeValidator $scope,
        private readonly AppointmentBookingService $bookingService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function find(string $id): AppointmentRecurrenceSeries
    {
        $series = AppointmentRecurrenceSeries::query()
            ->with(['client', 'teamMember', 'location', 'workspace', 'appointments.serviceLines'])
            ->findOrFail($id);

        $this->scope->assertTenantModel($series);

        return $series;
    }

    /**
     * Creates valid occurrences only; skips conflicts and reports them.
     *
     * @return array{series: AppointmentRecurrenceSeries, created: array<int, string>, skipped: array<int, array{starts_at: string, reason: string}>}
     */
    public function create(array $data, ?string $createdByTeamMemberId = null): array
    {
        if (empty($data['occurrence_count']) && empty($data['end_date'])) {
            throw ValidationException::withMessages([
                'occurrence_count' => ['Either occurrence count or end date is required.'],
            ]);
        }

        $this->scope->findClient($data['client_id']);
        $this->scope->findTeamMember($data['team_member_id']);
        $this->scope->findLocation($data['location_id']);
        $this->scope->findWorkspace($data['workspace_id'] ?? null);

        $anchor = Carbon::parse($data['starts_at']);
        $serviceTemplate = collect($data['services'])->map(fn ($s) => [
            'booking_service_id' => $s['booking_service_id'],
            'sort_order' => $s['sort_order'] ?? 0,
        ])->all();

        return DB::transaction(function () use ($data, $anchor, $serviceTemplate, $createdByTeamMemberId) {
            $series = AppointmentRecurrenceSeries::query()->create([
                'tenant_id' => $this->scope->tenantId(),
                'pattern' => AppointmentRecurrenceSeries::PATTERN_WEEKLY,
                'interval_weeks' => $data['interval_weeks'] ?? 1,
                'anchor_starts_at' => $anchor,
                'end_date' => $data['end_date'] ?? null,
                'occurrence_count' => $data['occurrence_count'] ?? null,
                'status' => AppointmentRecurrenceSeries::STATUS_ACTIVE,
                'client_id' => $data['client_id'],
                'team_member_id' => $data['team_member_id'],
                'location_id' => $data['location_id'],
                'workspace_id' => $data['workspace_id'] ?? null,
                'service_template' => $serviceTemplate,
                'client_notes' => $data['client_notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'created_by_team_member_id' => $createdByTeamMemberId,
            ]);

            $this->auditLogger->log('recurrence_series.created', $series, null, [
                'occurrence_count' => $series->occurrence_count,
                'end_date' => $series->end_date?->toDateString(),
            ]);

            $slots = $this->buildOccurrenceSlots($series, $anchor);
            $created = [];
            $skipped = [];
            $index = 0;

            foreach ($slots as $slot) {
                try {
                    $appointment = $this->bookingService->create([
                        'client_id' => $series->client_id,
                        'team_member_id' => $series->team_member_id,
                        'location_id' => $series->location_id,
                        'workspace_id' => $series->workspace_id,
                        'starts_at' => $slot->toDateTimeString(),
                        'status' => $data['status'] ?? Appointment::STATUS_CONFIRMED,
                        'booking_source' => Appointment::SOURCE_ADMIN,
                        'client_notes' => $series->client_notes,
                        'internal_notes' => $series->internal_notes,
                        'services' => $serviceTemplate,
                    ], $createdByTeamMemberId, [
                        'recurrence_series_id' => $series->id,
                        'occurrence_index' => $index,
                    ]);

                    $created[] = $appointment->id;
                } catch (ValidationException $e) {
                    $reason = collect($e->errors())->flatten()->first() ?? 'Scheduling conflict';
                    $skipped[] = [
                        'starts_at' => $slot->toIso8601String(),
                        'reason' => $reason,
                    ];
                }

                $index++;
            }

            return [
                'series' => $series->fresh()->load(['client', 'teamMember', 'location', 'appointments']),
                'created' => $created,
                'skipped' => $skipped,
            ];
        });
    }

    public function cancelFuture(AppointmentRecurrenceSeries $series): AppointmentRecurrenceSeries
    {
        $this->scope->assertTenantModel($series);

        $series->status = AppointmentRecurrenceSeries::STATUS_CANCELLED;
        $series->save();

        Appointment::query()
            ->where('recurrence_series_id', $series->id)
            ->where('starts_at', '>', now())
            ->whereNotIn('status', [Appointment::STATUS_CANCELLED, Appointment::STATUS_COMPLETED])
            ->each(function (Appointment $appointment) {
                $this->bookingService->cancel($appointment, 'Recurrence series cancelled');
            });

        $this->auditLogger->log('recurrence_series.cancelled', $series);

        return $series->fresh()->load('appointments');
    }

    /**
     * @return array<int, Carbon>
     */
    private function buildOccurrenceSlots(AppointmentRecurrenceSeries $series, Carbon $anchor): array
    {
        $slots = [];
        $current = $anchor->copy();
        $max = $series->occurrence_count ?? 52;
        $endDate = $series->end_date?->endOfDay();

        for ($i = 0; $i < $max; $i++) {
            if ($endDate !== null && $current->gt($endDate)) {
                break;
            }

            $slots[] = $current->copy();

            if ($series->occurrence_count !== null && count($slots) >= $series->occurrence_count) {
                break;
            }

            $current->addWeeks($series->interval_weeks);
        }

        return $slots;
    }
}
