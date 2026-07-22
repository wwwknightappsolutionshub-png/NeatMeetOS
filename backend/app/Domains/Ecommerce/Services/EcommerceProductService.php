<?php

namespace App\Domains\Ecommerce\Services;

use App\Domains\Ecommerce\Enums\EcommerceProductStatus;
use App\Domains\Ecommerce\Models\EcommerceProduct;
use App\Domains\Inventory\Enums\InventoryItemStatus;
use App\Domains\Inventory\Enums\InventoryItemType;
use App\Domains\Inventory\Models\InventoryItem;
use App\Shared\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class EcommerceProductService
{
    public function __construct(
        private readonly EcommerceScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(array $filters = []): Collection
    {
        $query = EcommerceProduct::query()
            ->with('inventoryItem')
            ->orderBy('sort_order')
            ->orderBy('title');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('inventoryItem', fn ($itemQuery) => $itemQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%"));
            });
        }

        return $query->limit(200)->get();
    }

    public function find(string $id): EcommerceProduct
    {
        return $this->scope->findProduct($id);
    }

    public function create(array $data): EcommerceProduct
    {
        $item = $this->validateInventoryItem($data['inventory_item_id']);

        $exists = EcommerceProduct::query()
            ->where('inventory_item_id', $item->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'inventory_item_id' => ['An ecommerce listing already exists for this inventory item.'],
            ]);
        }

        $product = EcommerceProduct::query()->create([
            'tenant_id' => $this->scope->tenantId(),
            'inventory_item_id' => $item->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'price_cents' => $data['price_cents'],
            'show_on_booking_carousel' => $data['show_on_booking_carousel'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
            'status' => EcommerceProductStatus::ACTIVE,
        ]);

        $this->auditLogger->log('ecommerce.product.created', $product, null, $product->only([
            'title', 'inventory_item_id', 'price_cents', 'status',
        ]));

        return $product->fresh()->load('inventoryItem');
    }

    public function update(EcommerceProduct $product, array $data): EcommerceProduct
    {
        $this->scope->assertTenantModel($product);
        $old = $product->only(['title', 'price_cents', 'status', 'show_on_booking_carousel']);

        if (! empty($data['inventory_item_id']) && $data['inventory_item_id'] !== $product->inventory_item_id) {
            $item = $this->validateInventoryItem($data['inventory_item_id']);

            $exists = EcommerceProduct::query()
                ->where('inventory_item_id', $item->id)
                ->where('id', '!=', $product->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'inventory_item_id' => ['An ecommerce listing already exists for this inventory item.'],
                ]);
            }

            $product->inventory_item_id = $item->id;
        }

        $product->fill(array_intersect_key($data, array_flip([
            'title', 'description', 'image_url', 'price_cents',
            'show_on_booking_carousel', 'sort_order',
        ])));
        $product->save();

        $this->auditLogger->log('ecommerce.product.updated', $product, $old, $product->only([
            'title', 'price_cents', 'status', 'show_on_booking_carousel',
        ]));

        return $product->fresh()->load('inventoryItem');
    }

    public function archive(EcommerceProduct $product): EcommerceProduct
    {
        $this->scope->assertTenantModel($product);
        $product->status = EcommerceProductStatus::ARCHIVED;
        $product->save();

        $this->auditLogger->log('ecommerce.product.archived', $product, null, ['status' => EcommerceProductStatus::ARCHIVED]);

        return $product->fresh()->load('inventoryItem');
    }

    public function activate(EcommerceProduct $product): EcommerceProduct
    {
        $this->scope->assertTenantModel($product);
        $this->validateInventoryItem($product->inventory_item_id);

        $product->status = EcommerceProductStatus::ACTIVE;
        $product->save();

        $this->auditLogger->log('ecommerce.product.activated', $product, null, ['status' => EcommerceProductStatus::ACTIVE]);

        return $product->fresh()->load('inventoryItem');
    }

    private function validateInventoryItem(string $inventoryItemId): InventoryItem
    {
        $item = $this->scope->findItem($inventoryItemId);

        if (! $item->isRetail()) {
            throw ValidationException::withMessages([
                'inventory_item_id' => ['Only retail inventory items can be listed for ecommerce.'],
            ]);
        }

        if ($item->status !== InventoryItemStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'inventory_item_id' => ['Inventory item must be active.'],
            ]);
        }

        return $item;
    }
}
