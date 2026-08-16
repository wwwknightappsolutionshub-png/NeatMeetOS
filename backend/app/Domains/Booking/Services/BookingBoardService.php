<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Identity\Models\Workspace;
use Carbon\Carbon;

class BookingBoardService
{
    public function __construct(private readonly BookingScopeValidator $scope) {}

    public function dayBoard(array $filters): array
    {
        $tenantTz = $this->scope->tenantTimezone();
        $date = Carbon::parse($filters['date'] ?? now($tenantTz)->toDateString(), $tenantTz)->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        // Compare in UTC-equivalent instants so local salon days match stored timestamps.
        $fromUtc = $date->copy()->utc();
        $toUtc = $endOfDay->copy()->utc();

        $query = Appointment::query()
            ->with(['client', 'teamMember', 'location', 'workspace', 'serviceLines'])
            ->where('starts_at', '<=', $toUtc)
            ->where('ends_at', '>=', $fromUtc)
            ->orderBy('starts_at');

        if (! empty($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }

        if (! empty($filters['team_member_id'])) {
            $query->where('team_member_id', $filters['team_member_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['booking_source'])) {
            $query->where('booking_source', $filters['booking_source']);
        }

        $appointments = $query->get();

        $workspaceOccupancy = $this->buildWorkspaceOccupancy($appointments, $date, $filters['location_id'] ?? null);

        return [
            'date' => $date->toDateString(),
            'appointments' => $appointments,
            'workspace_occupancy' => $workspaceOccupancy,
            'summary' => [
                'total' => $appointments->count(),
                'by_status' => $appointments->groupBy('status')->map->count(),
                'walk_ins_waiting' => $appointments->where('walk_in_stage', Appointment::WALK_IN_WAITING)->count(),
            ],
        ];
    }

    /**
     * @return list<array{workspace_id: string, workspace_name: string, workspace_type: string, appointments: int}>
     */
    private function buildWorkspaceOccupancy(
        \Illuminate\Support\Collection $appointments,
        Carbon $date,
        ?string $locationId,
    ): array {
        $active = $appointments->filter(fn (Appointment $a) => $a->isBlockingSchedule() && $a->workspace_id !== null);

        $workspaceIds = $active->pluck('workspace_id')->unique()->filter()->values();

        if ($workspaceIds->isEmpty()) {
            return [];
        }

        $workspaces = Workspace::query()
            ->whereIn('id', $workspaceIds)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->get()
            ->keyBy('id');

        return $workspaceIds->map(function (string $id) use ($active, $workspaces) {
            $workspace = $workspaces->get($id);

            return [
                'workspace_id' => $id,
                'workspace_name' => $workspace?->name ?? 'Unknown',
                'workspace_type' => $workspace?->workspace_type ?? 'unknown',
                'appointments' => $active->where('workspace_id', $id)->count(),
            ];
        })->values()->all();
    }
}
