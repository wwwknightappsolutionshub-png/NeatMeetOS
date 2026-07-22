<?php

namespace Database\Seeders;

use App\Domains\Ecommerce\Enums\EcommerceProductStatus;
use App\Domains\Ecommerce\Models\EcommerceProduct;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Inventory\Enums\InventoryItemType;
use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Enums\MovementReferenceType;
use App\Domains\Inventory\Models\InventoryItem;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryMovement;
use Illuminate\Database\Seeder;

class EcommerceDemoSeeder extends Seeder
{
    public function run(Tenant $tenant, Location $location, TeamMember $owner): void
    {
        $catalog = [
            [
                'sku' => 'ECO-SHMP-300',
                'name' => 'Salon Shampoo 300ml',
                'title' => 'Salon Shampoo',
                'description' => 'Gentle daily shampoo for all hair types.',
                'price_cents' => 1999,
                'stock' => 20,
                'image_url' => '/shop/shampoo.svg',
            ],
            [
                'sku' => 'ECO-COND-300',
                'name' => 'Salon Conditioner 300ml',
                'title' => 'Salon Conditioner',
                'description' => 'Nourishing conditioner to pair with our shampoo.',
                'price_cents' => 1899,
                'stock' => 16,
                'image_url' => '/shop/conditioner.svg',
            ],
            [
                'sku' => 'ECO-SERUM-50',
                'name' => 'Smoothing Serum 50ml',
                'title' => 'Smoothing Serum',
                'description' => 'Lightweight frizz control for finishing styles.',
                'price_cents' => 2499,
                'stock' => 12,
                'image_url' => '/shop/serum.svg',
            ],
            [
                'sku' => 'ECO-MASK-200',
                'name' => 'Repair Mask 200ml',
                'title' => 'Repair Mask',
                'description' => 'Weekly treatment mask for damaged hair.',
                'price_cents' => 2799,
                'stock' => 10,
                'image_url' => '/shop/mask.svg',
            ],
        ];

        foreach ($catalog as $index => $row) {
            $item = InventoryItem::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('sku', $row['sku'])
                ->first();

            if ($item === null) {
                $item = InventoryItem::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id,
                    'name' => $row['name'],
                    'sku' => $row['sku'],
                    'item_type' => InventoryItemType::RETAIL,
                    'status' => 'active',
                    'category' => 'retail',
                    'retail_price_cents' => $row['price_cents'],
                ]);
            }

            $level = InventoryLevel::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('inventory_item_id', $item->id)
                ->where('location_id', $location->id)
                ->first();

            if ($level === null) {
                InventoryLevel::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id,
                    'inventory_item_id' => $item->id,
                    'location_id' => $location->id,
                    'on_hand_quantity' => $row['stock'],
                ]);

                InventoryMovement::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id,
                    'inventory_item_id' => $item->id,
                    'location_id' => $location->id,
                    'movement_type' => InventoryMovementType::OPENING,
                    'quantity_delta' => $row['stock'],
                    'quantity_before' => 0,
                    'quantity_after' => $row['stock'],
                    'reference_type' => MovementReferenceType::MANUAL,
                    'notes' => 'Demo ecommerce opening stock',
                    'performed_by_team_member_id' => $owner->id,
                ]);
            }

            EcommerceProduct::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'inventory_item_id' => $item->id,
                ],
                [
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'image_url' => $row['image_url'] ?? null,
                    'price_cents' => $row['price_cents'],
                    'show_on_booking_carousel' => true,
                    'sort_order' => $index,
                    'status' => EcommerceProductStatus::ACTIVE,
                ],
            );
        }
    }
}
