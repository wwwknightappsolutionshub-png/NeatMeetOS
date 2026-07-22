<?php

namespace App\Domains\Identity\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'timezone',
        'latitude',
        'longitude',
        'geofence_radius_meters',
        'address',
        'contact_email',
        'contact_phone',
        'opening_hours',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'opening_hours' => 'array',
            'is_active' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'geofence_radius_meters' => 'integer',
        ];
    }

    /**
     * Weekly store windows for a day (ISO 1=Mon … 7=Sun).
     * null = hours not configured (no store restriction).
     * [] = configured and closed that day.
     *
     * @return list<array{start_time: string, end_time: string}>|null
     */
    public function openingWindowsForDay(int $dayOfWeekIso): ?array
    {
        $hours = $this->opening_hours;
        if ($hours === null || $hours === []) {
            return null;
        }

        $windows = [];
        $sawDay = false;

        foreach ($hours as $row) {
            if ((int) ($row['day_of_week'] ?? 0) !== $dayOfWeekIso) {
                continue;
            }
            $sawDay = true;
            if (! empty($row['is_closed'])) {
                return [];
            }
            $start = $this->normalizeTime((string) ($row['start_time'] ?? ''));
            $end = $this->normalizeTime((string) ($row['end_time'] ?? ''));
            if ($start !== null && $end !== null) {
                $windows[] = ['start_time' => $start, 'end_time' => $end];
            }
        }

        if (! $sawDay) {
            return [];
        }

        return $windows;
    }

    public function isOpenForInterval(Carbon $startsAt, Carbon $endsAt): bool
    {
        $windows = $this->openingWindowsForDay($startsAt->dayOfWeekIso);
        if ($windows === null) {
            return true;
        }
        if ($windows === []) {
            return false;
        }

        $start = $startsAt->format('H:i:s');
        $end = $endsAt->format('H:i:s');

        foreach ($windows as $window) {
            if ($window['start_time'] <= $start && $window['end_time'] >= $end) {
                return true;
            }
        }

        return false;
    }

    private function normalizeTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            return $value.':00';
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
            return $value;
        }

        return null;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }
}
