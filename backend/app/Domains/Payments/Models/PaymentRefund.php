<?php

namespace App\Domains\Payments\Models;

use App\Domains\Identity\Models\TeamMember;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRefund extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'payment_transaction_id',
        'refund_transaction_id',
        'amount_cents',
        'reason',
        'notes',
        'source',
        'commerce_checkout_id',
        'status',
        'provider_reference',
        'processed_at',
        'metadata',
        'created_by_team_member_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'processed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(\App\Shared\Commerce\Models\CommerceCheckout::class, 'commerce_checkout_id');
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function refundTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'refund_transaction_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'created_by_team_member_id');
    }
}
