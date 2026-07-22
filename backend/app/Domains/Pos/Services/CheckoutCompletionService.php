<?php

namespace App\Domains\Pos\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Inventory\Models\ServiceInventoryConsumptionRule;
use App\Domains\Inventory\Services\InventoryConsumptionService;
use App\Domains\Memberships\Services\PackageEntitlementService;
use App\Shared\Audit\AuditLogger;
use App\Shared\Commerce\Assemblers\InventoryConsumptionRequestBuilder;
use App\Shared\Commerce\Contracts\StockConsumptionRequestContract;
use App\Shared\Commerce\DTO\CommerceEventDto;
use App\Shared\Commerce\DTO\InventoryConsumptionRequestDto;
use App\Shared\Commerce\Enums\BillingSettlementStatus;
use App\Shared\Commerce\Enums\CheckoutStatus;
use App\Shared\Commerce\Enums\CommerceEventName;
use App\Shared\Commerce\Enums\DepositLifecycleState;
use App\Shared\Commerce\Enums\InventoryConsumptionType;
use App\Shared\Commerce\Enums\SaleLineType;
use App\Shared\Commerce\Models\CommerceCheckout;
use App\Shared\Commerce\Models\CommerceDepositRecord;
use App\Shared\Commerce\Services\CommerceEventPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutCompletionService
{
    public function __construct(
        private readonly PosScopeValidator $scope,
        private readonly CheckoutTotalsRecalculator $totalsRecalculator,
        private readonly StockConsumptionRequestContract $consumptionRequestBuilder,
        private readonly InventoryConsumptionService $inventoryConsumption,
        private readonly GiftCardService $giftCardService,
        private readonly GiftCardRedemptionService $giftCardRedemptionService,
        private readonly PackageEntitlementService $packageEntitlement,
        private readonly ReceiptService $receiptService,
        private readonly AuditLogger $auditLogger,
        private readonly CommerceEventPublisher $eventPublisher,
    ) {}

    public function complete(string $checkoutId, ?string $teamMemberId = null): CommerceCheckout
    {
        $checkout = $this->scope->findCheckout($checkoutId);
        $this->scope->assertEditable($checkout);

        $checkout = $this->totalsRecalculator->recalculate($checkout);

        if ($checkout->lines()->count() === 0) {
            throw ValidationException::withMessages([
                'checkout' => ['Checkout must have at least one line before completion.'],
            ]);
        }

        if ($checkout->amount_due_cents > 0) {
            throw ValidationException::withMessages([
                'amount_due_cents' => ['Checkout cannot be completed while an amount remains due.'],
            ]);
        }

        return DB::transaction(function () use ($checkout, $teamMemberId) {
            $checkout->status = CheckoutStatus::COMPLETED;
            $checkout->completed_at = now();
            $checkout->save();

            $this->finalizeDepositCredits($checkout);
            $this->packageEntitlement->finalizeForCheckout($checkout, $teamMemberId);
            $this->giftCardRedemptionService->finalizeForCheckout($checkout, $teamMemberId);
            $this->giftCardService->issueFromCheckout($checkout, $teamMemberId);
            $this->settleLinkedAppointments($checkout);
            $this->executeInventoryConsumption($checkout, $teamMemberId);
            $this->receiptService->generateForCompletedCheckout($checkout);

            $this->auditLogger->log('checkout.completed', $checkout, null, [
                'total_cents' => $checkout->total_cents,
                'amount_paid_cents' => $checkout->amount_paid_cents,
            ]);

            $this->eventPublisher->publish(new CommerceEventDto(
                eventName: CommerceEventName::CHECKOUT_COMPLETED,
                tenantId: $checkout->tenant_id,
                aggregateType: 'commerce_checkout',
                aggregateId: $checkout->id,
                payload: [
                    'checkout_number' => $checkout->checkout_number,
                    'total_cents' => $checkout->total_cents,
                ],
            ));

            return $this->scope->findCheckout($checkout->id);
        });
    }

    private function finalizeDepositCredits(CommerceCheckout $checkout): void
    {
        foreach ($checkout->lines as $line) {
            if ($line->line_type !== SaleLineType::DEPOSIT_CREDIT || $line->reference_id === null) {
                continue;
            }

            $record = CommerceDepositRecord::query()->find($line->reference_id);

            if ($record === null) {
                continue;
            }

            if ($record->applied_checkout_id !== null && $record->applied_checkout_id !== $checkout->id) {
                throw ValidationException::withMessages([
                    'deposit' => ['Deposit credit has already been applied to another checkout.'],
                ]);
            }

            $record->applied_checkout_id = $checkout->id;
            $record->lifecycle_state = DepositLifecycleState::APPLIED_TO_CHECKOUT;
            $record->save();
        }
    }

    private function settleLinkedAppointments(CommerceCheckout $checkout): void
    {
        $checkout->loadMissing('appointmentLinks.appointment');

        foreach ($checkout->appointmentLinks as $link) {
            $appointment = $link->appointment;

            if ($appointment === null) {
                continue;
            }

            $appointment->billing_settlement_status = BillingSettlementStatus::SETTLED;
            $appointment->save();
        }
    }

    private function executeInventoryConsumption(CommerceCheckout $checkout, ?string $teamMemberId): void
    {
        if ($checkout->location_id === null) {
            return;
        }

        $linePayloads = $checkout->lines->map(fn ($line) => [
            'checkout_line_id' => $line->id,
            'line_type' => $line->line_type,
            'reference_id' => $line->reference_id,
            'quantity' => $line->quantity,
            'pricing_snapshot' => $line->pricing_snapshot ?? [],
        ])->all();

        $requests = $this->consumptionRequestBuilder->buildFromCheckoutLines(
            $checkout->id,
            $checkout->location_id,
            $linePayloads,
        );

        $requests = array_merge($requests, $this->buildServiceConsumptionFromRules($checkout));

        if ($requests === []) {
            return;
        }

        $result = $this->inventoryConsumption->execute($requests, $teamMemberId);

        if ($result['failures'] !== []) {
            throw ValidationException::withMessages([
                'inventory' => ['Inventory consumption failed: '.($result['failures'][0]['reason'] ?? 'unknown error')],
            ]);
        }
    }

    /**
     * @return list<InventoryConsumptionRequestDto>
     */
    private function buildServiceConsumptionFromRules(CommerceCheckout $checkout): array
    {
        $requests = [];

        foreach ($checkout->lines as $line) {
            if ($line->line_type !== SaleLineType::APPOINTMENT_SERVICE) {
                continue;
            }

            $bookingServiceId = $line->pricing_snapshot['booking_service_id'] ?? null;

            if ($bookingServiceId === null) {
                continue;
            }

            $rules = ServiceInventoryConsumptionRule::query()
                ->where('booking_service_id', $bookingServiceId)
                ->where('is_active', true)
                ->get();

            foreach ($rules as $rule) {
                $requests[] = new InventoryConsumptionRequestDto(
                    checkoutId: $checkout->id,
                    checkoutLineId: $line->id,
                    consumptionType: InventoryConsumptionType::PROFESSIONAL_USE,
                    productId: $rule->inventory_item_id,
                    quantity: (string) $rule->quantity_required,
                    locationId: $checkout->location_id,
                    appointmentServiceLineId: $line->reference_id,
                    recipeSnapshot: [
                        'consumption_rule_id' => $rule->id,
                        'booking_service_id' => $bookingServiceId,
                    ],
                );
            }
        }

        return $requests;
    }
}
