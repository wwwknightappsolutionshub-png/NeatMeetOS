<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\BookableService;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Inventory\Enums\InventoryItemType;
use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Models\InventoryItem;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Inventory\Models\InventorySupplier;
use App\Domains\Inventory\Models\ServiceInventoryConsumptionRule;
use App\Shared\Commerce\Enums\InventoryConsumptionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module7AInventoryAdminTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    protected function inventoryPermissions(): array
    {
        return [
            'inventory.view',
            'inventory.manage',
            'inventory.adjust',
            'inventory.reporting.view',
        ];
    }

    public function test_inventory_item_crud(): void
    {
        $ctx = $this->seedTenantContext($this->inventoryPermissions());

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/inventory/items', [
                'name' => 'Test Shampoo',
                'sku' => 'TST-001',
                'item_type' => InventoryItemType::RETAIL,
                'retail_price_cents' => 1500,
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Test Shampoo');

        $id = $create->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->putJson("/api/v1/admin/inventory/items/{$id}", ['name' => 'Updated Shampoo'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Shampoo');

        $this->assertDatabaseHas('audit_logs', ['action' => 'inventory.item.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'inventory.item.updated']);
    }

    public function test_supplier_crud(): void
    {
        $ctx = $this->seedTenantContext($this->inventoryPermissions());

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/inventory/suppliers', [
                'name' => 'Wholesale Co',
                'email' => 'orders@wholesale.test',
            ])
            ->assertCreated();

        $id = $create->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->putJson("/api/v1/admin/inventory/suppliers/{$id}", ['contact_name' => 'Sam'])
            ->assertOk()
            ->assertJsonPath('data.contact_name', 'Sam');

        $this->assertDatabaseHas('audit_logs', ['action' => 'inventory.supplier.created']);
    }

    public function test_location_stock_level_update_and_opening(): void
    {
        $ctx = $this->seedTenantContext($this->inventoryPermissions());

        $item = InventoryItem::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Level Item',
            'item_type' => InventoryItemType::PROFESSIONAL,
            'status' => 'active',
        ]);

        $this->withTenantAuth($ctx['token'])
            ->putJson("/api/v1/admin/inventory/items/{$item->id}/levels/{$ctx['location']->id}", [
                'reorder_point' => 5,
                'reorder_target' => 20,
                'opening_quantity' => 15,
            ])
            ->assertOk()
            ->assertJsonPath('data.reorder_point', '5.000');

        $this->assertDatabaseHas('inventory_levels', [
            'inventory_item_id' => $item->id,
            'location_id' => $ctx['location']->id,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $item->id,
            'movement_type' => InventoryMovementType::OPENING,
        ]);
    }

    public function test_stock_movement_updates_level(): void
    {
        $ctx = $this->seedTenantContext($this->inventoryPermissions());

        $item = InventoryItem::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Move Item',
            'item_type' => InventoryItemType::RETAIL,
            'status' => 'active',
        ]);

        InventoryLevel::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'inventory_item_id' => $item->id,
            'location_id' => $ctx['location']->id,
            'on_hand_quantity' => 10,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/inventory/movements', [
                'inventory_item_id' => $item->id,
                'location_id' => $ctx['location']->id,
                'movement_type' => InventoryMovementType::PURCHASE_RECEIPT,
                'quantity_delta' => 5,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('inventory_levels', [
            'inventory_item_id' => $item->id,
            'on_hand_quantity' => 15,
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'inventory.movement.recorded']);
        $this->assertDatabaseHas('commerce_events', ['event_name' => 'stock.restocked']);
    }

    public function test_negative_stock_blocked_without_override(): void
    {
        $ctx = $this->seedTenantContext($this->inventoryPermissions());

        $item = InventoryItem::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Low Item',
            'item_type' => InventoryItemType::RETAIL,
            'status' => 'active',
        ]);

        InventoryLevel::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'inventory_item_id' => $item->id,
            'location_id' => $ctx['location']->id,
            'on_hand_quantity' => 2,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/inventory/movements', [
                'inventory_item_id' => $item->id,
                'location_id' => $ctx['location']->id,
                'movement_type' => InventoryMovementType::ADJUSTMENT,
                'quantity_delta' => -5,
            ])
            ->assertStatus(422);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/inventory/movements', [
                'inventory_item_id' => $item->id,
                'location_id' => $ctx['location']->id,
                'movement_type' => InventoryMovementType::ADJUSTMENT,
                'quantity_delta' => -5,
                'allow_negative' => true,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('inventory_levels', [
            'inventory_item_id' => $item->id,
            'on_hand_quantity' => -3,
        ]);
    }

    public function test_tenant_isolation(): void
    {
        $ctx = $this->seedTenantContext($this->inventoryPermissions());

        $otherItem = InventoryItem::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'name' => 'Other Item',
            'item_type' => InventoryItemType::RETAIL,
            'status' => 'active',
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/inventory/items')
            ->assertOk()
            ->assertJsonMissing(['id' => $otherItem->id]);

        $this->withTenantAuth($ctx['token'])
            ->getJson("/api/v1/admin/inventory/items/{$otherItem->id}")
            ->assertNotFound();
    }

    public function test_service_consumption_rule_crud_and_booking_validation(): void
    {
        $ctx = $this->seedTenantContext($this->inventoryPermissions());

        $service = BookableService::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Colour Service',
            'duration_minutes' => 90,
            'is_active' => true,
        ]);

        $item = InventoryItem::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Tube',
            'item_type' => InventoryItemType::PROFESSIONAL,
            'status' => 'active',
        ]);

        $otherService = BookableService::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'name' => 'Other',
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/inventory/service-consumption-rules', [
                'booking_service_id' => $otherService->id,
                'inventory_item_id' => $item->id,
                'quantity_required' => 1,
            ])
            ->assertNotFound();

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/inventory/service-consumption-rules', [
                'booking_service_id' => $service->id,
                'inventory_item_id' => $item->id,
                'quantity_required' => 1,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('audit_logs', ['action' => 'inventory.service_consumption_rule.created']);
    }

    public function test_low_stock_filtering(): void
    {
        $ctx = $this->seedTenantContext($this->inventoryPermissions());

        $item = InventoryItem::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Low Stock Item',
            'item_type' => InventoryItemType::PROFESSIONAL,
            'status' => 'active',
        ]);

        InventoryLevel::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'inventory_item_id' => $item->id,
            'location_id' => $ctx['location']->id,
            'on_hand_quantity' => 2,
            'reorder_point' => 5,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/inventory/levels?low_stock=1')
            ->assertOk()
            ->assertJsonPath('data.0.is_low_stock', true);
    }

    public function test_view_only_permission_cannot_manage_or_report(): void
    {
        $viewOnly = $this->seedTenantContext(['inventory.view']);

        $this->withTenantAuth($viewOnly['token'])
            ->getJson('/api/v1/admin/inventory/items')
            ->assertOk();

        $this->withTenantAuth($viewOnly['token'])
            ->postJson('/api/v1/admin/inventory/items', [
                'name' => 'Blocked',
                'item_type' => InventoryItemType::RETAIL,
            ])
            ->assertForbidden();

        $this->withTenantAuth($viewOnly['token'])
            ->getJson('/api/v1/admin/inventory/levels?low_stock=1')
            ->assertForbidden();
    }

    public function test_manage_without_adjust_cannot_record_movement(): void
    {
        $manageOnly = $this->seedTenantContext(['inventory.view', 'inventory.manage']);

        $this->withTenantAuth($manageOnly['token'])
            ->postJson('/api/v1/admin/inventory/movements', [
                'inventory_item_id' => (string) Str::uuid(),
                'location_id' => (string) Str::uuid(),
                'movement_type' => InventoryMovementType::ADJUSTMENT,
                'quantity_delta' => 1,
            ])
            ->assertForbidden();
    }

    public function test_inventory_consumption_contract_endpoint(): void
    {
        $ctx = $this->seedTenantContext($this->inventoryPermissions());

        $item = InventoryItem::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Retail Product',
            'item_type' => InventoryItemType::RETAIL,
            'status' => 'active',
        ]);

        InventoryLevel::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'inventory_item_id' => $item->id,
            'location_id' => $ctx['location']->id,
            'on_hand_quantity' => 10,
        ]);

        $checkoutId = (string) Str::uuid();
        $lineId = (string) Str::uuid();

        $response = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/inventory/consume', [
                'requests' => [[
                    'checkout_id' => $checkoutId,
                    'checkout_line_id' => $lineId,
                    'consumption_type' => InventoryConsumptionType::RETAIL_SALE,
                    'product_id' => $item->id,
                    'quantity' => 2,
                    'location_id' => $ctx['location']->id,
                ]],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.processed.0.product_id', $item->id);

        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $item->id,
            'movement_type' => InventoryMovementType::SALE,
        ]);

        $this->assertDatabaseHas('commerce_events', ['event_name' => 'stock.consumed']);
    }
}
