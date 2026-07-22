<?php

namespace App\Domains\Payments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location_id' => $this->location_id,
            'location' => $this->whenLoaded('location', fn () => $this->location ? [
                'id' => $this->location->id,
                'name' => $this->location->name,
            ] : null),
            'client_id' => $this->client_id,
            'client' => $this->whenLoaded('client', fn () => $this->client ? [
                'id' => $this->client->id,
                'resolved_display_name' => $this->client->resolvedDisplayName(),
            ] : null),
            'appointment_id' => $this->appointment_id,
            'appointment' => $this->whenLoaded('appointment', fn () => $this->appointment ? [
                'id' => $this->appointment->id,
                'booking_reference' => $this->appointment->booking_reference,
            ] : null),
            'team_member_id' => $this->team_member_id,
            'transaction_type' => $this->transaction_type,
            'direction' => $this->direction,
            'status' => $this->status,
            'amount_cents' => $this->amount_cents,
            'currency' => $this->currency,
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'external_reference' => $this->external_reference,
            'payment_method_type' => $this->payment_method_type,
            'payment_method_label' => $this->payment_method_label,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'failure_code' => $this->failure_code,
            'failure_message' => $this->failure_message,
            'metadata' => $this->metadata,
            'refundable_amount_cents' => $this->refundableAmountCents(),
            'allocations' => PaymentAllocationResource::collection($this->whenLoaded('allocations')),
            'refunds' => PaymentRefundResource::collection($this->whenLoaded('refunds')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
