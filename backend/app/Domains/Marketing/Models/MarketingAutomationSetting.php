<?php

namespace App\Domains\Marketing\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class MarketingAutomationSetting extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'booking_reminder_hours_before',
        'review_request_delay_hours',
        'rebooking_window_days',
        'win_back_inactivity_days',
        'review_request_enabled',
        'auto_pause_on_consent_withdrawal',
    ];

    protected function casts(): array
    {
        return [
            'booking_reminder_hours_before' => 'integer',
            'review_request_delay_hours' => 'integer',
            'rebooking_window_days' => 'integer',
            'win_back_inactivity_days' => 'integer',
            'review_request_enabled' => 'boolean',
            'auto_pause_on_consent_withdrawal' => 'boolean',
        ];
    }
}
