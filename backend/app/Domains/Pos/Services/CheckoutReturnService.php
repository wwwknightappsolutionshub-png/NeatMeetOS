<?php

namespace App\Domains\Pos\Services;

use App\Domains\Inventory\Services\InventoryConsumptionService;
use App\Shared\Audit\AuditLogger;
use App\Shared\Commerce\DTO\InventoryConsumptionRequestDto;
use App\Shared\Commerce\Enums\CheckoutStatus;
use App\Shared\Commerce\Enums\InventoryConsumptionType;
use App\Shared\Commerce\Enums\SaleLineType;
use App\Shared\Commerce\Models\CommerceCheckout;
use App\Domains\Pos\Enums\CheckoutLineReturnStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutReturnService
{
    public function __construct(
        private readonly PosScopeValidator $scope,
        private readonly CheckoutRefundService $refundService,
        private readonly InventoryConsumptionService $inventoryConsumption,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function processReturn(string $checkoutId, array $data, ?string $teamMemberId = null): CommerceCheckout
    {
        $checkout = $this->scope->findCheckout($checkoutId);

        if ($checkout->status !== CheckoutStatus::COMPLETED && $checkout->status !== CheckoutStatus::PARTIALLY_REFUNDED) {
            throw ValidationException::withMessages([
                'status' => ['Returns are only supported on completed checkouts.'],
            ]);
        }

        $line = $this->scope->findLine($checkoutId, $data['line_id']);

        if ($line->line_type !== SaleLineType::RETAIL_PRODUCT) {
            throw ValidationException::withMessages([
                'line_id' => ['Only retail lines support stock returns.'],
            ]);
        }

        $returnQty = (float) ($data['quantity'] ?? 0);

        if ($returnQty <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Return quantity must be positive.'],
            ]);
        }

        $alreadyReturned = (float) ($line->returned_quantity ?? 0);
        $available = (float) $line->quantity - $alreadyReturned;

        if ($returnQty > $available) {
            throw ValidationException::withMessages([
                'quantity' => ['Return quantity exceeds remaining sold quantity.'],
            ]);
        }

        $unitNet = (int) floor($line->line_total_cents / max(1, (int) $line->quantity));
        $returnSubtotal = (int) round($unitNet * $returnQty);

        $remainingLineValue = $line->line_total_cents - ($line->returned_subtotal_cents ?? 0);

        if ($returnSubtotal > $remainingLineValue) {
            $returnSubtotal = $remainingLineValue;
        }

        return DB::transaction(function () use ($checkout, $line, $data, $returnQty, $returnSubtotal, $teamMemberId) {
            $productId = $line->reference_id ?? ($line->pricing_snapshot['product_id'] ?? null);

            if ($productId === null || $checkout->location_id === null) {
                throw ValidationException::withMessages([
                    'line_id' => ['Retail line is missing inventory reference for reversal.'],
                ]);
            }

            $result = $this->inventoryConsumption->execute([
                new InventoryConsumptionRequestDto(
                    checkoutId: $checkout->id,
                    checkoutLineId: $line->id,
                    consumptionType: InventoryConsumptionType::REVERSAL,
                    productId: $productId,
                    quantity: (string) $returnQty,
                    locationId: $checkout->location_id,
                    recipeSnapshot: ['return_reason' => $data['reason'] ?? null],
                ),
            ], $teamMemberId);

            if ($result['failures'] !== []) {
                throw ValidationException::withMessages([
                    'inventory' => ['Stock reversal failed: '.($result['failures'][0]['reason'] ?? 'unknown error')],
                ]);
            }

            $line->returned_quantity = (float) ($line->returned_quantity ?? 0) + $returnQty;
            $line->returned_subtotal_cents = (int) ($line->returned_subtotal_cents ?? 0) + $returnSubtotal;
            $line->return_status = $line->returned_quantity >= (float) $line->quantity
                ? CheckoutLineReturnStatus::RETURNED
                : CheckoutLineReturnStatus::PARTIALLY_RETURNED;
            $line->save();

            if (! empty($data['refund_immediately']) && $returnSubtotal > 0) {
                $this->refundService->createRefund($checkout->id, [
                    'amount_cents' => $returnSubtotal,
                    'reason' => $data['reason'] ?? 'Retail return',
                    'notes' => $data['notes'] ?? null,
                ], $teamMemberId);
            }

            $this->auditLogger->log('checkout.line_returned', $checkout, null, [
                'line_id' => $line->id,
                'returned_quantity' => $returnQty,
                'returned_subtotal_cents' => $returnSubtotal,
            ]);

            return $this->scope->findCheckout($checkout->id);
        });
    }
}
