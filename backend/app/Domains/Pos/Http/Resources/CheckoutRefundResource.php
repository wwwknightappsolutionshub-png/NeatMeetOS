<?php

namespace App\Domains\Pos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domains\Payments\Models\PaymentRefund */
class CheckoutRefundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount_cents' => $this->amount_cents,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'source' => $this->source,
            'status' => $this->status,
            'payment_transaction_id' => $this->payment_transaction_id,
            'refund_transaction_id' => $this->refund_transaction_id,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
