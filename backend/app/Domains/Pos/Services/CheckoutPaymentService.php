<?php

namespace App\Domains\Pos\Services;

use App\Domains\Payments\Enums\PaymentTransactionType;
use App\Domains\Payments\Models\PaymentAllocation;
use App\Domains\Payments\Services\PaymentTransactionService;
use App\Shared\Audit\AuditLogger;
use App\Shared\Commerce\Enums\PaymentAllocationType;
use App\Shared\Commerce\Models\CommerceCheckout;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutPaymentService
{
    public function __construct(
        private readonly PosScopeValidator $scope,
        private readonly PaymentTransactionService $paymentTransactionService,
        private readonly CheckoutTotalsRecalculator $totalsRecalculator,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return list<\App\Domains\Payments\Models\PaymentTransaction>
     */
    public function listPayments(string $checkoutId): array
    {
        $checkout = $this->scope->findCheckout($checkoutId);

        return PaymentAllocation::query()
            ->with(['transaction'])
            ->where('commerce_checkout_id', $checkout->id)
            ->get()
            ->pluck('transaction')
            ->unique('id')
            ->values()
            ->all();
    }

    /**
     * @param  list<array{amount_cents: int, payment_method_type: string, payment_method_label?: string|null, provider?: string|null}>  $tenders
     */
    public function recordPayments(string $checkoutId, array $tenders, ?string $teamMemberId = null): CommerceCheckout
    {
        $checkout = $this->scope->findCheckout($checkoutId);
        $this->scope->assertEditable($checkout);

        if ($tenders === []) {
            throw ValidationException::withMessages([
                'tenders' => ['At least one tender is required.'],
            ]);
        }

        return DB::transaction(function () use ($checkout, $tenders, $teamMemberId) {
            foreach ($tenders as $tender) {
                $amount = (int) ($tender['amount_cents'] ?? 0);

                if ($amount <= 0) {
                    throw ValidationException::withMessages([
                        'amount_cents' => ['Tender amount must be positive.'],
                    ]);
                }

                $methodType = $tender['payment_method_type'] ?? 'cash';

                $transaction = match ($methodType) {
                    'payment_link' => tap(
                        $this->paymentTransactionService->createPaymentLink([
                            'transaction_type' => PaymentTransactionType::SALE,
                            'amount_cents' => $amount,
                            'client_id' => $checkout->client_id,
                            'location_id' => $checkout->location_id,
                            'payment_method_type' => $methodType,
                            'payment_method_label' => $tender['payment_method_label'] ?? 'Payment link',
                            'allocations' => [[
                                'allocation_type' => PaymentAllocationType::CHECKOUT_SALE,
                                'amount_cents' => $amount,
                                'commerce_checkout_id' => $checkout->id,
                            ]],
                        ], $teamMemberId),
                        fn ($tx) => $this->paymentTransactionService->markSucceeded($tx, $teamMemberId),
                    ),
                    default => $this->paymentTransactionService->createManual([
                        'transaction_type' => PaymentTransactionType::SALE,
                        'amount_cents' => $amount,
                        'client_id' => $checkout->client_id,
                        'location_id' => $checkout->location_id,
                        'payment_method_type' => $methodType,
                        'payment_method_label' => $tender['payment_method_label'] ?? ucfirst(str_replace('_', ' ', $methodType)),
                        'allocations' => [[
                            'allocation_type' => PaymentAllocationType::CHECKOUT_SALE,
                            'amount_cents' => $amount,
                            'commerce_checkout_id' => $checkout->id,
                        ]],
                    ], $teamMemberId),
                };

                $this->auditLogger->log('checkout.payment_recorded', $checkout, null, [
                    'payment_transaction_id' => $transaction->id,
                    'amount_cents' => $amount,
                    'payment_method_type' => $methodType,
                ]);
            }

            return $this->totalsRecalculator->recalculate($checkout);
        });
    }
}
