<?php

namespace App\Domains\Booking\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class BookingPolicySetting extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'booking_policy_settings';

    protected $attributes = [
        'min_advance_notice_minutes' => 30,
        'free_change_window_minutes' => 15,
        'late_cancel_fee_percent' => 50,
        'free_window_reminder_lead_minutes' => 10,
        'approval_reminder_interval_minutes' => 2,
        'approval_reminder_max_count' => 3,
    ];

    protected $fillable = [
        'tenant_id',
        'min_advance_notice_minutes',
        'free_change_window_minutes',
        'late_cancel_fee_percent',
        'free_window_reminder_lead_minutes',
        'approval_reminder_interval_minutes',
        'approval_reminder_max_count',
    ];

    protected function casts(): array
    {
        return [
            'min_advance_notice_minutes' => 'integer',
            'free_change_window_minutes' => 'integer',
            'late_cancel_fee_percent' => 'integer',
            'free_window_reminder_lead_minutes' => 'integer',
            'approval_reminder_interval_minutes' => 'integer',
            'approval_reminder_max_count' => 'integer',
        ];
    }
}
