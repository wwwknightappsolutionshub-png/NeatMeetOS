<?php

namespace App\Domains\Notifications\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class NotificationAutomationSetting extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'notification_automation_settings';

    protected $attributes = [
        'booking_reminders_enabled' => true,
        'booking_confirmation_enabled' => true,
        'cancellation_notifications_enabled' => true,
        'payment_link_notifications_enabled' => true,
        'payment_reminders_enabled' => true,
        'membership_expiry_notifications_enabled' => true,
        'membership_renewal_notifications_enabled' => true,
        'default_booking_reminder_hours' => 24,
        'default_booking_reminder_minutes' => 45,
        'default_payment_reminder_days' => 3,
    ];

    protected $fillable = [
        'tenant_id',
        'booking_reminders_enabled',
        'booking_confirmation_enabled',
        'cancellation_notifications_enabled',
        'payment_link_notifications_enabled',
        'payment_reminders_enabled',
        'membership_expiry_notifications_enabled',
        'membership_renewal_notifications_enabled',
        'default_booking_reminder_hours',
        'default_booking_reminder_minutes',
        'default_payment_reminder_days',
        'sender_name',
        'sender_email',
        'sender_sms_name',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'booking_reminders_enabled' => 'boolean',
            'booking_confirmation_enabled' => 'boolean',
            'cancellation_notifications_enabled' => 'boolean',
            'payment_link_notifications_enabled' => 'boolean',
            'payment_reminders_enabled' => 'boolean',
            'membership_expiry_notifications_enabled' => 'boolean',
            'membership_renewal_notifications_enabled' => 'boolean',
            'default_booking_reminder_hours' => 'integer',
            'default_booking_reminder_minutes' => 'integer',
            'default_payment_reminder_days' => 'integer',
            'metadata' => 'array',
        ];
    }
}
