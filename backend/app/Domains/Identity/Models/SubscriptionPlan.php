<?php

namespace App\Domains\Identity\Models;

use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasUuid;

    public const INTERVAL_MONTHLY = 'monthly';

    public const INTERVAL_ANNUAL = 'annual';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'billing_interval',
        'features',
        'limits',
        'display_price_cents',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'limits' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function tenantSubscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }
}
