<?php

namespace App\Domains\Memberships\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class MembershipLoyaltySetting extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const DEFAULT_CRM_JOIN_SIGNUP_POINTS = 300;

    protected $fillable = [
        'tenant_id',
        'is_loyalty_redemption_enabled',
        'points_per_redemption_block',
        'value_cents_per_block',
        'crm_join_signup_points',
    ];

    protected function casts(): array
    {
        return [
            'is_loyalty_redemption_enabled' => 'boolean',
            'points_per_redemption_block' => 'integer',
            'value_cents_per_block' => 'integer',
            'crm_join_signup_points' => 'integer',
        ];
    }
}
