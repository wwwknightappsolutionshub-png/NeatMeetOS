<?php

namespace App\Domains\Payments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentRefundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_transaction_id' => $this->payment_transaction_id,
            'refund_transaction_id' => $this->refund_transaction_id,
            'amount_cents' => $this->amount_cents,
            'reason' => $this->reason,
            'status' => $this->status,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
