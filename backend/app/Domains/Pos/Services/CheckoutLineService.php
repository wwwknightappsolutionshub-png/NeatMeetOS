<?php

namespace App\Domains\Pos\Services;

use App\Domains\Inventory\Enums\InventoryItemType;
use App\Domains\Inventory\Models\InventoryItem;
use App\Shared\Audit\AuditLogger;
use App\Shared\Commerce\Enums\SaleLineType;
use App\Shared\Commerce\Models\CommerceCheckout;
use App\Shared\Commerce\Models\CommerceCheckoutLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutLineService
{
    public function __construct(
        private readonly PosScopeValidator $scope,
        private readonly CheckoutTotalsRecalculator $totalsRecalculator,
        private readonly AuditLogger $auditLogger,
        private readonly CheckoutDiscountService $discountService,
    ) {}

    public function addServiceLine(string $checkoutId, array $data): CommerceCheckout
    {
        $checkout = $this->scope->findCheckout($checkoutId);
        $this->scope->assertEditable($checkout);

        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $unitPrice = (int) ($data['unit_price_cents'] ?? 0);
        $discount = (int) ($data['discount_cents'] ?? 0);
        $lineTotal = ($quantity * $unitPrice) - $discount;

        if ($lineTotal < 0) {
            throw ValidationException::withMessages(['line_total_cents' => ['Line total cannot be negative.']]);
        }

        return DB::transaction(function () use ($checkout, $data, $quantity, $unitPrice, $discount, $lineTotal) {
            $discountMeta = $this->discountService->mergeDiscountMetadata(
                new CommerceCheckoutLine(['discount_cents' => 0]),
                $data,
                null,
            );

            $line = $this->createLine($checkout, array_merge([
                'line_type' => SaleLineType::APPOINTMENT_SERVICE,
                'description' => $data['description'],
                'quantity' => $quantity,
                'unit_price_cents' => $unitPrice,
                'discount_cents' => $discount,
                'line_total_cents' => $lineTotal,
                'return_status' => $this->discountService->defaultReturnStatus(),
                'reference_type' => $data['reference_type'] ?? 'booking_service',
                'reference_id' => $data['booking_service_id'] ?? $data['reference_id'] ?? null,
                'pricing_snapshot' => $data['pricing_snapshot'] ?? [
                    'booking_service_id' => $data['booking_service_id'] ?? null,
                    'service_name' => $data['description'],
                ],
                'sort_order' => $this->nextSortOrder($checkout),
            ], $discountMeta));

            $this->auditLogger->log('checkout.line_added', $checkout, null, [
                'line_id' => $line->id,
                'line_type' => $line->line_type,
            ]);

            return $this->totalsRecalculator->recalculate($checkout);
        });
    }

    public function addRetailLine(string $checkoutId, array $data): CommerceCheckout
    {
        $checkout = $this->scope->findCheckout($checkoutId);
        $this->scope->assertEditable($checkout);

        $item = InventoryItem::query()->findOrFail($data['inventory_item_id']);
        $this->scope->assertTenantModel($item);

        if ($item->status !== 'active') {
            throw ValidationException::withMessages([
                'inventory_item_id' => ['Inventory item is not available for sale.'],
            ]);
        }

        if ($item->item_type !== InventoryItemType::RETAIL) {
            throw ValidationException::withMessages([
                'inventory_item_id' => ['Item is not configured as a retail product.'],
            ]);
        }

        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $unitPrice = (int) ($data['unit_price_cents'] ?? $item->retail_price_cents ?? 0);
        $discount = (int) ($data['discount_cents'] ?? 0);
        $lineTotal = ($quantity * $unitPrice) - $discount;

        return DB::transaction(function () use ($checkout, $item, $data, $quantity, $unitPrice, $discount, $lineTotal) {
            $line = $this->createLine($checkout, [
                'line_type' => SaleLineType::RETAIL_PRODUCT,
                'description' => $data['description'] ?? $item->name,
                'quantity' => $quantity,
                'unit_price_cents' => $unitPrice,
                'discount_cents' => $discount,
                'line_total_cents' => $lineTotal,
                'reference_type' => 'inventory_item',
                'reference_id' => $item->id,
                'pricing_snapshot' => [
                    'product_id' => $item->id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                ],
                'sort_order' => $this->nextSortOrder($checkout),
            ]);

            $this->auditLogger->log('checkout.line_added', $checkout, null, [
                'line_id' => $line->id,
                'line_type' => $line->line_type,
            ]);

            return $this->totalsRecalculator->recalculate($checkout);
        });
    }

    public function addGiftCardLine(string $checkoutId, array $data): CommerceCheckout
    {
        $checkout = $this->scope->findCheckout($checkoutId);
        $this->scope->assertEditable($checkout);

        $amount = (int) ($data['amount_cents'] ?? 0);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount_cents' => ['Gift card amount must be positive.'],
            ]);
        }

        return DB::transaction(function () use ($checkout, $data, $amount) {
            $line = $this->createLine($checkout, [
                'line_type' => SaleLineType::GIFT_CARD_SALE,
                'description' => $data['description'] ?? 'Gift card',
                'quantity' => 1,
                'unit_price_cents' => $amount,
                'discount_cents' => 0,
                'line_total_cents' => $amount,
                'return_status' => $this->discountService->defaultReturnStatus(),
                'reference_type' => 'gift_card_sale',
                'reference_id' => null,
                'pricing_snapshot' => [
                    'issued_to_client_id' => $data['issued_to_client_id'] ?? $checkout->client_id,
                ],
                'sort_order' => $this->nextSortOrder($checkout),
            ]);

            $this->auditLogger->log('checkout.line_added', $checkout, null, [
                'line_id' => $line->id,
                'line_type' => SaleLineType::GIFT_CARD_SALE,
            ]);

            return $this->totalsRecalculator->recalculate($checkout);
        });
    }

    public function updateLine(string $checkoutId, string $lineId, array $data): CommerceCheckout
    {
        $checkout = $this->scope->findCheckout($checkoutId);
        $this->scope->assertEditable($checkout);

        $line = $this->scope->findLine($checkoutId, $lineId);

        if ($line->line_type === SaleLineType::DEPOSIT_CREDIT) {
            throw ValidationException::withMessages([
                'line_id' => ['Deposit credit lines cannot be edited directly. Remove and re-apply deposit credit.'],
            ]);
        }

        $quantity = max(1, (int) ($data['quantity'] ?? $line->quantity));
        $unitPrice = (int) ($data['unit_price_cents'] ?? $line->unit_price_cents);
        $discount = (int) ($data['discount_cents'] ?? $line->discount_cents ?? 0);
        $lineTotal = ($quantity * $unitPrice) - $discount;

        if ($lineTotal < 0) {
            throw ValidationException::withMessages(['line_total_cents' => ['Line total cannot be negative.']]);
        }

        return DB::transaction(function () use ($checkout, $line, $data, $quantity, $unitPrice, $discount, $lineTotal) {
            $old = $line->only(['quantity', 'unit_price_cents', 'discount_cents', 'line_total_cents']);

            $line->quantity = $quantity;
            $line->unit_price_cents = $unitPrice;
            $line->discount_cents = $discount;
            $line->line_total_cents = $lineTotal;

            if (! empty($data['description'])) {
                $line->description = $data['description'];
            }

            foreach ($this->discountService->mergeDiscountMetadata($line, $data, null) as $key => $value) {
                $line->{$key} = $value;
            }

            $line->save();

            $this->auditLogger->log('checkout.line_updated', $checkout, $old, $line->only([
                'quantity', 'unit_price_cents', 'discount_cents', 'line_total_cents',
            ]));

            return $this->totalsRecalculator->recalculate($checkout);
        });
    }

    public function removeLine(string $checkoutId, string $lineId): CommerceCheckout
    {
        $checkout = $this->scope->findCheckout($checkoutId);
        $this->scope->assertEditable($checkout);

        $line = $this->scope->findLine($checkoutId, $lineId);

        return DB::transaction(function () use ($checkout, $line) {
            $this->auditLogger->log('checkout.line_removed', $checkout, $line->only([
                'id', 'line_type', 'line_total_cents',
            ]), null);

            $line->delete();

            return $this->totalsRecalculator->recalculate($checkout);
        });
    }

    public function importLines(string $checkoutId, array $linePayloads): CommerceCheckout
    {
        $checkout = $this->scope->findCheckout($checkoutId);
        $this->scope->assertEditable($checkout);

        return DB::transaction(function () use ($checkout, $linePayloads) {
            foreach ($linePayloads as $payload) {
                $this->createLine($checkout, $payload);
            }

            return $this->totalsRecalculator->recalculate($checkout);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createLine(CommerceCheckout $checkout, array $attributes): CommerceCheckoutLine
    {
        return CommerceCheckoutLine::query()->create(array_merge([
            'tenant_id' => $checkout->tenant_id,
            'checkout_id' => $checkout->id,
        ], $attributes));
    }

    private function nextSortOrder(CommerceCheckout $checkout): int
    {
        return (int) CommerceCheckoutLine::query()
            ->where('checkout_id', $checkout->id)
            ->max('sort_order') + 1;
    }
}
