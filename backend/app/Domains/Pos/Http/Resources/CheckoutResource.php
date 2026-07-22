<?php

namespace App\Domains\Pos\Http\Resources;

use App\Domains\Pos\Services\CheckoutDepositService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Shared\Commerce\Models\CommerceCheckout */
class CheckoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $depositService = app(CheckoutDepositService::class);

        return [
            'id' => $this->id,
            'checkout_number' => $this->checkout_number,
            'status' => $this->status,
            'source' => $this->source,
            'currency' => $this->currency,
            'client' => $this->whenLoaded('client', fn () => $this->client ? [
                'id' => $this->client->id,
                'first_name' => $this->client->first_name,
                'last_name' => $this->client->last_name,
                'display_name' => trim($this->client->first_name.' '.$this->client->last_name),
            ] : null),
            'location' => $this->whenLoaded('location', fn () => $this->location ? [
                'id' => $this->location->id,
                'name' => $this->location->name,
            ] : null),
            'cashier' => $this->whenLoaded('teamMember', fn () => $this->teamMember ? [
                'id' => $this->teamMember->id,
                'display_name' => $this->teamMember->display_name,
            ] : null),
            'linked_appointments' => $this->whenLoaded('appointmentLinks', fn () => $this->appointmentLinks->map(fn ($link) => [
                'id' => $link->appointment_id,
                'role' => $link->role,
                'imported_subtotal_cents' => $link->imported_subtotal_cents,
                'booking_reference' => $link->appointment?->booking_reference,
                'status' => $link->appointment?->status,
                'billing_settlement_status' => $link->appointment?->billing_settlement_status,
            ])),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'line_type' => $line->line_type,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price_cents' => $line->unit_price_cents,
                'discount_cents' => $line->discount_cents ?? 0,
                'discount_type' => $line->discount_type,
                'discount_reason' => $line->discount_reason,
                'returned_quantity' => $line->returned_quantity ?? 0,
                'returned_subtotal_cents' => $line->returned_subtotal_cents ?? 0,
                'return_status' => $line->return_status ?? 'not_returned',
                'line_total_cents' => $line->line_total_cents,
                'reference_type' => $line->reference_type,
                'reference_id' => $line->reference_id,
                'pricing_snapshot' => $line->pricing_snapshot,
                'sort_order' => $line->sort_order,
                'membership_application_type' => $line->membership_application_type,
                'client_package_id' => $line->client_package_id,
                'client_package_redemption_id' => $line->client_package_redemption_id,
                'covered_quantity' => $line->covered_quantity,
                'covered_amount_cents' => $line->covered_amount_cents ?? 0,
            ])),
            'subtotal_cents' => $this->subtotal_cents,
            'discount_cents' => $this->discount_cents,
            'tax_cents' => $this->tax_cents,
            'tip_cents' => $this->tip_cents,
            'deposit_credit_cents' => $this->deposit_credit_cents ?? 0,
            'gift_card_redemption_cents' => $this->gift_card_redemption_cents ?? 0,
            'wallet_credit_cents' => $this->wallet_credit_cents ?? 0,
            'loyalty_discount_cents' => $this->loyalty_discount_cents ?? 0,
            'loyalty_points_redeemed' => $this->loyalty_points_redeemed ?? 0,
            'package_covered_cents' => $this->package_covered_cents ?? 0,
            'refunded_total_cents' => $this->refunded_total_cents ?? 0,
            'total_cents' => $this->total_cents,
            'amount_paid_cents' => $this->amount_paid_cents ?? 0,
            'amount_due_cents' => $this->amount_due_cents ?? 0,
            'available_deposit_credit' => $depositService->availableCredit($this->resource),
            'notes' => $this->notes,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'reopened_at' => $this->reopened_at?->toIso8601String(),
            'reopen_reason' => $this->reopen_reason,
            'receipt_last_sent_at' => $this->receipt_last_sent_at?->toIso8601String(),
            'receipt_last_delivery_method' => $this->receipt_last_delivery_method,
            'voided_at' => $this->voided_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
