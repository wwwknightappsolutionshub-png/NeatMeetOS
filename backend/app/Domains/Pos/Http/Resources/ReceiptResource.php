<?php

namespace App\Domains\Pos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domains\Pos\Models\CommerceReceipt */
class ReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'delivery_method' => $this->delivery_method,
            'delivery_status' => $this->delivery_status,
            'delivery_target' => $this->delivery_target,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'failure_reason' => $this->failure_reason,
            'payload_snapshot' => $this->payload_snapshot,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
