<?php

namespace App\Domains\Pos\Services;

use App\Domains\Payments\Models\PaymentAllocation;
use App\Shared\Commerce\Assemblers\CheckoutTotalsCalculator;
use App\Shared\Commerce\DTO\CheckoutLineDto;
use App\Shared\Commerce\Models\CommerceCheckout;

class CheckoutTotalsRecalculator
{
    public function __construct(private readonly CheckoutTotalsCalculator $calculator) {}

    public function recalculate(CommerceCheckout $checkout): CommerceCheckout
    {
        $checkout->unsetRelation('lines');
        $checkout->load('lines');

        $lineDtos = $checkout->lines->map(fn ($line) => new CheckoutLineDto(
            lineType: $line->line_type,
            description: $line->description,
            quantity: (int) $line->quantity,
            unitPriceCents: $line->unit_price_cents,
            lineTotalCents: $line->line_total_cents,
            referenceType: $line->reference_type,
            referenceId: $line->reference_id,
            pricingSnapshot: $line->pricing_snapshot ?? [],
            sortOrder: $line->sort_order,
        ))->all();

        $totals = $this->calculator->calculate($lineDtos, $checkout->tax_cents ?? 0, $checkout->tip_cents ?? 0);

        $paid = (int) PaymentAllocation::query()
            ->where('commerce_checkout_id', $checkout->id)
            ->whereHas('transaction', fn ($q) => $q->where('status', 'succeeded'))
            ->sum('amount_cents');

        $packageCovered = (int) $checkout->lines->sum('covered_amount_cents');
        $walletCredit = (int) ($checkout->wallet_credit_cents ?? 0);
        $loyaltyDiscount = (int) ($checkout->loyalty_discount_cents ?? 0);
        $membershipCredits = $packageCovered + $walletCredit + $loyaltyDiscount;

        $checkout->subtotal_cents = $totals->subtotalCents;
        $checkout->discount_cents = $totals->discountCents;
        $checkout->deposit_credit_cents = $totals->depositCreditCents;
        $checkout->gift_card_redemption_cents = $totals->giftCardCreditCents;
        $checkout->package_covered_cents = $packageCovered;
        $checkout->tax_cents = $totals->taxCents;
        $checkout->tip_cents = $totals->tipCents;
        $checkout->total_cents = $totals->totalCents;
        $checkout->amount_paid_cents = $paid;
        $checkout->amount_due_cents = max(0, $totals->totalCents - $membershipCredits - $paid);
        $checkout->save();

        return $checkout->fresh(['lines', 'appointmentLinks.appointment', 'client', 'location', 'teamMember']);
    }
}
