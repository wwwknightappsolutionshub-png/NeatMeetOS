<?php

namespace App\Shared\Commerce\Assemblers;

use App\Shared\Commerce\Contracts\StockConsumptionRequestContract;
use App\Shared\Commerce\DTO\InventoryConsumptionRequestDto;
use App\Shared\Commerce\Enums\InventoryConsumptionType;
use App\Shared\Commerce\Enums\SaleLineType;

class InventoryConsumptionRequestBuilder implements StockConsumptionRequestContract
{
    public function buildFromCheckoutLines(
        string $checkoutId,
        string $locationId,
        array $checkoutLines,
    ): array {
        $requests = [];

        foreach ($checkoutLines as $line) {
            $lineType = $line['line_type'];
            $snapshot = $line['pricing_snapshot'] ?? [];

            if ($lineType === SaleLineType::RETAIL_PRODUCT) {
                $productId = $line['reference_id'] ?? $snapshot['product_id'] ?? null;

                if ($productId === null) {
                    continue;
                }

                $requests[] = new InventoryConsumptionRequestDto(
                    checkoutId: $checkoutId,
                    checkoutLineId: $line['checkout_line_id'],
                    consumptionType: InventoryConsumptionType::RETAIL_SALE,
                    productId: $productId,
                    quantity: (string) ($line['quantity'] ?? 1),
                    locationId: $locationId,
                    recipeSnapshot: $snapshot,
                );
            }

            if ($lineType === SaleLineType::APPOINTMENT_SERVICE) {
                $productId = $snapshot['consumption_product_id'] ?? null;

                if ($productId === null) {
                    continue;
                }

                $requests[] = new InventoryConsumptionRequestDto(
                    checkoutId: $checkoutId,
                    checkoutLineId: $line['checkout_line_id'],
                    consumptionType: InventoryConsumptionType::PROFESSIONAL_USE,
                    productId: $productId,
                    quantity: (string) ($snapshot['consumption_quantity'] ?? '1'),
                    locationId: $locationId,
                    appointmentServiceLineId: $line['reference_id'] ?? null,
                    recipeSnapshot: $snapshot,
                );
            }
        }

        return $requests;
    }
}
