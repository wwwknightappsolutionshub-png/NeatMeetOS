<?php

namespace App\Domains\Ecommerce\Services;

use App\Domains\Booking\Services\BookingScopeValidator;
use App\Domains\Ecommerce\Models\EcommerceOrder;
use App\Domains\Ecommerce\Models\EcommerceProduct;
use App\Domains\Inventory\Services\InventoryScopeValidator;

class EcommerceScopeValidator
{
    public function __construct(
        private readonly BookingScopeValidator $bookingScope,
        private readonly InventoryScopeValidator $inventoryScope,
    ) {}

    public function tenantId(): string
    {
        return $this->bookingScope->tenantId();
    }

    public function assertTenantModel(object $model): void
    {
        $this->bookingScope->assertTenantModel($model);
    }

    public function findLocation(string $id)
    {
        return $this->inventoryScope->findLocation($id);
    }

    public function findItem(string $id)
    {
        return $this->inventoryScope->findItem($id);
    }

    public function findProduct(string $id): EcommerceProduct
    {
        $product = EcommerceProduct::query()
            ->with('inventoryItem')
            ->findOrFail($id);
        $this->assertTenantModel($product);

        return $product;
    }

    public function findOrder(string $id): EcommerceOrder
    {
        $order = EcommerceOrder::query()
            ->with(['location', 'lines.product', 'lines.inventoryItem'])
            ->findOrFail($id);
        $this->assertTenantModel($order);

        return $order;
    }
}
