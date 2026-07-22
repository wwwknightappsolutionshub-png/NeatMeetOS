<?php

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Enums\InventoryItemStatus;
use App\Domains\Inventory\Enums\InventoryItemType;
use App\Domains\Inventory\Models\InventoryItem;
use App\Shared\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class InventoryItemService
{
    public function __construct(
        private readonly InventoryScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(array $filters = []): Collection
    {
        $query = InventoryItem::query()
            ->with(['preferredSupplier', 'levels.location'])
            ->orderBy('name');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['item_type'])) {
            $query->where('item_type', $filters['item_type']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        return $query->limit(200)->get();
    }

    public function find(string $id): InventoryItem
    {
        return InventoryItem::query()
            ->with(['preferredSupplier', 'levels.location', 'consumptionRules.bookingService'])
            ->findOrFail($id);
    }

    public function create(array $data): InventoryItem
    {
        if (! empty($data['preferred_supplier_id'])) {
            $this->scope->findSupplier($data['preferred_supplier_id']);
        }

        if (! empty($data['sku'])) {
            $exists = InventoryItem::query()->where('sku', $data['sku'])->exists();
            if ($exists) {
                throw ValidationException::withMessages(['sku' => ['SKU already in use.']]);
            }
        }

        $item = InventoryItem::query()->create([
            'tenant_id' => $this->scope->tenantId(),
            'name' => $data['name'],
            'sku' => $data['sku'] ?? null,
            'item_type' => $data['item_type'],
            'status' => InventoryItemStatus::ACTIVE,
            'brand' => $data['brand'] ?? null,
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'unit_label' => $data['unit_label'] ?? null,
            'unit_size' => $data['unit_size'] ?? null,
            'cost_price_cents' => $data['cost_price_cents'] ?? null,
            'retail_price_cents' => $data['retail_price_cents'] ?? null,
            'tax_code' => $data['tax_code'] ?? null,
            'preferred_supplier_id' => $data['preferred_supplier_id'] ?? null,
            'barcode' => $data['barcode'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);

        $this->auditLogger->log('inventory.item.created', $item, null, $item->only(['name', 'sku', 'item_type']));

        return $item->fresh()->load('preferredSupplier');
    }

    public function update(InventoryItem $item, array $data): InventoryItem
    {
        $this->scope->assertTenantModel($item);
        $old = $item->only(['name', 'sku', 'item_type', 'status']);

        if (! empty($data['preferred_supplier_id'])) {
            $this->scope->findSupplier($data['preferred_supplier_id']);
        }

        if (! empty($data['sku']) && $data['sku'] !== $item->sku) {
            $exists = InventoryItem::query()
                ->where('sku', $data['sku'])
                ->where('id', '!=', $item->id)
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages(['sku' => ['SKU already in use.']]);
            }
        }

        $item->fill(array_intersect_key($data, array_flip([
            'name', 'sku', 'item_type', 'brand', 'category', 'description',
            'unit_label', 'unit_size', 'cost_price_cents', 'retail_price_cents',
            'tax_code', 'preferred_supplier_id', 'barcode', 'metadata',
        ])));
        $item->save();

        $this->auditLogger->log('inventory.item.updated', $item, $old, $item->only(['name', 'sku', 'item_type', 'status']));

        return $item->fresh()->load(['preferredSupplier', 'levels.location']);
    }

    public function archive(InventoryItem $item): InventoryItem
    {
        $this->scope->assertTenantModel($item);
        $item->status = InventoryItemStatus::ARCHIVED;
        $item->save();

        $this->auditLogger->log('inventory.item.archived', $item, null, ['status' => InventoryItemStatus::ARCHIVED]);

        return $item;
    }

    public function activate(InventoryItem $item): InventoryItem
    {
        $this->scope->assertTenantModel($item);
        $item->status = InventoryItemStatus::ACTIVE;
        $item->save();

        $this->auditLogger->log('inventory.item.updated', $item, null, ['status' => InventoryItemStatus::ACTIVE]);

        return $item;
    }
}
