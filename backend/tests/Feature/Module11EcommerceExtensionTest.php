<?php

namespace Tests\Feature;

use App\Domains\Ecommerce\Enums\EcommerceOrderStatus;
use App\Domains\Ecommerce\Enums\EcommercePaymentStatus;
use App\Domains\Ecommerce\Models\EcommerceOrder;
use App\Domains\Ecommerce\Models\EcommerceProduct;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Inventory\Enums\InventoryItemType;
use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Enums\MovementReferenceType;
use App\Domains\Inventory\Models\InventoryItem;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module11EcommerceExtensionTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    protected function ecommercePermissions(): array
    {
        return [
            'ecommerce.view',
            'ecommerce.manage',
            'inventory.view',
            'inventory.manage',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function seedEcommerceContext(int $stock = 10): array
    {
        $ctx = $this->seedTenantContext($this->ecommercePermissions());

        $item = InventoryItem::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Retail Serum',
            'sku' => 'ECO-TEST-001',
            'item_type' => InventoryItemType::RETAIL,
            'status' => 'active',
            'retail_price_cents' => 2500,
        ]);

        InventoryLevel::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'inventory_item_id' => $item->id,
            'location_id' => $ctx['location']->id,
            'on_hand_quantity' => $stock,
        ]);

        return array_merge($ctx, compact('item'));
    }

    public function test_product_crud_requires_permissions(): void
    {
        $ctx = $this->seedEcommerceContext();

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/ecommerce/products', [
                'inventory_item_id' => $ctx['item']->id,
                'title' => 'Serum',
                'price_cents' => 2500,
                'show_on_booking_carousel' => true,
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.title', 'Serum');

        $productId = $create->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->putJson("/api/v1/admin/ecommerce/products/{$productId}", [
                'title' => 'Updated Serum',
                'price_cents' => 2700,
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Serum');

        $this->withTenantAuth($ctx['token'])
            ->patchJson("/api/v1/admin/ecommerce/products/{$productId}/status", [
                'status' => 'archived',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->assertDatabaseHas('audit_logs', ['action' => 'ecommerce.product.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ecommerce.product.updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ecommerce.product.archived']);

        $this->withTenantAuth($ctx['viewerToken'])
            ->getJson('/api/v1/admin/ecommerce/products')
            ->assertForbidden();
    }

    public function test_public_catalog_and_place_order_decrements_stock(): void
    {
        $ctx = $this->seedEcommerceContext(stock: 5);

        $product = EcommerceProduct::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'inventory_item_id' => $ctx['item']->id,
            'title' => 'Serum',
            'price_cents' => 2500,
            'show_on_booking_carousel' => true,
            'status' => 'active',
        ]);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/shop/products?carousel=1&location_id='.$ctx['location']->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product->id);

        $order = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/shop/orders', [
                'location_id' => $ctx['location']->id,
                'customer_name' => 'Alex Customer',
                'customer_email' => 'alex@example.test',
                'lines' => [
                    ['ecommerce_product_id' => $product->id, 'quantity' => 2],
                ],
            ]);

        $order->assertCreated()
            ->assertJsonPath('data.status', EcommerceOrderStatus::PENDING_PICKUP)
            ->assertJsonPath('data.payment_method', 'cash_in_salon')
            ->assertJsonPath('data.total_cents', 5000);

        $this->assertDatabaseHas('inventory_levels', [
            'inventory_item_id' => $ctx['item']->id,
            'location_id' => $ctx['location']->id,
            'on_hand_quantity' => 3,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $ctx['item']->id,
            'movement_type' => InventoryMovementType::SALE,
            'reference_type' => MovementReferenceType::ECOMMERCE_ORDER,
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'ecommerce.order.placed']);

        $orderId = $order->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->patchJson("/api/v1/admin/ecommerce/orders/{$orderId}/status", [
                'status' => EcommerceOrderStatus::COLLECTED,
                'payment_status' => EcommercePaymentStatus::PAID_AT_PICKUP,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', EcommerceOrderStatus::COLLECTED)
            ->assertJsonPath('data.payment_status', EcommercePaymentStatus::PAID_AT_PICKUP);
    }

    public function test_insufficient_stock_rejected(): void
    {
        $ctx = $this->seedEcommerceContext(stock: 1);

        $product = EcommerceProduct::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'inventory_item_id' => $ctx['item']->id,
            'title' => 'Serum',
            'price_cents' => 2500,
            'show_on_booking_carousel' => true,
            'status' => 'active',
        ]);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/shop/orders', [
                'location_id' => $ctx['location']->id,
                'lines' => [
                    ['ecommerce_product_id' => $product->id, 'quantity' => 3],
                ],
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('inventory_levels', [
            'inventory_item_id' => $ctx['item']->id,
            'on_hand_quantity' => 1,
        ]);

        $this->assertDatabaseCount('ecommerce_orders', 0);
    }

    public function test_tenant_isolation(): void
    {
        $ctx = $this->seedEcommerceContext();

        $otherItem = InventoryItem::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'name' => 'Other Serum',
            'sku' => 'ECO-OTHER-001',
            'item_type' => InventoryItemType::RETAIL,
            'status' => 'active',
        ]);

        $otherLocation = Location::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'name' => 'Other HQ',
            'slug' => 'other-hq',
            'timezone' => 'Europe/London',
            'is_active' => true,
        ]);

        InventoryLevel::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'inventory_item_id' => $otherItem->id,
            'location_id' => $otherLocation->id,
            'on_hand_quantity' => 8,
        ]);

        $otherProduct = EcommerceProduct::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'inventory_item_id' => $otherItem->id,
            'title' => 'Other Product',
            'price_cents' => 1500,
            'status' => 'active',
        ]);

        EcommerceProduct::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'inventory_item_id' => $ctx['item']->id,
            'title' => 'Own Product',
            'price_cents' => 1200,
            'status' => 'active',
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/ecommerce/products/'.$otherProduct->id)
            ->assertNotFound();

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/shop/products?location_id='.$ctx['location']->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Own Product');

        EcommerceOrder::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'location_id' => $otherLocation->id,
            'order_number' => 'ECO-000001',
            'status' => EcommerceOrderStatus::PENDING_PICKUP,
            'payment_method' => 'cash_in_salon',
            'payment_status' => EcommercePaymentStatus::UNPAID,
            'subtotal_cents' => 1500,
            'total_cents' => 1500,
            'public_token' => 'other-token',
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/ecommerce/orders')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
