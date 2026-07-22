<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Marketing\Models\MarketingAutomationSetting;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class MarketingAutomationSettingService
{
    private const DEFAULTS = [
        'booking_reminder_hours_before' => 24,
        'review_request_delay_hours' => 24,
        'rebooking_window_days' => 42,
        'win_back_inactivity_days' => 120,
        'review_request_enabled' => true,
        'auto_pause_on_consent_withdrawal' => true,
    ];

    private const UPDATABLE = [
        'booking_reminder_hours_before',
        'review_request_delay_hours',
        'rebooking_window_days',
        'win_back_inactivity_days',
        'review_request_enabled',
        'auto_pause_on_consent_withdrawal',
    ];

    public function __construct(
        private readonly MarketingScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function get(): MarketingAutomationSetting
    {
        return $this->getOrCreate();
    }

    public function getOrCreate(): MarketingAutomationSetting
    {
        $tenantId = $this->scope->tenantId();

        $setting = MarketingAutomationSetting::query()->where('tenant_id', $tenantId)->first();

        if ($setting !== null) {
            return $setting;
        }

        return DB::transaction(function () use ($tenantId) {
            $setting = MarketingAutomationSetting::query()->create(array_merge(
                self::DEFAULTS,
                ['tenant_id' => $tenantId],
            ));

            $this->auditLogger->log('marketing_automation_settings.created', $setting, null, $setting->only(self::UPDATABLE));

            return $setting;
        });
    }

    public function update(array $data): MarketingAutomationSetting
    {
        $setting = $this->getOrCreate();
        $fields = array_intersect_key($data, array_flip(self::UPDATABLE));

        return DB::transaction(function () use ($setting, $fields) {
            $old = $setting->only(array_keys($fields));
            $setting->fill($fields);
            $setting->save();

            $this->auditLogger->log('marketing_automation_settings.updated', $setting, $old, $setting->only(array_keys($fields)));

            return $setting->fresh();
        });
    }
}
