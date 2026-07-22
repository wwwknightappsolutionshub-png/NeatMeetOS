<?php

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Models\InventorySupplier;
use App\Shared\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;

class InventorySupplierService
{
    public function __construct(
        private readonly InventoryScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(array $filters = []): Collection
    {
        $query = InventorySupplier::query()->orderBy('name');

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->limit(200)->get();
    }

    public function find(string $id): InventorySupplier
    {
        return InventorySupplier::query()->withCount('items')->findOrFail($id);
    }

    public function create(array $data): InventorySupplier
    {
        $supplier = InventorySupplier::query()->create([
            'tenant_id' => $this->scope->tenantId(),
            'name' => $data['name'],
            'contact_name' => $data['contact_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'website' => $data['website'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->auditLogger->log('inventory.supplier.created', $supplier, null, $supplier->only(['name']));

        return $supplier;
    }

    public function update(InventorySupplier $supplier, array $data): InventorySupplier
    {
        $this->scope->assertTenantModel($supplier);
        $supplier->fill(array_intersect_key($data, array_flip([
            'name', 'contact_name', 'email', 'phone', 'website', 'notes', 'is_active',
        ])));
        $supplier->save();

        $this->auditLogger->log('inventory.supplier.updated', $supplier, null, $supplier->only(['name', 'is_active']));

        return $supplier;
    }

    public function archive(InventorySupplier $supplier): InventorySupplier
    {
        $this->scope->assertTenantModel($supplier);
        $supplier->is_active = false;
        $supplier->save();

        return $supplier;
    }

    public function activate(InventorySupplier $supplier): InventorySupplier
    {
        $this->scope->assertTenantModel($supplier);
        $supplier->is_active = true;
        $supplier->save();

        return $supplier;
    }
}
