<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\Location;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LocationService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(): Collection
    {
        return Location::query()->orderBy('name')->get();
    }

    public function find(string $id): Location
    {
        return Location::query()->findOrFail($id);
    }

    public function create(array $data): Location
    {
        $tenantId = $this->tenantContext->id();

        if ($tenantId === null) {
            throw ValidationException::withMessages(['tenant' => ['Tenant context is required.']]);
        }

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $data['tenant_id'] = $tenantId;
        $data['is_active'] = $data['is_active'] ?? true;

        $location = Location::query()->create($data);

        $this->auditLogger->log('location.created', $location, null, $location->toArray());

        return $location;
    }

    public function update(Location $location, array $data): Location
    {
        $old = $location->toArray();
        $location->fill($data);
        $location->save();

        $this->auditLogger->log('location.updated', $location, $old, $location->toArray());

        return $location->fresh();
    }

    public function setActive(Location $location, bool $isActive): Location
    {
        $old = ['is_active' => $location->is_active];
        $location->is_active = $isActive;
        $location->save();

        $action = $isActive ? 'location.activated' : 'location.deactivated';
        $this->auditLogger->log($action, $location, $old, ['is_active' => $isActive]);

        return $location->fresh();
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $hours
     * @return array<int, array{day_of_week: int, start_time: string|null, end_time: string|null, is_closed: bool}>|null
     */
    public function normalizeOpeningHours(?array $hours): ?array
    {
        if ($hours === null) {
            return null;
        }

        $normalized = [];

        foreach (array_values($hours) as $index => $row) {
            $day = (int) ($row['day_of_week'] ?? 0);
            if ($day < 1 || $day > 7) {
                throw ValidationException::withMessages([
                    "opening_hours.{$index}.day_of_week" => ['Day of week must be 1 (Mon) through 7 (Sun).'],
                ]);
            }

            $closed = (bool) ($row['is_closed'] ?? false);
            if ($closed) {
                $normalized[] = [
                    'day_of_week' => $day,
                    'start_time' => null,
                    'end_time' => null,
                    'is_closed' => true,
                ];

                continue;
            }

            $start = $this->normalizeClock((string) ($row['start_time'] ?? ''));
            $end = $this->normalizeClock((string) ($row['end_time'] ?? ''));

            if ($start === null || $end === null) {
                throw ValidationException::withMessages([
                    "opening_hours.{$index}" => ['Open days require start_time and end_time (HH:MM).'],
                ]);
            }

            if ($end <= $start) {
                throw ValidationException::withMessages([
                    "opening_hours.{$index}.end_time" => ['End time must be after start time.'],
                ]);
            }

            $normalized[] = [
                'day_of_week' => $day,
                'start_time' => substr($start, 0, 5),
                'end_time' => substr($end, 0, 5),
                'is_closed' => false,
            ];
        }

        return $normalized;
    }

    private function normalizeClock(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            return $value.':00';
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
            return $value;
        }

        return null;
    }
}
