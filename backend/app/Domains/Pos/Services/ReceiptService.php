<?php

namespace App\Domains\Pos\Services;

use App\Domains\Pos\Enums\ReceiptDeliveryMethod;
use App\Domains\Pos\Enums\ReceiptDeliveryStatus;
use App\Domains\Pos\Models\CommerceReceipt;
use App\Shared\Audit\AuditLogger;
use App\Shared\Commerce\Models\CommerceCheckout;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceiptService
{
    public function __construct(
        private readonly PosScopeValidator $scope,
        private readonly ReceiptNumberGenerator $numberGenerator,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function listForCheckout(string $checkoutId): \Illuminate\Database\Eloquent\Collection
    {
        $checkout = $this->scope->findCheckout($checkoutId);

        return CommerceReceipt::query()
            ->where('commerce_checkout_id', $checkout->id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function generateForCompletedCheckout(CommerceCheckout $checkout): CommerceReceipt
    {
        $payload = $this->buildPayloadSnapshot($checkout);

        return CommerceReceipt::query()->create([
            'tenant_id' => $checkout->tenant_id,
            'commerce_checkout_id' => $checkout->id,
            'receipt_number' => $this->numberGenerator->next($checkout->tenant_id),
            'delivery_method' => ReceiptDeliveryMethod::MANUAL,
            'delivery_status' => ReceiptDeliveryStatus::PENDING,
            'payload_snapshot' => $payload,
        ]);
    }

    public function resend(string $checkoutId, array $data, ?string $teamMemberId = null): CommerceReceipt
    {
        $checkout = $this->scope->findCheckout($checkoutId);

        if ($checkout->completed_at === null) {
            throw ValidationException::withMessages([
                'checkout' => ['Receipts can only be sent for completed checkouts.'],
            ]);
        }

        $method = $data['delivery_method'] ?? ReceiptDeliveryMethod::EMAIL;
        $target = $data['delivery_target'] ?? null;

        if (! in_array($method, [ReceiptDeliveryMethod::PRINT, ReceiptDeliveryMethod::MANUAL], true) && empty($target)) {
            throw ValidationException::withMessages([
                'delivery_target' => ['Delivery target is required for email/SMS receipts.'],
            ]);
        }

        return DB::transaction(function () use ($checkout, $method, $target, $teamMemberId) {
            $receipt = CommerceReceipt::query()->create([
                'tenant_id' => $checkout->tenant_id,
                'commerce_checkout_id' => $checkout->id,
                'receipt_number' => $this->numberGenerator->next($checkout->tenant_id),
                'delivery_method' => $method,
                'delivery_status' => ReceiptDeliveryStatus::SENT,
                'delivery_target' => $target,
                'sent_at' => now(),
                'payload_snapshot' => $this->buildPayloadSnapshot($checkout),
            ]);

            $checkout->receipt_last_sent_at = now();
            $checkout->receipt_last_delivery_method = $method;
            $checkout->receipt_last_delivery_status = ReceiptDeliveryStatus::SENT;
            $checkout->save();

            $this->auditLogger->log('checkout.receipt_sent', $checkout, null, [
                'receipt_id' => $receipt->id,
                'delivery_method' => $method,
                'delivery_target' => $target,
            ]);

            return $receipt;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayloadSnapshot(CommerceCheckout $checkout): array
    {
        $checkout->loadMissing(['lines', 'client', 'location']);

        return [
            'checkout_number' => $checkout->checkout_number,
            'completed_at' => $checkout->completed_at?->toIso8601String(),
            'client' => $checkout->client?->only(['id', 'first_name', 'last_name']),
            'location' => $checkout->location?->only(['id', 'name']),
            'lines' => $checkout->lines->map(fn ($l) => [
                'description' => $l->description,
                'line_type' => $l->line_type,
                'quantity' => $l->quantity,
                'line_total_cents' => $l->line_total_cents,
            ])->all(),
            'subtotal_cents' => $checkout->subtotal_cents,
            'discount_cents' => $checkout->discount_cents,
            'deposit_credit_cents' => $checkout->deposit_credit_cents,
            'gift_card_redemption_cents' => $checkout->gift_card_redemption_cents,
            'total_cents' => $checkout->total_cents,
            'amount_paid_cents' => $checkout->amount_paid_cents,
            'refunded_total_cents' => $checkout->refunded_total_cents,
        ];
    }
}
