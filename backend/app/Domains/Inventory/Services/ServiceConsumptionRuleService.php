<?php

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Enums\ConsumptionMode;
use App\Domains\Inventory\Models\ServiceInventoryConsumptionRule;
use App\Shared\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ServiceConsumptionRuleService
{
    public function __construct(
        private readonly InventoryScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(array $filters = []): Collection
    {
        $query = ServiceInventoryConsumptionRule::query()
            ->with(['bookingService', 'inventoryItem'])
            ->orderBy('booking_service_id');

        if (! empty($filters['booking_service_id'])) {
            $query->where('booking_service_id', $filters['booking_service_id']);
        }

        if (! empty($filters['inventory_item_id'])) {
            $query->where('inventory_item_id', $filters['inventory_item_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->limit(200)->get();
    }

    public function create(array $data): ServiceInventoryConsumptionRule
    {
        $this->scope->findBookableService($data['booking_service_id']);
        $this->scope->findItem($data['inventory_item_id']);

        if ((float) $data['quantity_required'] <= 0) {
            throw ValidationException::withMessages([
                'quantity_required' => ['Quantity must be positive.'],
            ]);
        }

        $rule = ServiceInventoryConsumptionRule::query()->create([
            'tenant_id' => $this->scope->tenantId(),
            'booking_service_id' => $data['booking_service_id'],
            'inventory_item_id' => $data['inventory_item_id'],
            'quantity_required' => $data['quantity_required'],
            'consumption_mode' => $data['consumption_mode'] ?? ConsumptionMode::FIXED,
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->auditLogger->log('inventory.service_consumption_rule.created', $rule, null, [
            'booking_service_id' => $rule->booking_service_id,
            'inventory_item_id' => $rule->inventory_item_id,
        ]);

        return $rule->load(['bookingService', 'inventoryItem']);
    }

    public function update(ServiceInventoryConsumptionRule $rule, array $data): ServiceInventoryConsumptionRule
    {
        $this->scope->assertTenantModel($rule);

        if (! empty($data['booking_service_id'])) {
            $this->scope->findBookableService($data['booking_service_id']);
        }

        if (! empty($data['inventory_item_id'])) {
            $this->scope->findItem($data['inventory_item_id']);
        }

        $rule->fill(array_intersect_key($data, array_flip([
            'booking_service_id', 'inventory_item_id', 'quantity_required',
            'consumption_mode', 'notes', 'is_active',
        ])));
        $rule->save();

        $this->auditLogger->log('inventory.service_consumption_rule.updated', $rule, null, [
            'quantity_required' => $rule->quantity_required,
            'is_active' => $rule->is_active,
        ]);

        return $rule->load(['bookingService', 'inventoryItem']);
    }

    public function archive(ServiceInventoryConsumptionRule $rule): ServiceInventoryConsumptionRule
    {
        $this->scope->assertTenantModel($rule);
        $rule->is_active = false;
        $rule->save();

        $this->auditLogger->log('inventory.service_consumption_rule.updated', $rule, null, ['is_active' => false]);

        return $rule;
    }
}
