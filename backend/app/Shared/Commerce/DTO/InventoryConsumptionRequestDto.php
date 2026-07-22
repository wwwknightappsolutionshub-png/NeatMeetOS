<?php

namespace App\Shared\Commerce\DTO;

readonly class InventoryConsumptionRequestDto
{
    /**
     * @param  array<string, mixed>  $recipeSnapshot
     */
    public function __construct(
        public string $checkoutId,
        public string $checkoutLineId,
        public string $consumptionType,
        public string $productId,
        public string $quantity,
        public string $locationId,
        public ?string $appointmentServiceLineId = null,
        public array $recipeSnapshot = [],
    ) {}

    public function toArray(): array
    {
        return [
            'checkout_id' => $this->checkoutId,
            'checkout_line_id' => $this->checkoutLineId,
            'consumption_type' => $this->consumptionType,
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
            'location_id' => $this->locationId,
            'appointment_service_line_id' => $this->appointmentServiceLineId,
            'recipe_snapshot' => $this->recipeSnapshot,
        ];
    }
}
