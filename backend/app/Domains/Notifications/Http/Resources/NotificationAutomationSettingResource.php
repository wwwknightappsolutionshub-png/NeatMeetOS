<?php

namespace App\Domains\Notifications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationAutomationSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_reminders_enabled' => $this->booking_reminders_enabled,
            'booking_confirmation_enabled' => $this->booking_confirmation_enabled,
            'cancellation_notifications_enabled' => $this->cancellation_notifications_enabled,
            'payment_link_notifications_enabled' => $this->payment_link_notifications_enabled,
            'payment_reminders_enabled' => $this->payment_reminders_enabled,
            'membership_expiry_notifications_enabled' => $this->membership_expiry_notifications_enabled,
            'membership_renewal_notifications_enabled' => $this->membership_renewal_notifications_enabled,
            'default_booking_reminder_hours' => $this->default_booking_reminder_hours,
            'default_booking_reminder_minutes' => $this->default_booking_reminder_minutes,
            'default_payment_reminder_days' => $this->default_payment_reminder_days,
            'sender_name' => $this->sender_name,
            'sender_email' => $this->sender_email,
            'sender_sms_name' => $this->sender_sms_name,
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
