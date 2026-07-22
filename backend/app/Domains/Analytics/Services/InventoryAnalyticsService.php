<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\DTOs\DateRange;
use App\Domains\Inventory\Enums\InventoryMovementType;
use Illuminate\Support\Facades\DB;

/**
 * Inventory analytics. Movement metrics are anchored on
 * inventory_movements.created_at; low-stock is a point-in-time snapshot from
 * inventory_levels (reorder_point IS NOT NULL AND on_hand_quantity <= reorder_point).
 */
class InventoryAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function report(string $tenantId, DateRange $range, ?string $locationId = null): array
    {
        return [
            'range' => $range->toArray(),
            'summary' => $this->summary($tenantId, $range, $locationId),
            'movement_breakdown' => $this->movementBreakdown($tenantId, $range, $locationId),
            'low_stock' => $this->lowStock($tenantId, $locationId),
            'top_consumed_items' => $this->topConsumedItems($tenantId, $range, $locationId),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    public function summary(string $tenantId, DateRange $range, ?string $locationId = null): array
    {
        $moves = $this->movementQuery($tenantId, $range, $locationId);

        $consumption = (float) (clone $moves)
            ->where('movement_type', InventoryMovementType::SERVICE_CONSUMPTION)
            ->selectRaw('COALESCE(SUM(ABS(quantity_delta)), 0) as total')
            ->value('total');

        $waste = (float) (clone $moves)
            ->where('movement_type', InventoryMovementType::WASTE)
            ->selectRaw('COALESCE(SUM(ABS(quantity_delta)), 0) as total')
            ->value('total');

        return [
            'low_stock_items_count' => $this->lowStockCount($tenantId, $locationId),
            'total_movements_count' => (int) (clone $moves)->count(),
            'stock_adjustments_count' => (int) (clone $moves)->where('movement_type', InventoryMovementType::ADJUSTMENT)->count(),
            'stock_consumption_events_count' => (int) (clone $moves)->where('movement_type', InventoryMovementType::SERVICE_CONSUMPTION)->count(),
            'consumption_total_quantity' => round($consumption, 3),
            'waste_total_quantity' => round($waste, 3),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function movementBreakdown(string $tenantId, DateRange $range, ?string $locationId): array
    {
        return $this->movementQuery($tenantId, $range, $locationId)
            ->selectRaw('movement_type, COUNT(*) as total, COALESCE(SUM(ABS(quantity_delta)), 0) as quantity')
            ->groupBy('movement_type')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'movement_type' => $row->movement_type,
                'total' => (int) $row->total,
                'quantity' => round((float) $row->quantity, 3),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lowStock(string $tenantId, ?string $locationId): array
    {
        $query = DB::table('inventory_levels as l')
            ->join('inventory_items as i', 'i.id', '=', 'l.inventory_item_id')
            ->where('l.tenant_id', $tenantId)
            ->whereNotNull('l.reorder_point')
            ->whereColumn('l.on_hand_quantity', '<=', 'l.reorder_point');

        if ($locationId !== null) {
            $query->where('l.location_id', $locationId);
        }

        return $query
            ->selectRaw('i.id as item_id, i.name as item_name, i.item_type as item_type, l.location_id as location_id, l.on_hand_quantity as on_hand_quantity, l.reorder_point as reorder_point')
            ->orderBy('l.on_hand_quantity')
            ->limit(50)
            ->get()
            ->map(fn ($row) => [
                'item_id' => $row->item_id,
                'item_name' => $row->item_name,
                'item_type' => $row->item_type,
                'location_id' => $row->location_id,
                'on_hand_quantity' => round((float) $row->on_hand_quantity, 3),
                'reorder_point' => round((float) $row->reorder_point, 3),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topConsumedItems(string $tenantId, DateRange $range, ?string $locationId): array
    {
        $query = DB::table('inventory_movements as m')
            ->join('inventory_items as i', 'i.id', '=', 'm.inventory_item_id')
            ->where('m.tenant_id', $tenantId)
            ->where('m.movement_type', InventoryMovementType::SERVICE_CONSUMPTION)
            ->whereBetween('m.created_at', [$range->from, $range->to]);

        if ($locationId !== null) {
            $query->where('m.location_id', $locationId);
        }

        return $query
            ->selectRaw('i.id as item_id, i.name as item_name, i.item_type as item_type, COALESCE(SUM(ABS(m.quantity_delta)), 0) as quantity, COUNT(*) as events')
            ->groupBy('i.id', 'i.name', 'i.item_type')
            ->orderByDesc('quantity')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'item_id' => $row->item_id,
                'item_name' => $row->item_name,
                'item_type' => $row->item_type,
                'quantity' => round((float) $row->quantity, 3),
                'events' => (int) $row->events,
            ])
            ->all();
    }

    private function lowStockCount(string $tenantId, ?string $locationId): int
    {
        $query = DB::table('inventory_levels')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('reorder_point')
            ->whereColumn('on_hand_quantity', '<=', 'reorder_point');

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        return (int) $query->count();
    }

    private function movementQuery(string $tenantId, DateRange $range, ?string $locationId): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('inventory_movements')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$range->from, $range->to]);

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        return $query;
    }
}
