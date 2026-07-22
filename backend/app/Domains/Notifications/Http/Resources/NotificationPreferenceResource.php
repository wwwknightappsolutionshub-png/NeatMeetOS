<?php

namespace App\Domains\Notifications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationPreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'allow_email' => $this->allow_email,
            'allow_sms' => $this->allow_sms,
            'allow_whatsapp' => $this->allow_whatsapp,
            'allow_push' => $this->allow_push,
            'booking_notifications' => $this->booking_notifications,
            'payment_notifications' => $this->payment_notifications,
            'membership_notifications' => $this->membership_notifications,
            'general_notifications' => $this->general_notifications,
            'preferred_channel' => $this->preferred_channel,
            'last_synced_from_consent_at' => $this->last_synced_from_consent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
