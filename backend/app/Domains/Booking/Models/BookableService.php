<?php

namespace App\Domains\Booking\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class BookableService extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'booking_services';

    protected $fillable = [
        'tenant_id',
        'name',
        'category',
        'description',
        'image_url',
        'duration_minutes',
        'base_price_cents',
        'membership_price_cents',
        'loyalty_price_cents',
        'is_active',
        'is_bookable_online',
        'display_order',
        'deposit_required',
        'deposit_amount_cents',
        'min_lead_time_hours',
        'cancellation_window_hours',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'base_price_cents' => 'integer',
            'membership_price_cents' => 'integer',
            'loyalty_price_cents' => 'integer',
            'is_active' => 'boolean',
            'is_bookable_online' => 'boolean',
            'display_order' => 'integer',
            'deposit_required' => 'boolean',
            'deposit_amount_cents' => 'integer',
            'min_lead_time_hours' => 'integer',
            'cancellation_window_hours' => 'integer',
        ];
    }
}
