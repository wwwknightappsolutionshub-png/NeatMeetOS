<?php

namespace App\Domains\Identity\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\AppointmentServiceLine;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\TenantSubscription;
use App\Domains\Identity\Support\ProgressiveModuleAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProgressiveModuleAccessService
{
    public function __construct(
        private readonly PlatformUpgradeDispatchService $upgradeDispatch,
    ) {}

    public function isProgressiveTrial(Tenant $tenant): bool
    {
        $sub = TenantSubscription::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($sub === null) {
            return true;
        }

        if ($sub->status === TenantSubscription::STATUS_TRIAL) {
            return true;
        }

        if ($sub->trial_ends_at !== null && $sub->trial_ends_at->isFuture()) {
            return true;
        }

        return false;
    }

    /**
     * Overlay progressive trial rules onto plan+override features.
     * Keys present in $overrideKeys are left untouched (platform admin wins).
     *
     * @param  array<string, bool>  $features
     * @param  list<string>  $overrideKeys
     * @return array<string, bool>
     */
    public function applyToFeatures(Tenant $tenant, array $features, array $overrideKeys = []): array
    {
        if (! $this->isProgressiveTrial($tenant)) {
            $features['booking_board'] = (bool) ($features['booking'] ?? false);

            return $features;
        }

        $overrideSet = array_fill_keys($overrideKeys, true);

        if (! isset($overrideSet['crm'])) {
            $features['crm'] = $this->clientCount($tenant) < ProgressiveModuleAccess::CRM_GATE_CONTACTS;
        }

        $planBooking = (bool) ($features['booking'] ?? false);
        $overBookingCap = $this->hasBookingWorthAtLeast(
            $tenant,
            ProgressiveModuleAccess::BOOKING_GATE_CENTS,
        );

        if (! isset($overrideSet['booking'])) {
            // Soft-unlock catalogue when under the £500 cap even if the plan excludes booking.
            // Keep plan booking after the cap so tenants can still manage services.
            if ($planBooking) {
                $features['booking'] = true;
            } else {
                $features['booking'] = ! $overBookingCap;
            }
        }

        // Day board / walk-ins / waitlist gate independently of the service catalogue.
        $features['booking_board'] = (bool) ($features['booking'] ?? false) && ! $overBookingCap;

        $withinWindow = $this->withinActivationWindow($tenant, ProgressiveModuleAccess::TIME_UNLOCK_DAYS);
        foreach (ProgressiveModuleAccess::timeUnlockModules() as $key) {
            if (isset($overrideSet[$key])) {
                continue;
            }
            // Soft-unlock for 30 days; after that fall back to plan entitlement.
            $features[$key] = $withinWindow || (bool) ($features[$key] ?? false);
        }

        if (! isset($overrideSet['gallery'])) {
            $galleryTrial = $withinWindow
                && ProgressiveModuleAccess::isGalleryFemaleOriented($tenant->business_type);
            $features['gallery'] = $galleryTrial || (bool) ($features['gallery'] ?? false);
        }

        return $features;
    }

    public function clientCount(Tenant $tenant): int
    {
        return (int) Client::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->count();
    }

    public function hasBookingWorthAtLeast(Tenant $tenant, int $cents): bool
    {
        return DB::table('appointment_services as s')
            ->join('appointments as a', 'a.id', '=', 's.appointment_id')
            ->where('s.tenant_id', $tenant->id)
            ->whereNotIn('a.status', [
                Appointment::STATUS_CANCELLED,
                Appointment::STATUS_NO_SHOW,
            ])
            ->groupBy('s.appointment_id')
            ->havingRaw('COALESCE(SUM(s.price_cents), 0) >= ?', [$cents])
            ->exists();
    }

    public function appointmentValueCents(Appointment $appointment): int
    {
        return (int) AppointmentServiceLine::withoutGlobalScopes()
            ->where('appointment_id', $appointment->id)
            ->sum('price_cents');
    }

    public function withinActivationWindow(Tenant $tenant, int $days): bool
    {
        $anchor = $tenant->activated_at ?? $tenant->created_at;
        if ($anchor === null) {
            return false;
        }

        $elapsed = (int) $anchor->copy()->startOfDay()->diffInDays(now()->copy()->startOfDay());

        return $elapsed < $days;
    }

    public function maybeNudgeAfterClientCreated(Tenant $tenant): void
    {
        if (! $this->isProgressiveTrial($tenant)) {
            return;
        }

        $count = $this->clientCount($tenant);
        if ($count < ProgressiveModuleAccess::CRM_NUDGE_CONTACTS) {
            return;
        }

        $this->safeSendDay21($tenant, ProgressiveModuleAccess::TRIGGER_CONTACTS_20, [
            'client_count' => $count,
        ]);
    }

    public function maybeNudgeAfterAppointmentCreated(Tenant $tenant, Appointment $appointment): void
    {
        if (! $this->isProgressiveTrial($tenant)) {
            return;
        }

        $value = $this->appointmentValueCents($appointment);
        if ($value < ProgressiveModuleAccess::BOOKING_GATE_CENTS) {
            return;
        }

        $this->safeSendDay21($tenant, ProgressiveModuleAccess::TRIGGER_BOOKING_500, [
            'appointment_id' => $appointment->id,
            'appointment_value_cents' => $value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function safeSendDay21(Tenant $tenant, string $trigger, array $meta = []): void
    {
        try {
            $this->upgradeDispatch->sendUsageDay21Offer($tenant, $trigger, $meta);
        } catch (\Throwable $e) {
            Log::warning('progressive.upgrade_nudge_failed', [
                'tenant_id' => $tenant->id,
                'trigger' => $trigger,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
