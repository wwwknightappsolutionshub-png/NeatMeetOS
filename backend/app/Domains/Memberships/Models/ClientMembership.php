<?php

namespace App\Domains\Memberships\Models;

use App\Domains\Crm\Models\Client;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientMembership extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'client_id',
        'membership_plan_id',
        'status',
        'source',
        'started_at',
        'trial_ends_at',
        'current_period_starts_at',
        'current_period_ends_at',
        'next_billing_date',
        'billing_anchor_date',
        'cancel_at_period_end',
        'cancelled_at',
        'paused_at',
        'expires_at',
        'price_cents_snapshot',
        'joining_fee_cents_snapshot',
        'included_wallet_credit_cents_snapshot',
        'included_loyalty_points_snapshot',
        'included_entitlement_quantity_snapshot',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'next_billing_date' => 'date',
            'billing_anchor_date' => 'date',
            'cancel_at_period_end' => 'boolean',
            'cancelled_at' => 'datetime',
            'paused_at' => 'datetime',
            'expires_at' => 'datetime',
            'price_cents_snapshot' => 'integer',
            'joining_fee_cents_snapshot' => 'integer',
            'included_wallet_credit_cents_snapshot' => 'integer',
            'included_loyalty_points_snapshot' => 'integer',
            'included_entitlement_quantity_snapshot' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }
}
