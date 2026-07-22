<?php

namespace App\Domains\Inventory\Services;

use App\Domains\Booking\Models\BookableService;
use App\Domains\Booking\Services\BookingScopeValidator;
use App\Domains\Identity\Models\Location;
use App\Domains\Inventory\Models\InventoryItem;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Inventory\Models\InventorySupplier;
use App\Domains\Inventory\Models\ServiceInventoryConsumptionRule;

class InventoryScopeValidator
{
    public function __construct(private readonly BookingScopeValidator $bookingScope) {}

    public function tenantId(): string
    {
        return $this->bookingScope->tenantId();
    }

    public function assertTenantModel(object $model): void
    {
        $this->bookingScope->assertTenantModel($model);
    }

    public function findItem(string $id): InventoryItem
    {
        $item = InventoryItem::query()->findOrFail($id);
        $this->assertTenantModel($item);

        return $item;
    }

    public function findSupplier(string $id): InventorySupplier
    {
        $supplier = InventorySupplier::query()->findOrFail($id);
        $this->assertTenantModel($supplier);

        return $supplier;
    }

    public function findLocation(string $id): Location
    {
        return $this->bookingScope->findLocation($id);
    }

    public function findBookableService(string $id): BookableService
    {
        return $this->bookingScope->findBookableService($id);
    }

    public function findLevel(string $itemId, string $locationId): InventoryLevel
    {
        $level = InventoryLevel::query()
            ->where('inventory_item_id', $itemId)
            ->where('location_id', $locationId)
            ->firstOrFail();

        $this->assertTenantModel($level);

        return $level;
    }

    public function findMovement(string $id): InventoryMovement
    {
        $movement = InventoryMovement::query()->findOrFail($id);
        $this->assertTenantModel($movement);

        return $movement;
    }

    public function findConsumptionRule(string $id): ServiceInventoryConsumptionRule
    {
        $rule = ServiceInventoryConsumptionRule::query()->findOrFail($id);
        $this->assertTenantModel($rule);

        return $rule;
    }
}
