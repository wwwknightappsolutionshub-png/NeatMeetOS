<?php

namespace App\Shared\Commerce\Models;

use App\Domains\Crm\Models\Client;
use App\Domains\Pos\Models\CommerceReceipt;
use App\Domains\Pos\Models\GiftCard;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Shared\Commerce\Enums\CheckoutStatus;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommerceCheckout extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'commerce_checkouts';

    protected $fillable = [
        'tenant_id',
        'checkout_number',
        'client_id',
        'location_id',
        'team_member_id',
        'status',
        'currency',
        'subtotal_cents',
        'discount_cents',
        'tax_cents',
        'tip_cents',
        'deposit_credit_cents',
        'total_cents',
        'amount_paid_cents',
        'amount_due_cents',
        'completed_at',
        'voided_at',
        'metadata',
        'notes',
        'source',
        'reopened_at',
        'reopened_by_team_member_id',
        'reopen_reason',
        'receipt_last_sent_at',
        'receipt_last_delivery_method',
        'receipt_last_delivery_status',
        'refunded_total_cents',
        'gift_card_redemption_cents',
        'wallet_credit_cents',
        'loyalty_discount_cents',
        'loyalty_points_redeemed',
        'package_covered_cents',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'tax_cents' => 'integer',
            'tip_cents' => 'integer',
            'deposit_credit_cents' => 'integer',
            'total_cents' => 'integer',
            'amount_paid_cents' => 'integer',
            'amount_due_cents' => 'integer',
            'refunded_total_cents' => 'integer',
            'gift_card_redemption_cents' => 'integer',
            'wallet_credit_cents' => 'integer',
            'loyalty_discount_cents' => 'integer',
            'loyalty_points_redeemed' => 'integer',
            'package_covered_cents' => 'integer',
            'completed_at' => 'datetime',
            'voided_at' => 'datetime',
            'reopened_at' => 'datetime',
            'receipt_last_sent_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public static function statuses(): array
    {
        return CheckoutStatus::all();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CommerceCheckoutLine::class, 'checkout_id')->orderBy('sort_order');
    }

    public function appointmentLinks(): HasMany
    {
        return $this->hasMany(CommerceCheckoutAppointment::class, 'checkout_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(CommerceReceipt::class, 'commerce_checkout_id');
    }

    public function issuedGiftCards(): HasMany
    {
        return $this->hasMany(GiftCard::class, 'issued_checkout_id');
    }
}
