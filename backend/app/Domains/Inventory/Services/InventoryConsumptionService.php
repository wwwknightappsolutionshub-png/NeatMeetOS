<?php

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Enums\MovementReferenceType;
use App\Shared\Commerce\Contracts\StockConsumptionExecutionContract;
use App\Shared\Commerce\DTO\InventoryConsumptionRequestDto;
use App\Shared\Commerce\Enums\InventoryConsumptionType;
use Illuminate\Validation\ValidationException;

class InventoryConsumptionService implements StockConsumptionExecutionContract
{
    public function __construct(
        private readonly InventoryScopeValidator $scope,
        private readonly InventoryMovementService $movementService,
    ) {}

    /**
     * @param  list<InventoryConsumptionRequestDto>  $requests
     */
    public function execute(array $requests, ?string $teamMemberId = null): array
    {
        $processed = [];
        $failures = [];

        foreach ($requests as $request) {
            try {
                $movement = $this->executeOne($request, $teamMemberId);
                $processed[] = [
                    'movement_id' => $movement->id,
                    'product_id' => $request->productId,
                    'quantity' => $request->quantity,
                ];
            } catch (ValidationException $e) {
                $failures[] = [
                    'product_id' => $request->productId,
                    'reason' => collect($e->errors())->flatten()->first() ?? 'Validation failed',
                ];
            }
        }

        return compact('processed', 'failures');
    }

    public function executeFromPayload(array $payload, ?string $teamMemberId = null): array
    {
        $requests = [];

        foreach ($payload['requests'] ?? [] as $row) {
            $requests[] = new InventoryConsumptionRequestDto(
                checkoutId: $row['checkout_id'],
                checkoutLineId: $row['checkout_line_id'],
                consumptionType: $row['consumption_type'],
                productId: $row['product_id'],
                quantity: (string) $row['quantity'],
                locationId: $row['location_id'],
                appointmentServiceLineId: $row['appointment_service_line_id'] ?? null,
                recipeSnapshot: $row['recipe_snapshot'] ?? [],
            );
        }

        return $this->execute($requests, $teamMemberId);
    }

    private function executeOne(InventoryConsumptionRequestDto $request, ?string $teamMemberId)
    {
        $this->scope->findItem($request->productId);
        $this->scope->findLocation($request->locationId);

        $qty = (float) $request->quantity;

        if ($qty <= 0) {
            throw ValidationException::withMessages(['quantity' => ['Quantity must be positive.']]);
        }

        [$movementType, $delta] = match ($request->consumptionType) {
            InventoryConsumptionType::RETAIL_SALE => [InventoryMovementType::SALE, -$qty],
            InventoryConsumptionType::PROFESSIONAL_USE => [InventoryMovementType::SERVICE_CONSUMPTION, -$qty],
            InventoryConsumptionType::REVERSAL => [InventoryMovementType::ADJUSTMENT, $qty],
            default => throw ValidationException::withMessages([
                'consumption_type' => ['Unsupported consumption type.'],
            ]),
        };

        return $this->movementService->record([
            'inventory_item_id' => $request->productId,
            'location_id' => $request->locationId,
            'movement_type' => $movementType,
            'quantity_delta' => $delta,
            'reference_type' => MovementReferenceType::CHECKOUT,
            'reference_id' => $request->checkoutId,
            'notes' => $request->consumptionType === InventoryConsumptionType::REVERSAL
                ? 'Stock reversal'
                : 'Commerce consumption',
            'metadata' => [
                'checkout_line_id' => $request->checkoutLineId,
                'appointment_service_line_id' => $request->appointmentServiceLineId,
                'consumption_type' => $request->consumptionType,
                'recipe_snapshot' => $request->recipeSnapshot,
            ],
        ], $teamMemberId);
    }
}
