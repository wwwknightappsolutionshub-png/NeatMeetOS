<?php

namespace App\Domains\Pos\Models;

use App\Domains\Identity\Models\TeamMember;
use App\Domains\Payments\Models\PaymentRefund;
use App\Shared\Commerce\Models\CommerceCheckout;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftCardTransaction extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'gift_card_id',
        'type',
        'amount_cents',
        'commerce_checkout_id',
        'payment_refund_id',
        'notes',
        'created_by_team_member_id',
    ];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(CommerceCheckout::class, 'commerce_checkout_id');
    }

    public function paymentRefund(): BelongsTo
    {
        return $this->belongsTo(PaymentRefund::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'created_by_team_member_id');
    }
}
