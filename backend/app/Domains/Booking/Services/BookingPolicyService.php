<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\BookingPolicySetting;
use App\Domains\Identity\Models\Tenant;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingPolicyService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function get(?Tenant $tenant = null): BookingPolicySetting
    {
        $tenantId = $tenant?->id ?? $this->tenantContext->id();

        $settings = BookingPolicySetting::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->first();

        if ($settings !== null) {
            return $settings;
        }

        return BookingPolicySetting::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateForTenant(Tenant $tenant, array $data): BookingPolicySetting
    {
        $settings = $this->get($tenant);

        $fields = array_intersect_key($data, array_flip([
            'min_advance_notice_minutes',
            'free_change_window_minutes',
            'late_cancel_fee_percent',
            'free_window_reminder_lead_minutes',
            'approval_reminder_interval_minutes',
            'approval_reminder_max_count',
        ]));

        return DB::transaction(function () use ($settings, $fields) {
            $old = $settings->only(array_keys($fields));
            $settings->fill($fields);
            $settings->save();

            $this->auditLogger->log(
                'booking_policy_settings.updated',
                $settings,
                $old,
                $settings->only(array_keys($fields)),
            );

            return $settings->fresh() ?? $settings;
        });
    }

    public function earliestBookableAt(?BookingPolicySetting $settings = null): Carbon
    {
        $settings ??= $this->get();

        return now()->addMinutes(max(0, (int) $settings->min_advance_notice_minutes));
    }

    public function assertStartsAtMeetsAdvanceNotice(CarbonInterface $startsAt, ?BookingPolicySetting $settings = null): void
    {
        $earliest = $this->earliestBookableAt($settings);

        if ($startsAt->lt($earliest)) {
            $minutes = max(0, (int) ($settings ?? $this->get())->min_advance_notice_minutes);
            throw ValidationException::withMessages([
                'starts_at' => ["Bookings must be made at least {$minutes} minutes in advance."],
            ]);
        }
    }

    public function minutesUntilStart(Appointment $appointment): int
    {
        if ($appointment->starts_at === null) {
            return 0;
        }

        return (int) max(0, now()->diffInMinutes($appointment->starts_at, false));
    }

    public function isInsideFreeChangeWindow(Appointment $appointment, ?BookingPolicySetting $settings = null): bool
    {
        $settings ??= $this->get();
        $minutesLeft = $this->minutesUntilStart($appointment);

        return $minutesLeft >= (int) $settings->free_change_window_minutes;
    }

    public function freeWindowReminderAt(Appointment $appointment, ?BookingPolicySetting $settings = null): ?Carbon
    {
        if ($appointment->starts_at === null) {
            return null;
        }

        $settings ??= $this->get();
        $offset = (int) $settings->free_change_window_minutes + (int) $settings->free_window_reminder_lead_minutes;

        return $appointment->starts_at->copy()->subMinutes($offset);
    }

    public function lateCancelFeeCents(Appointment $appointment, ?BookingPolicySetting $settings = null): int
    {
        $settings ??= $this->get();
        $deposit = (int) ($appointment->deposit_required_cents ?? 0);
        if ($deposit <= 0) {
            return 0;
        }

        $percent = max(0, min(100, (int) $settings->late_cancel_fee_percent));

        return (int) round($deposit * ($percent / 100));
    }

    /**
     * @return array<string, int>
     */
    public function publicSummary(?BookingPolicySetting $settings = null): array
    {
        $settings ??= $this->get();

        return [
            'min_advance_notice_minutes' => (int) $settings->min_advance_notice_minutes,
            'free_change_window_minutes' => (int) $settings->free_change_window_minutes,
            'late_cancel_fee_percent' => (int) $settings->late_cancel_fee_percent,
            'free_window_reminder_lead_minutes' => (int) $settings->free_window_reminder_lead_minutes,
            'approval_reminder_interval_minutes' => (int) $settings->approval_reminder_interval_minutes,
            'approval_reminder_max_count' => (int) $settings->approval_reminder_max_count,
        ];
    }
}
