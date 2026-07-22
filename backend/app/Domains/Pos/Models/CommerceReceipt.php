<?php

namespace App\Domains\Pos\Models;

use App\Shared\Commerce\Models\CommerceCheckout;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceReceipt extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'commerce_receipts';

    protected $fillable = [
        'tenant_id',
        'commerce_checkout_id',
        'receipt_number',
        'delivery_method',
        'delivery_status',
        'delivery_target',
        'sent_at',
        'failure_reason',
        'payload_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'payload_snapshot' => 'array',
        ];
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(CommerceCheckout::class, 'commerce_checkout_id');
    }
}
