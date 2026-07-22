<?php

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Enums\MovementReferenceType;
use App\Domains\Inventory\Models\InventoryItem;
use App\Domains\Inventory\Models\InventoryLevel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InventoryLevelService
{
    public function __construct(
        private readonly InventoryScopeValidator $scope,
        private readonly InventoryMovementService $movementService,
    ) {}

    public function list(array $filters = []): Collection
    {
        $query = InventoryLevel::query()
            ->with(['item.preferredSupplier', 'location'])
            ->orderBy('inventory_item_id');

        if (! empty($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }

        if (! empty($filters['item_type'])) {
            $query->whereHas('item', fn ($q) => $q->where('item_type', $filters['item_type']));
        }

        if (! empty($filters['low_stock'])) {
            $query->whereNotNull('reorder_point')
                ->whereColumn('on_hand_quantity', '<=', 'reorder_point');
        }

        return $query->limit(300)->get();
    }

    public function forItem(string $itemId): Collection
    {
        $this->scope->findItem($itemId);

        return InventoryLevel::query()
            ->with('location')
            ->where('inventory_item_id', $itemId)
            ->get();
    }

    public function updateForLocation(InventoryItem $item, string $locationId, array $data, ?string $teamMemberId = null): InventoryLevel
    {
        $this->scope->assertTenantModel($item);
        $this->scope->findLocation($locationId);

        return DB::transaction(function () use ($item, $locationId, $data, $teamMemberId) {
            $level = InventoryLevel::query()
                ->where('inventory_item_id', $item->id)
                ->where('location_id', $locationId)
                ->lockForUpdate()
                ->first();

            if ($level === null) {
                $level = InventoryLevel::query()->create([
                    'tenant_id' => $this->scope->tenantId(),
                    'inventory_item_id' => $item->id,
                    'location_id' => $locationId,
                    'on_hand_quantity' => 0,
                    'reserved_quantity' => 0,
                ]);
            }

            if (array_key_exists('reorder_point', $data)) {
                $level->reorder_point = $data['reorder_point'];
            }

            if (array_key_exists('reorder_target', $data)) {
                $level->reorder_target = $data['reorder_target'];
            }

            $level->save();

            if (isset($data['opening_quantity']) && (float) $data['opening_quantity'] > 0) {
                $hasMovements = $item->movements()
                    ->where('location_id', $locationId)
                    ->exists();

                if (! $hasMovements && (float) $level->on_hand_quantity == 0.0) {
                    $this->movementService->record([
                        'inventory_item_id' => $item->id,
                        'location_id' => $locationId,
                        'movement_type' => InventoryMovementType::OPENING,
                        'quantity_delta' => (float) $data['opening_quantity'],
                        'reference_type' => MovementReferenceType::MANUAL,
                        'notes' => 'Opening stock',
                    ], $teamMemberId);

                    $level->refresh();
                }
            }

            return $level->load('location');
        });
    }
}
