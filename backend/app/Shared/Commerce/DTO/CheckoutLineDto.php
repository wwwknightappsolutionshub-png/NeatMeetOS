<?php

namespace App\Shared\Commerce\DTO;

readonly class CheckoutLineDto
{
    /**
     * @param  array<string, mixed>  $pricingSnapshot
     */
    public function __construct(
        public string $lineType,
        public string $description,
        public int $quantity,
        public int $unitPriceCents,
        public int $lineTotalCents,
        public ?string $referenceType = null,
        public ?string $referenceId = null,
        public array $pricingSnapshot = [],
        public int $sortOrder = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'line_type' => $this->lineType,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price_cents' => $this->unitPriceCents,
            'line_total_cents' => $this->lineTotalCents,
            'reference_type' => $this->referenceType,
            'reference_id' => $this->referenceId,
            'pricing_snapshot' => $this->pricingSnapshot,
            'sort_order' => $this->sortOrder,
        ];
    }
}
