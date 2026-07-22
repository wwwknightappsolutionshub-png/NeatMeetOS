<?php

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Enums\MovementReferenceType;
use App\Domains\Inventory\Models\InventoryItem;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Shared\Audit\AuditLogger;
use App\Shared\Commerce\DTO\CommerceEventDto;
use App\Shared\Commerce\Enums\CommerceEventName;
use App\Shared\Commerce\Services\CommerceEventPublisher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryMovementService
{
    public function __construct(
        private readonly InventoryScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
        private readonly CommerceEventPublisher $eventPublisher,
    ) {}

    public function list(array $filters = []): Collection
    {
        $query = InventoryMovement::query()
            ->with(['item', 'location', 'performedBy'])
            ->orderByDesc('created_at');

        if (! empty($filters['inventory_item_id'])) {
            $query->where('inventory_item_id', $filters['inventory_item_id']);
        }

        if (! empty($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }

        if (! empty($filters['movement_type'])) {
            $query->where('movement_type', $filters['movement_type']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        return $query->limit(200)->get();
    }

    /**
     * @param  array{
     *     inventory_item_id: string,
     *     location_id: string,
     *     movement_type: string,
     *     quantity_delta: float|string,
     *     unit_cost_cents?: int|null,
     *     reference_type?: string|null,
     *     reference_id?: string|null,
     *     notes?: string|null,
     *     metadata?: array|null,
     *     allow_negative?: bool
     * }  $data
     */
    public function record(array $data, ?string $teamMemberId = null): InventoryMovement
    {
        $item = $this->scope->findItem($data['inventory_item_id']);
        $this->scope->findLocation($data['location_id']);

        $delta = (float) $data['quantity_delta'];

        if ($delta == 0.0) {
            throw ValidationException::withMessages([
                'quantity_delta' => ['Quantity delta cannot be zero.'],
            ]);
        }

        if (! in_array($data['movement_type'], InventoryMovementType::all(), true)) {
            throw ValidationException::withMessages([
                'movement_type' => ['Invalid movement type.'],
            ]);
        }

        return DB::transaction(function () use ($data, $item, $delta, $teamMemberId) {
            $level = $this->lockOrCreateLevel($item, $data['location_id']);
            $before = (float) $level->on_hand_quantity;
            $after = $before + $delta;

            if ($after < 0 && empty($data['allow_negative'])) {
                throw ValidationException::withMessages([
                    'quantity_delta' => ['Insufficient stock. Pass allow_negative to override.'],
                ]);
            }

            $movement = InventoryMovement::query()->create([
                'tenant_id' => $this->scope->tenantId(),
                'inventory_item_id' => $item->id,
                'location_id' => $data['location_id'],
                'movement_type' => $data['movement_type'],
                'quantity_delta' => $delta,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'unit_cost_cents' => $data['unit_cost_cents'] ?? $item->cost_price_cents,
                'reference_type' => $data['reference_type'] ?? MovementReferenceType::MANUAL,
                'reference_id' => $data['reference_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'performed_by_team_member_id' => $teamMemberId,
            ]);

            $level->on_hand_quantity = $after;

            if ($data['movement_type'] === InventoryMovementType::PURCHASE_RECEIPT) {
                $level->last_restocked_at = now();
            }

            $level->save();

            $this->auditLogger->log('inventory.movement.recorded', $movement, null, [
                'movement_type' => $movement->movement_type,
                'quantity_delta' => $delta,
                'quantity_after' => $after,
            ]);

            $this->publishMovementEvent($movement, $item);

            return $movement->load(['item', 'location']);
        });
    }

    private function lockOrCreateLevel(InventoryItem $item, string $locationId): InventoryLevel
    {
        $level = InventoryLevel::query()
            ->where('inventory_item_id', $item->id)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if ($level !== null) {
            return $level;
        }

        return InventoryLevel::query()->create([
            'tenant_id' => $this->scope->tenantId(),
            'inventory_item_id' => $item->id,
            'location_id' => $locationId,
            'on_hand_quantity' => 0,
            'reserved_quantity' => 0,
        ]);
    }

    private function publishMovementEvent(InventoryMovement $movement, InventoryItem $item): void
    {
        $payload = [
            'movement_id' => $movement->id,
            'product_id' => $item->id,
            'quantity' => (string) abs((float) $movement->quantity_delta),
            'location_id' => $movement->location_id,
        ];

        $eventName = match ($movement->movement_type) {
            InventoryMovementType::SALE,
            InventoryMovementType::SERVICE_CONSUMPTION => CommerceEventName::STOCK_CONSUMED,
            InventoryMovementType::PURCHASE_RECEIPT => CommerceEventName::STOCK_RESTOCKED,
            default => CommerceEventName::STOCK_ADJUSTED,
        };

        if ($movement->movement_type === InventoryMovementType::ADJUSTMENT
            && isset($movement->metadata['consumption_type'])
            && $movement->metadata['consumption_type'] === 'reversal') {
            $eventName = CommerceEventName::STOCK_REVERSED;
        }

        $this->eventPublisher->publish(new CommerceEventDto(
            eventName: $eventName,
            tenantId: $movement->tenant_id,
            aggregateType: 'inventory_movement',
            aggregateId: $movement->id,
            payload: $payload,
        ));
    }
}
