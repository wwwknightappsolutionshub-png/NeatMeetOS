<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\DTOs\DateRange;
use App\Domains\Analytics\Services\Concerns\BuildsDailySeries;
use App\Domains\Booking\Models\Appointment;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read-only booking analytics derived from the appointments and
 * appointment_services tables. All activity metrics are anchored on
 * `appointments.starts_at`.
 */
class BookingAnalyticsService
{
    use BuildsDailySeries;

    /**
     * @return array<string, mixed>
     */
    public function report(string $tenantId, DateRange $range, ?string $locationId = null, ?string $providerId = null): array
    {
        return [
            'range' => $range->toArray(),
            'summary' => $this->summary($tenantId, $range, $locationId, $providerId),
            'daily' => $this->daily($tenantId, $range, $locationId, $providerId),
            'providers' => $this->providers($tenantId, $range, $locationId, $providerId),
            'services' => $this->services($tenantId, $range, $locationId, $providerId),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function summary(string $tenantId, DateRange $range, ?string $locationId = null, ?string $providerId = null): array
    {
        $counts = $this->baseQuery($tenantId, $range, $locationId, $providerId)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = (int) $counts->sum();
        $completed = (int) ($counts[Appointment::STATUS_COMPLETED] ?? 0);
        $cancelled = (int) ($counts[Appointment::STATUS_CANCELLED] ?? 0);
        $noShow = (int) ($counts[Appointment::STATUS_NO_SHOW] ?? 0);
        $checkedIn = (int) ($counts[Appointment::STATUS_CHECKED_IN] ?? 0);
        $confirmed = (int) ($counts[Appointment::STATUS_CONFIRMED] ?? 0);
        $pending = (int) ($counts[Appointment::STATUS_PENDING] ?? 0);

        $walkIn = (int) $this->baseQuery($tenantId, $range, $locationId, $providerId)
            ->where('booking_source', Appointment::SOURCE_WALK_IN)
            ->count();

        return [
            'total_appointments' => $total,
            'completed_appointments' => $completed,
            'cancelled_appointments' => $cancelled,
            'no_show_appointments' => $noShow,
            'checked_in_appointments' => $checkedIn,
            'confirmed_appointments' => $confirmed,
            'pending_appointments' => $pending,
            'walk_in_appointments' => $walkIn,
            'average_booking_value_cents' => $this->averageBookingValueCents($tenantId, $range, $locationId, $providerId),
            'cancellation_rate' => $total > 0 ? round($cancelled / $total, 4) : 0.0,
            'no_show_rate' => $total > 0 ? round($noShow / $total, 4) : 0.0,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function daily(string $tenantId, DateRange $range, ?string $locationId, ?string $providerId): array
    {
        $rows = $this->baseQuery($tenantId, $range, $locationId, $providerId)
            ->get(['starts_at', 'status']);

        return $this->dailySeries(
            $range,
            $rows,
            'starts_at',
            fn ($row) => [
                'total' => 1,
                'completed' => $row->status === Appointment::STATUS_COMPLETED ? 1 : 0,
            ],
            ['total' => 0, 'completed' => 0],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function providers(string $tenantId, DateRange $range, ?string $locationId, ?string $providerId): array
    {
        $query = DB::table('appointments as a')
            ->leftJoin('team_members as t', 't.id', '=', 'a.team_member_id')
            ->where('a.tenant_id', $tenantId)
            ->whereBetween('a.starts_at', [$range->from, $range->to]);

        if ($locationId !== null) {
            $query->where('a.location_id', $locationId);
        }
        if ($providerId !== null) {
            $query->where('a.team_member_id', $providerId);
        }

        return $query
            ->selectRaw('a.team_member_id as provider_id, t.display_name as provider_name, COUNT(*) as total_appointments')
            ->selectRaw("SUM(CASE WHEN a.status = ? THEN 1 ELSE 0 END) as completed_appointments", [Appointment::STATUS_COMPLETED])
            ->selectRaw("SUM(CASE WHEN a.status = ? THEN 1 ELSE 0 END) as no_show_appointments", [Appointment::STATUS_NO_SHOW])
            ->groupBy('a.team_member_id', 't.display_name')
            ->orderByDesc('total_appointments')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'provider_id' => $row->provider_id,
                'provider_name' => $row->provider_name,
                'total_appointments' => (int) $row->total_appointments,
                'completed_appointments' => (int) $row->completed_appointments,
                'no_show_appointments' => (int) $row->no_show_appointments,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function services(string $tenantId, DateRange $range, ?string $locationId, ?string $providerId): array
    {
        $query = DB::table('appointment_services as s')
            ->join('appointments as a', 'a.id', '=', 's.appointment_id')
            ->where('s.tenant_id', $tenantId)
            ->whereBetween('a.starts_at', [$range->from, $range->to]);

        if ($locationId !== null) {
            $query->where('a.location_id', $locationId);
        }
        if ($providerId !== null) {
            $query->where('a.team_member_id', $providerId);
        }

        return $query
            ->selectRaw('s.service_name as service_name, COUNT(*) as bookings, COALESCE(SUM(s.price_cents), 0) as revenue_cents')
            ->groupBy('s.service_name')
            ->orderByDesc('bookings')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'service_name' => $row->service_name,
                'bookings' => (int) $row->bookings,
                'revenue_cents' => (int) $row->revenue_cents,
            ])
            ->all();
    }

    private function averageBookingValueCents(string $tenantId, DateRange $range, ?string $locationId, ?string $providerId): int
    {
        $query = DB::table('appointment_services as s')
            ->join('appointments as a', 'a.id', '=', 's.appointment_id')
            ->where('s.tenant_id', $tenantId)
            ->whereBetween('a.starts_at', [$range->from, $range->to]);

        if ($locationId !== null) {
            $query->where('a.location_id', $locationId);
        }
        if ($providerId !== null) {
            $query->where('a.team_member_id', $providerId);
        }

        $row = $query
            ->selectRaw('COALESCE(SUM(s.price_cents), 0) as total_cents, COUNT(DISTINCT s.appointment_id) as appointment_count')
            ->first();

        $appointments = (int) ($row->appointment_count ?? 0);

        return $appointments > 0 ? intdiv((int) $row->total_cents, $appointments) : 0;
    }

    private function baseQuery(string $tenantId, DateRange $range, ?string $locationId, ?string $providerId): Builder
    {
        $query = DB::table('appointments')
            ->where('tenant_id', $tenantId)
            ->whereBetween('starts_at', [$range->from, $range->to]);

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }
        if ($providerId !== null) {
            $query->where('team_member_id', $providerId);
        }

        return $query;
    }
}
