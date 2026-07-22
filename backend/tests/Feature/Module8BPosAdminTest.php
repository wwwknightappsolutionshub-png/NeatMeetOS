<?php

namespace Tests\Feature;

use App\Domains\Inventory\Enums\InventoryItemType;
use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Models\InventoryItem;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Pos\Enums\GiftCardStatus;
use App\Domains\Pos\Models\CommerceReceipt;
use App\Domains\Pos\Models\GiftCard;
use App\Shared\Commerce\Enums\CheckoutStatus;
use App\Shared\Commerce\Enums\SaleLineType;
use App\Shared\Commerce\Models\CommerceCheckoutLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module8BPosAdminTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    protected function posAdvancedPermissions(): array
    {
        return [
            'payments.view',
            'payments.manage',
            'inventory.view',
            'inventory.manage',
            'inventory.adjust',
            'pos.view',
            'pos.manage',
            'pos.checkout.complete',
            'pos.refund',
            'pos.checkout.reopen',
            'pos.receipt.manage',
        ];
    }

    protected function completeRetailCheckout(array $ctx, int $priceCents = 2000): array
    {
        $item = InventoryItem::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => '8B Retail',
            'item_type' => InventoryItemType::RETAIL,
            'retail_price_cents' => $priceCents,
            'status' => 'active',
        ]);

        InventoryLevel::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'inventory_item_id' => $item->id,
            'location_id' => $ctx['location']->id,
            'on_hand_quantity' => 20,
        ]);

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/pos/checkouts', ['location_id' => $ctx['location']->id])
            ->assertCreated();

        $checkoutId = $create->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/lines/retail", [
                'inventory_item_id' => $item->id,
                'quantity' => 1,
            ])
            ->assertOk();

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/payments", [
                'tenders' => [['amount_cents' => $priceCents, 'payment_method_type' => 'cash']],
            ])
            ->assertOk();

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', CheckoutStatus::COMPLETED);

        return compact('checkoutId', 'item');
    }

    public function test_partial_refund_on_completed_checkout(): void
    {
        $ctx = $this->seedTenantContext($this->posAdvancedPermissions());
        ['checkoutId' => $checkoutId] = $this->completeRetailCheckout($ctx, 3000);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/refunds", [
                'amount_cents' => 1000,
                'reason' => 'Partial refund test',
            ])
            ->assertCreated()
            ->assertJsonPath('data.checkout.refunded_total_cents', 1000)
            ->assertJsonPath('data.checkout.status', CheckoutStatus::PARTIALLY_REFUNDED);

        $this->assertDatabaseHas('audit_logs', ['action' => 'checkout.refund_created']);
    }

    public function test_retail_return_reverses_stock(): void
    {
        $ctx = $this->seedTenantContext($this->posAdvancedPermissions());
        ['checkoutId' => $checkoutId, 'item' => $item] = $this->completeRetailCheckout($ctx, 1500);

        $line = CommerceCheckoutLine::query()
            ->where('checkout_id', $checkoutId)
            ->where('line_type', SaleLineType::RETAIL_PRODUCT)
            ->firstOrFail();

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/returns", [
                'line_id' => $line->id,
                'quantity' => 1,
                'reason' => 'Customer return',
                'refund_immediately' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.lines.0.return_status', 'returned');

        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $item->id,
            'movement_type' => InventoryMovementType::ADJUSTMENT,
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'checkout.line_returned']);
    }

    public function test_reopen_completed_checkout_without_refunds(): void
    {
        $ctx = $this->seedTenantContext($this->posAdvancedPermissions());
        ['checkoutId' => $checkoutId] = $this->completeRetailCheckout($ctx, 1200);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/reopen", [
                'reason' => 'Wrong line added',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', CheckoutStatus::OPEN);

        $this->assertDatabaseHas('audit_logs', ['action' => 'checkout.reopened']);
    }

    public function test_gift_card_sale_and_redemption_flow(): void
    {
        $ctx = $this->seedTenantContext($this->posAdvancedPermissions());

        $sale = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/pos/checkouts', ['location_id' => $ctx['location']->id])
            ->assertCreated();

        $saleId = $sale->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$saleId}/lines/gift-card", [
                'amount_cents' => 5000,
                'description' => '£50 Gift Card',
            ])
            ->assertOk();

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$saleId}/payments", [
                'tenders' => [['amount_cents' => 5000, 'payment_method_type' => 'cash']],
            ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$saleId}/complete")
            ->assertOk();

        $card = GiftCard::query()->where('issued_checkout_id', $saleId)->first();
        $this->assertNotNull($card);
        $this->assertSame(5000, $card->current_balance_cents);

        $redeem = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/pos/checkouts', ['location_id' => $ctx['location']->id])
            ->assertCreated();

        $redeemId = $redeem->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$redeemId}/lines/service", [
                'description' => 'Service',
                'unit_price_cents' => 3000,
            ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$redeemId}/apply-gift-card", [
                'code' => $card->code,
            ])
            ->assertOk()
            ->assertJsonPath('data.gift_card_redemption_cents', 3000);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$redeemId}/complete")
            ->assertOk();

        $this->assertDatabaseHas('gift_cards', [
            'id' => $card->id,
            'current_balance_cents' => 2000,
            'status' => GiftCardStatus::ACTIVE,
        ]);
    }

    public function test_receipt_generation_and_resend(): void
    {
        $ctx = $this->seedTenantContext($this->posAdvancedPermissions());
        ['checkoutId' => $checkoutId] = $this->completeRetailCheckout($ctx, 1800);

        $this->assertDatabaseHas('commerce_receipts', [
            'commerce_checkout_id' => $checkoutId,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson("/api/v1/admin/pos/checkouts/{$checkoutId}/receipts")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/receipts/resend", [
                'delivery_method' => 'email',
                'delivery_target' => 'client@example.com',
            ])
            ->assertCreated();

        $this->assertSame(2, CommerceReceipt::query()->where('commerce_checkout_id', $checkoutId)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'checkout.receipt_sent']);
    }

    public function test_discount_metadata_on_line(): void
    {
        $ctx = $this->seedTenantContext($this->posAdvancedPermissions());

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/pos/checkouts', ['location_id' => $ctx['location']->id])
            ->assertCreated();

        $checkoutId = $create->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/lines/service", [
                'description' => 'Discounted service',
                'unit_price_cents' => 4000,
                'discount_cents' => 500,
                'discount_type' => 'manager_override',
                'discount_reason' => 'VIP adjustment',
            ])
            ->assertOk()
            ->assertJsonPath('data.lines.0.discount_cents', 500)
            ->assertJsonPath('data.lines.0.discount_reason', 'VIP adjustment');
    }

    public function test_cannot_reopen_after_refund(): void
    {
        $ctx = $this->seedTenantContext($this->posAdvancedPermissions());
        ['checkoutId' => $checkoutId] = $this->completeRetailCheckout($ctx, 2000);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/refunds", [
                'amount_cents' => 500,
                'reason' => 'Partial',
            ])
            ->assertCreated();

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/reopen", [
                'reason' => 'Should fail',
            ])
            ->assertStatus(422);
    }
}
