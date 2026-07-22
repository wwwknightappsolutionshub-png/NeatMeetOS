<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\Models\NotificationAutomationSetting;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class NotificationAutomationSettingService
{
    public function __construct(
        private readonly NotificationScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function get(): NotificationAutomationSetting
    {
        $tenantId = $this->scope->tenantId();

        $settings = NotificationAutomationSetting::query()
            ->where('tenant_id', $tenantId)
            ->first();

        if ($settings !== null) {
            return $settings;
        }

        return NotificationAutomationSetting::query()->create([
            'tenant_id' => $tenantId,
        ]);
    }

    public function update(array $data): NotificationAutomationSetting
    {
        $settings = $this->get();

        $boolFields = [
            'booking_reminders_enabled', 'booking_confirmation_enabled',
            'cancellation_notifications_enabled', 'payment_link_notifications_enabled',
            'payment_reminders_enabled', 'membership_expiry_notifications_enabled',
            'membership_renewal_notifications_enabled',
        ];

        $fields = array_intersect_key($data, array_flip(array_merge($boolFields, [
            'default_booking_reminder_hours', 'default_booking_reminder_minutes',
            'default_payment_reminder_days',
            'sender_name', 'sender_email', 'sender_sms_name', 'metadata',
        ])));

        foreach ($boolFields as $boolField) {
            if (array_key_exists($boolField, $fields)) {
                $fields[$boolField] = filter_var($fields[$boolField], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return DB::transaction(function () use ($settings, $fields) {
            $old = $settings->only(array_keys($fields));
            $settings->fill($fields);
            $settings->save();

            $this->auditLogger->log('notification_automation_settings.updated', $settings, $old, $settings->only(array_keys($fields)));

            return $settings->fresh();
        });
    }

    /**
     * Lead time before appointment start for operational booking reminders.
     * Prefers minutes when set; falls back to hours × 60.
     */
    public function bookingReminderLeadMinutes(): int
    {
        $settings = $this->get();

        if ($settings->default_booking_reminder_minutes !== null) {
            return max(1, (int) $settings->default_booking_reminder_minutes);
        }

        return max(1, ((int) ($settings->default_booking_reminder_hours ?? 24)) * 60);
    }
}
