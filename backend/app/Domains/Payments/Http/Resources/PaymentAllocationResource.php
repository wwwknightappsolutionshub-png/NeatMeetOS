<?php

namespace App\Domains\Payments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'allocation_type' => $this->allocation_type,
            'amount_cents' => $this->amount_cents,
            'appointment_id' => $this->appointment_id,
            'commerce_deposit_record_id' => $this->commerce_deposit_record_id,
            'commerce_checkout_id' => $this->commerce_checkout_id,
            'notes' => $this->notes,
        ];
    }
}
