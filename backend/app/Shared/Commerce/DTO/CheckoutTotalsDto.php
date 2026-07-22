<?php

namespace App\Shared\Commerce\DTO;

readonly class CheckoutTotalsDto
{
    public function __construct(
        public int $subtotalCents,
        public int $discountCents,
        public int $depositCreditCents,
        public int $giftCardCreditCents = 0,
        public int $taxCents = 0,
        public int $tipCents = 0,
        public int $totalCents = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'subtotal_cents' => $this->subtotalCents,
            'discount_cents' => $this->discountCents,
            'deposit_credit_cents' => $this->depositCreditCents,
            'gift_card_redemption_cents' => $this->giftCardCreditCents,
            'tax_cents' => $this->taxCents,
            'tip_cents' => $this->tipCents,
            'total_cents' => $this->totalCents,
        ];
    }
}
