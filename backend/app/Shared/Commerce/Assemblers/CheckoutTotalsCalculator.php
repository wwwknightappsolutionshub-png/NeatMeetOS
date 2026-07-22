<?php

namespace App\Shared\Commerce\Assemblers;

use App\Shared\Commerce\DTO\CheckoutLineDto;
use App\Shared\Commerce\DTO\CheckoutTotalsDto;
use App\Shared\Commerce\Enums\SaleLineType;
use Illuminate\Validation\ValidationException;

class CheckoutTotalsCalculator
{
    /**
     * @param  list<CheckoutLineDto>  $lines
     */
    public function calculate(array $lines, int $taxCents = 0, int $tipCents = 0): CheckoutTotalsDto
    {
        $subtotal = 0;
        $discount = 0;
        $depositCredit = 0;
        $giftCardCredit = 0;
        $computedTip = $tipCents;

        foreach ($lines as $line) {
            match ($line->lineType) {
                SaleLineType::DISCOUNT,
                SaleLineType::PACKAGE_REDEMPTION,
                SaleLineType::MEMBERSHIP_DISCOUNT => $discount += abs($line->lineTotalCents),

                SaleLineType::DEPOSIT_CREDIT => $depositCredit += abs($line->lineTotalCents),

                SaleLineType::GIFT_CARD_REDEMPTION => $giftCardCredit += abs($line->lineTotalCents),

                SaleLineType::TIP => $computedTip += $line->lineTotalCents,

                SaleLineType::TAX => null,

                default => $subtotal += $line->lineTotalCents,
            };
        }

        $total = $subtotal - $discount - $depositCredit - $giftCardCredit + $taxCents + $computedTip;

        if ($total < 0) {
            throw ValidationException::withMessages([
                'checkout' => ['Checkout total cannot be negative.'],
            ]);
        }

        return new CheckoutTotalsDto(
            subtotalCents: $subtotal,
            discountCents: $discount,
            depositCreditCents: $depositCredit,
            giftCardCreditCents: $giftCardCredit,
            taxCents: $taxCents,
            tipCents: $computedTip,
            totalCents: $total,
        );
    }
}
