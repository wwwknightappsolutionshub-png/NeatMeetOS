<?php

namespace App\Domains\Pos\Services;

use App\Domains\Memberships\Services\PackageEntitlementService;
use App\Domains\Payments\Models\PaymentAllocation;
use App\Domains\Payments\Models\PaymentRefund;
use App\Domains\Payments\Models\PaymentTransaction;
use App\Domains\Payments\Services\PaymentRefundService;
use App\Shared\Audit\AuditLogger;
use App\Shared\Commerce\Enums\CheckoutStatus;
use App\Shared\Commerce\Models\CommerceCheckout;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutRefundService
{
    public function __construct(
        private readonly PosScopeValidator $scope,
        private readonly PaymentRefundService $paymentRefundService,
        private readonly CheckoutMembershipApplicationService $membershipApplication,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function listForCheckout(string $checkoutId): \Illuminate\Database\Eloquent\Collection
    {
        $checkout = $this->scope->findCheckout($checkoutId);

        return PaymentRefund::query()
            ->with(['paymentTransaction', 'refundTransaction'])
            ->where('commerce_checkout_id', $checkout->id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function createRefund(string $checkoutId, array $data, ?string $teamMemberId = null): array
    {
        $checkout = $this->scope->findCheckout($checkoutId);
        $this->assertRefundable($checkout);

        $remaining = $this->refundableCents($checkout);

        if ($remaining <= 0) {
            throw ValidationException::withMessages([
                'amount_cents' => ['Nothing left to refund on this checkout.'],
            ]);
        }

        $amountCents = isset($data['amount_cents']) ? (int) $data['amount_cents'] : $remaining;

        if ($amountCents <= 0) {
            throw ValidationException::withMessages([
                'amount_cents' => ['Refund amount must be positive.'],
            ]);
        }

        if ($amountCents > $remaining) {
            throw ValidationException::withMessages([
                'amount_cents' => ['Refund exceeds remaining refundable balance.'],
            ]);
        }

        if (! empty($data['line_id'])) {
            $line = $this->scope->findLine($checkoutId, $data['line_id']);
            $lineRemaining = $line->line_total_cents - ($line->returned_subtotal_cents ?? 0);

            if ($amountCents > $lineRemaining) {
                throw ValidationException::withMessages([
                    'amount_cents' => ['Refund exceeds remaining value for this line.'],
                ]);
            }
        }

        return DB::transaction(function () use ($checkout, $data, $amountCents, $teamMemberId) {
            $transaction = $this->resolveTransaction($checkout, $data['payment_transaction_id'] ?? null, $amountCents);

            $refund = $this->paymentRefundService->createRefund($transaction, [
                'amount_cents' => $amountCents,
                'reason' => $data['reason'] ?? 'POS checkout refund',
                'notes' => $data['notes'] ?? null,
                'source' => 'pos',
                'commerce_checkout_id' => $checkout->id,
                'metadata' => [
                    'checkout_line_id' => $data['line_id'] ?? null,
                ],
            ], $teamMemberId);

            $checkout->refunded_total_cents = (int) $checkout->refunded_total_cents + $amountCents;
            $checkout->status = $checkout->refunded_total_cents >= $checkout->amount_paid_cents
                ? CheckoutStatus::FULLY_REFUNDED
                : CheckoutStatus::PARTIALLY_REFUNDED;
            $checkout->save();

            if ($checkout->refunded_total_cents >= $checkout->amount_paid_cents) {
                $this->membershipApplication->restoreAllApplications(
                    $checkout,
                    'Full checkout refund',
                    $teamMemberId,
                );
            } elseif (! empty($data['line_id'])) {
                $line = $this->scope->findLine($checkout->id, $data['line_id']);
                $lineRemaining = $line->line_total_cents - ($line->returned_subtotal_cents ?? 0);
                if ($line->client_package_redemption_id !== null && $amountCents >= $lineRemaining) {
                    app(PackageEntitlementService::class)->restoreForCheckoutLine(
                        $checkout,
                        $line,
                        'Line refund',
                        $teamMemberId,
                    );
                }
            }

            $this->auditLogger->log('checkout.refund_created', $checkout, null, [
                'payment_refund_id' => $refund->id,
                'amount_cents' => $amountCents,
            ]);

            return [
                'refund' => $refund,
                'checkout' => $this->scope->findCheckout($checkout->id),
            ];
        });
    }

    public function refundableCents(CommerceCheckout $checkout): int
    {
        return max(0, (int) $checkout->amount_paid_cents - (int) $checkout->refunded_total_cents);
    }

    private function assertRefundable(CommerceCheckout $checkout): void
    {
        if (! in_array($checkout->status, [
            CheckoutStatus::COMPLETED,
            CheckoutStatus::PARTIALLY_REFUNDED,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only completed checkouts can be refunded.'],
            ]);
        }
    }

    private function resolveTransaction(CommerceCheckout $checkout, ?string $transactionId, int $amountCents): PaymentTransaction
    {
        if ($transactionId !== null) {
            $transaction = PaymentTransaction::query()->findOrFail($transactionId);
            $this->scope->assertTenantModel($transaction);

            if ($transaction->refundableAmountCents() < $amountCents) {
                throw ValidationException::withMessages([
                    'payment_transaction_id' => ['Selected payment cannot cover this refund amount.'],
                ]);
            }

            return $transaction;
        }

        $allocations = PaymentAllocation::query()
            ->with('transaction')
            ->where('commerce_checkout_id', $checkout->id)
            ->get();

        foreach ($allocations as $allocation) {
            $txn = $allocation->transaction;

            if ($txn === null || $txn->refundableAmountCents() <= 0) {
                continue;
            }

            if ($txn->refundableAmountCents() >= $amountCents) {
                return $txn;
            }
        }

        throw ValidationException::withMessages([
            'payment' => ['No single payment transaction can cover this refund amount. Specify payment_transaction_id.'],
        ]);
    }
}
