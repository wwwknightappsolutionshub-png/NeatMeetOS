<?php

namespace App\Domains\Booking\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domains\Booking\Models\BookingPolicySetting */
class BookingPolicySettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'min_advance_notice_minutes' => $this->min_advance_notice_minutes,
            'free_change_window_minutes' => $this->free_change_window_minutes,
            'late_cancel_fee_percent' => $this->late_cancel_fee_percent,
            'free_window_reminder_lead_minutes' => $this->free_window_reminder_lead_minutes,
            'approval_reminder_interval_minutes' => $this->approval_reminder_interval_minutes,
            'approval_reminder_max_count' => $this->approval_reminder_max_count,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
