<?php

namespace App\Domains\Ecommerce\Services;

use App\Domains\Ecommerce\Enums\EcommerceOrderStatus;
use App\Domains\Ecommerce\Enums\EcommercePaymentMethod;
use App\Domains\Ecommerce\Enums\EcommercePaymentStatus;
use App\Domains\Ecommerce\Enums\EcommerceProductStatus;
use App\Domains\Ecommerce\Models\EcommerceOrder;
use App\Domains\Ecommerce\Models\EcommerceOrderLine;
use App\Domains\Ecommerce\Models\EcommerceProduct;
use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Enums\MovementReferenceType;
use App\Domains\Inventory\Services\InventoryMovementService;
use App\Shared\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EcommerceOrderService
{
    public function __construct(
        private readonly EcommerceScopeValidator $scope,
        private readonly EcommerceOrderNumberGenerator $numberGenerator,
        private readonly PublicEcommerceService $publicCatalog,
        private readonly InventoryMovementService $movementService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(array $filters = []): Collection
    {
        $query = EcommerceOrder::query()
            ->with(['location', 'lines'])
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return $query->limit(200)->get();
    }

    public function find(string $id): EcommerceOrder
    {
        return $this->scope->findOrder($id);
    }

    /**
     * @param  array{
     *     location_id: string,
     *     customer_name?: string|null,
     *     customer_email?: string|null,
     *     customer_phone?: string|null,
     *     notes?: string|null,
     *     lines: array<int, array{ecommerce_product_id: string, quantity: float|int|string}>
     * }  $data
     */
    public function placeOrder(array $data): EcommerceOrder
    {
        if (empty($data['lines'])) {
            throw ValidationException::withMessages([
                'lines' => ['At least one line item is required.'],
            ]);
        }

        $locationId = $data['location_id'];
        $this->scope->findLocation($locationId);

        return DB::transaction(function () use ($data, $locationId) {
            $resolvedLines = [];
            $subtotalCents = 0;

            foreach ($data['lines'] as $index => $line) {
                $product = EcommerceProduct::query()
                    ->with('inventoryItem')
                    ->find($line['ecommerce_product_id'] ?? null);

                if ($product === null || $product->status !== EcommerceProductStatus::ACTIVE) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.ecommerce_product_id" => ['Product is not available.'],
                    ]);
                }

                $quantity = (float) $line['quantity'];

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.quantity" => ['Quantity must be greater than zero.'],
                    ]);
                }

                $available = $this->publicCatalog->availableQuantity(
                    $product->inventory_item_id,
                    $locationId,
                );

                if ($available < $quantity) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.quantity" => ["Insufficient stock for {$product->title}."],
                    ]);
                }

                $lineTotalCents = (int) round($product->price_cents * $quantity);
                $subtotalCents += $lineTotalCents;

                $resolvedLines[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'line_total_cents' => $lineTotalCents,
                ];
            }

            $order = EcommerceOrder::query()->create([
                'tenant_id' => $this->scope->tenantId(),
                'location_id' => $locationId,
                'order_number' => $this->numberGenerator->next($this->scope->tenantId()),
                'status' => EcommerceOrderStatus::PENDING_PICKUP,
                'payment_method' => EcommercePaymentMethod::CASH_IN_SALON,
                'payment_status' => EcommercePaymentStatus::UNPAID,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'subtotal_cents' => $subtotalCents,
                'total_cents' => $subtotalCents,
                'public_token' => Str::random(40),
            ]);

            foreach ($resolvedLines as $resolved) {
                /** @var EcommerceProduct $product */
                $product = $resolved['product'];

                EcommerceOrderLine::query()->create([
                    'tenant_id' => $this->scope->tenantId(),
                    'order_id' => $order->id,
                    'ecommerce_product_id' => $product->id,
                    'inventory_item_id' => $product->inventory_item_id,
                    'title_snapshot' => $product->title,
                    'quantity' => $resolved['quantity'],
                    'unit_price_cents' => $product->price_cents,
                    'line_total_cents' => $resolved['line_total_cents'],
                ]);

                $this->movementService->record([
                    'inventory_item_id' => $product->inventory_item_id,
                    'location_id' => $locationId,
                    'movement_type' => InventoryMovementType::SALE,
                    'quantity_delta' => -1 * $resolved['quantity'],
                    'reference_type' => MovementReferenceType::ECOMMERCE_ORDER,
                    'reference_id' => $order->id,
                    'notes' => "Ecommerce order {$order->order_number}",
                    'metadata' => [
                        'ecommerce_product_id' => $product->id,
                        'order_number' => $order->order_number,
                    ],
                ]);
            }

            $this->auditLogger->log('ecommerce.order.placed', $order, null, [
                'order_number' => $order->order_number,
                'total_cents' => $order->total_cents,
                'line_count' => count($resolvedLines),
            ]);

            return $order->fresh()->load(['location', 'lines']);
        });
    }

    public function updateStatus(EcommerceOrder $order, array $data, ?string $teamMemberId = null): EcommerceOrder
    {
        $this->scope->assertTenantModel($order);

        if (! $order->isPendingPickup()) {
            throw ValidationException::withMessages([
                'status' => ['Only pending pickup orders can be updated.'],
            ]);
        }

        $newStatus = $data['status'];
        $old = $order->only(['status', 'payment_status']);

        return DB::transaction(function () use ($order, $data, $newStatus, $old, $teamMemberId) {
            if ($newStatus === EcommerceOrderStatus::CANCELLED) {
                $order->load('lines');

                foreach ($order->lines as $line) {
                    $this->movementService->record([
                        'inventory_item_id' => $line->inventory_item_id,
                        'location_id' => $order->location_id,
                        'movement_type' => InventoryMovementType::ADJUSTMENT,
                        'quantity_delta' => (float) $line->quantity,
                        'reference_type' => MovementReferenceType::ECOMMERCE_ORDER,
                        'reference_id' => $order->id,
                        'notes' => "Ecommerce order {$order->order_number} cancelled — stock restored",
                        'metadata' => [
                            'ecommerce_order_id' => $order->id,
                            'restoration' => true,
                        ],
                    ], $teamMemberId);
                }
            }

            $order->status = $newStatus;

            if ($newStatus === EcommerceOrderStatus::COLLECTED && ! empty($data['payment_status'])) {
                if ($data['payment_status'] === EcommercePaymentStatus::PAID_AT_PICKUP) {
                    $order->payment_status = EcommercePaymentStatus::PAID_AT_PICKUP;
                }
            }

            $order->save();

            $this->auditLogger->log('ecommerce.order.status_updated', $order, $old, $order->only([
                'status', 'payment_status',
            ]));

            return $order->fresh()->load(['location', 'lines']);
        });
    }
}
