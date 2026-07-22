<?php

namespace App\Domains\Memberships\Models;

use App\Domains\Identity\Models\Location;
use App\Domains\Memberships\Enums\MembershipPlanStatus;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipPlan extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'status',
        'plan_type',
        'billing_frequency',
        'price_cents',
        'joining_fee_cents',
        'included_wallet_credit_cents',
        'included_loyalty_points',
        'included_entitlement_quantity',
        'auto_renew',
        'grace_period_days',
        'is_public',
        'applies_to_all_locations',
        'notes',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'joining_fee_cents' => 'integer',
            'included_wallet_credit_cents' => 'integer',
            'included_loyalty_points' => 'integer',
            'included_entitlement_quantity' => 'integer',
            'auto_renew' => 'boolean',
            'grace_period_days' => 'integer',
            'is_public' => 'boolean',
            'applies_to_all_locations' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'membership_plan_locations');
    }

    public function clientMemberships(): HasMany
    {
        return $this->hasMany(ClientMembership::class);
    }

    public function isActive(): bool
    {
        return $this->status === MembershipPlanStatus::ACTIVE;
    }
}
