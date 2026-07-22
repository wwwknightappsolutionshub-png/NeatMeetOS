<?php

namespace Database\Seeders;

use App\Domains\Booking\Models\BookableService;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Inventory\Enums\InventoryItemType;
use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Enums\MovementReferenceType;
use App\Domains\Inventory\Models\InventoryItem;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Inventory\Models\InventorySupplier;
use App\Domains\Inventory\Models\ServiceInventoryConsumptionRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InventoryDemoSeeder extends Seeder
{
    public function run(Tenant $tenant, Location $location, TeamMember $ownerMember): void
    {
        $salonPro = InventorySupplier::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'SalonPro Supplies',
            'contact_name' => 'Alex Morgan',
            'email' => 'orders@salonpro.demo',
            'phone' => '+44000000001',
            'is_active' => true,
        ]);

        $luxe = InventorySupplier::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Luxe Hair Wholesale',
            'contact_name' => 'Jordan Lee',
            'email' => 'trade@luxehair.demo',
            'is_active' => true,
        ]);

        $items = [
            [
                'name' => 'Retail Shampoo 300ml',
                'sku' => 'RET-SHMP-300',
                'item_type' => InventoryItemType::RETAIL,
                'category' => 'retail',
                'unit_label' => 'bottle',
                'cost_price_cents' => 650,
                'retail_price_cents' => 1800,
                'preferred_supplier_id' => $salonPro->id,
            ],
            [
                'name' => 'Retail Conditioner 300ml',
                'sku' => 'RET-COND-300',
                'item_type' => InventoryItemType::RETAIL,
                'category' => 'retail',
                'unit_label' => 'bottle',
                'cost_price_cents' => 600,
                'retail_price_cents' => 1700,
                'preferred_supplier_id' => $salonPro->id,
            ],
            [
                'name' => 'Colour Tube 6.0',
                'sku' => 'PRO-COL-60',
                'item_type' => InventoryItemType::PROFESSIONAL,
                'category' => 'colour',
                'unit_label' => 'tube',
                'cost_price_cents' => 450,
                'preferred_supplier_id' => $luxe->id,
            ],
            [
                'name' => 'Developer 20vol',
                'sku' => 'PRO-DEV-20',
                'item_type' => InventoryItemType::PROFESSIONAL,
                'category' => 'colour',
                'unit_label' => 'ml',
                'unit_size' => '1000',
                'cost_price_cents' => 1200,
                'preferred_supplier_id' => $luxe->id,
            ],
            [
                'name' => 'Treatment Mask',
                'sku' => 'PRO-MASK-250',
                'item_type' => InventoryItemType::PROFESSIONAL,
                'category' => 'treatment',
                'unit_label' => 'jar',
                'cost_price_cents' => 900,
                'preferred_supplier_id' => $salonPro->id,
            ],
            [
                'name' => 'Disposable Gloves (box)',
                'sku' => 'PRO-GLV-100',
                'item_type' => InventoryItemType::PROFESSIONAL,
                'category' => 'consumable',
                'unit_label' => 'box',
                'cost_price_cents' => 800,
                'preferred_supplier_id' => $salonPro->id,
            ],
        ];

        $createdItems = [];

        foreach ($items as $row) {
            $createdItems[$row['sku']] = InventoryItem::withoutGlobalScopes()->create(array_merge($row, [
                'tenant_id' => $tenant->id,
                'status' => 'active',
            ]));
        }

        foreach ($createdItems as $item) {
            $onHand = match ($item->sku) {
                'RET-SHMP-300' => 24,
                'RET-COND-300' => 18,
                'PRO-COL-60' => 12,
                'PRO-DEV-20' => 3,
                'PRO-MASK-250' => 8,
                'PRO-GLV-100' => 5,
                default => 10,
            };

            $reorderPoint = match ($item->sku) {
                'PRO-DEV-20' => 5,
                'PRO-GLV-100' => 6,
                default => 4,
            };

            InventoryLevel::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'inventory_item_id' => $item->id,
                'location_id' => $location->id,
                'on_hand_quantity' => $onHand,
                'reorder_point' => $reorderPoint,
                'reorder_target' => $reorderPoint * 3,
                'last_restocked_at' => now()->subDays(3),
            ]);

            InventoryMovement::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'inventory_item_id' => $item->id,
                'location_id' => $location->id,
                'movement_type' => InventoryMovementType::OPENING,
                'quantity_delta' => $onHand + 2,
                'quantity_before' => 0,
                'quantity_after' => $onHand + 2,
                'reference_type' => MovementReferenceType::MANUAL,
                'notes' => 'Demo opening stock',
                'performed_by_team_member_id' => $ownerMember->id,
                'created_at' => now()->subDays(7),
            ]);
        }

        InventoryMovement::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'inventory_item_id' => $createdItems['RET-SHMP-300']->id,
            'location_id' => $location->id,
            'movement_type' => InventoryMovementType::PURCHASE_RECEIPT,
            'quantity_delta' => 12,
            'quantity_before' => 12,
            'quantity_after' => 24,
            'reference_type' => MovementReferenceType::SUPPLIER,
            'reference_id' => $salonPro->id,
            'notes' => 'Demo restock delivery',
            'performed_by_team_member_id' => $ownerMember->id,
            'created_at' => now()->subDays(3),
        ]);

        InventoryMovement::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'inventory_item_id' => $createdItems['PRO-MASK-250']->id,
            'location_id' => $location->id,
            'movement_type' => InventoryMovementType::WASTE,
            'quantity_delta' => -1,
            'quantity_before' => 9,
            'quantity_after' => 8,
            'reference_type' => MovementReferenceType::MANUAL,
            'notes' => 'Expired jar disposed',
            'performed_by_team_member_id' => $ownerMember->id,
            'created_at' => now()->subDay(),
        ]);

        InventoryMovement::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'inventory_item_id' => $createdItems['PRO-COL-60']->id,
            'location_id' => $location->id,
            'movement_type' => InventoryMovementType::SERVICE_CONSUMPTION,
            'quantity_delta' => -1,
            'quantity_before' => 13,
            'quantity_after' => 12,
            'reference_type' => MovementReferenceType::APPOINTMENT,
            'reference_id' => (string) Str::uuid(),
            'notes' => 'Demo colour service consumption',
            'performed_by_team_member_id' => $ownerMember->id,
            'created_at' => now()->subHours(6),
        ]);

        $fullColour = BookableService::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Full Colour')
            ->first();

        if ($fullColour !== null) {
            ServiceInventoryConsumptionRule::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'booking_service_id' => $fullColour->id,
                'inventory_item_id' => $createdItems['PRO-COL-60']->id,
                'quantity_required' => 1,
                'consumption_mode' => 'fixed',
                'notes' => 'One tube per full colour',
            ]);

            ServiceInventoryConsumptionRule::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'booking_service_id' => $fullColour->id,
                'inventory_item_id' => $createdItems['PRO-DEV-20']->id,
                'quantity_required' => 60,
                'consumption_mode' => 'estimated',
                'notes' => '60ml developer per application',
            ]);
        }
    }
}
