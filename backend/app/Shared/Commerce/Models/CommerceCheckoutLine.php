<?php

namespace App\Shared\Commerce\Models;

use App\Shared\Commerce\Enums\SaleLineType;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceCheckoutLine extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'commerce_checkout_lines';

    protected $fillable = [
        'tenant_id',
        'checkout_id',
        'line_type',
        'description',
        'quantity',
        'unit_price_cents',
        'discount_cents',
        'discount_type',
        'discount_reason',
        'discount_authorised_by_team_member_id',
        'line_total_cents',
        'returned_quantity',
        'returned_subtotal_cents',
        'return_status',
        'membership_application_type',
        'client_package_id',
        'client_package_redemption_id',
        'covered_quantity',
        'covered_amount_cents',
        'reference_type',
        'reference_id',
        'pricing_snapshot',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'discount_cents' => 'integer',
            'line_total_cents' => 'integer',
            'returned_quantity' => 'decimal:3',
            'returned_subtotal_cents' => 'integer',
            'covered_quantity' => 'decimal:3',
            'covered_amount_cents' => 'integer',
            'sort_order' => 'integer',
            'pricing_snapshot' => 'array',
        ];
    }

    public static function lineTypes(): array
    {
        return SaleLineType::all();
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(CommerceCheckout::class, 'checkout_id');
    }
}
