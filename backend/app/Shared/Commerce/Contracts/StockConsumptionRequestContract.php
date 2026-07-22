<?php

namespace App\Shared\Commerce\Contracts;

use App\Shared\Commerce\DTO\InventoryConsumptionRequestDto;

interface StockConsumptionRequestContract
{
    /**
     * Build consumption intents from checkout lines.
     * Execution is implemented in Inventory (Module 7).
     *
     * @param  list<array{line_type: string, checkout_line_id: string, reference_id: string|null, pricing_snapshot: array, quantity: int}>  $checkoutLines
     * @return list<InventoryConsumptionRequestDto>
     */
    public function buildFromCheckoutLines(
        string $checkoutId,
        string $locationId,
        array $checkoutLines,
    ): array;
}
