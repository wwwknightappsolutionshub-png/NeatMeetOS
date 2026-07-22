<?php

namespace App\Domains\Pos\Services;

use App\Shared\Audit\AuditLogger;
use App\Shared\Commerce\DTO\CommerceEventDto;
use App\Shared\Commerce\Enums\CommerceEventName;
use App\Shared\Commerce\Enums\DepositLifecycleState;
use App\Shared\Commerce\Enums\SaleLineType;
use App\Shared\Commerce\Models\CommerceCheckout;
use App\Shared\Commerce\Models\CommerceCheckoutLine;
use App\Shared\Commerce\Models\CommerceDepositRecord;
use App\Shared\Commerce\Services\CommerceEventPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutDepositService
{
    public function __construct(
        private readonly PosScopeValidator $scope,
        private readonly CheckoutTotalsRecalculator $totalsRecalculator,
        private readonly AuditLogger $auditLogger,
        private readonly CommerceEventPublisher $eventPublisher,
    ) {}

    /**
     * @return list<array{deposit_record_id: string, appointment_id: string, available_cents: int, collected_cents: int}>
     */
    public function availableCredit(CommerceCheckout $checkout): array
    {
        $checkout->loadMissing('appointmentLinks');

        $available = [];

        foreach ($checkout->appointmentLinks as $link) {
            $record = CommerceDepositRecord::query()
                ->where('appointment_id', $link->appointment_id)
                ->where('lifecycle_state', DepositLifecycleState::COLLECTED)
                ->whereNull('applied_checkout_id')
                ->orderByDesc('created_at')
                ->first();

            if ($record === null || $record->collected_cents <= 0) {
                continue;
            }

            if ($this->depositAlreadyAppliedInCheckout($checkout, $record->id)) {
                continue;
            }

            $available[] = [
                'deposit_record_id' => $record->id,
                'appointment_id' => $link->appointment_id,
                'available_cents' => $record->collected_cents,
                'collected_cents' => $record->collected_cents,
            ];
        }

        return $available;
    }

    public function applyDepositCredit(string $checkoutId, ?string $depositRecordId = null): CommerceCheckout
    {
        $checkout = $this->scope->findCheckout($checkoutId);
        $this->scope->assertEditable($checkout);

        $available = $this->availableCredit($checkout);

        if ($available === []) {
            throw ValidationException::withMessages([
                'deposit' => ['No collected deposit credit is available for linked appointments.'],
            ]);
        }

        $target = $depositRecordId !== null
            ? collect($available)->firstWhere('deposit_record_id', $depositRecordId)
            : $available[0];

        if ($target === null) {
            throw ValidationException::withMessages([
                'deposit_record_id' => ['Deposit record is not available for this checkout.'],
            ]);
        }

        $record = CommerceDepositRecord::query()->findOrFail($target['deposit_record_id']);
        $this->scope->assertTenantModel($record);

        if ($this->depositAlreadyAppliedInCheckout($checkout, $record->id)) {
            throw ValidationException::withMessages([
                'deposit_record_id' => ['Deposit credit has already been applied to this checkout.'],
            ]);
        }

        $checkout = $this->totalsRecalculator->recalculate($checkout);
        $creditAmount = min($record->collected_cents, max(0, $checkout->total_cents - $checkout->deposit_credit_cents));

        if ($creditAmount <= 0) {
            throw ValidationException::withMessages([
                'deposit' => ['No remaining balance to apply deposit credit against.'],
            ]);
        }

        return DB::transaction(function () use ($checkout, $record, $creditAmount) {
            CommerceCheckoutLine::query()->create([
                'tenant_id' => $checkout->tenant_id,
                'checkout_id' => $checkout->id,
                'line_type' => SaleLineType::DEPOSIT_CREDIT,
                'description' => 'Deposit credit',
                'quantity' => 1,
                'unit_price_cents' => $creditAmount,
                'discount_cents' => 0,
                'line_total_cents' => $creditAmount,
                'reference_type' => 'commerce_deposit_record',
                'reference_id' => $record->id,
                'pricing_snapshot' => [
                    'deposit_record_id' => $record->id,
                    'appointment_id' => $record->appointment_id,
                    'collected_cents' => $record->collected_cents,
                ],
                'sort_order' => (int) CommerceCheckoutLine::query()
                    ->where('checkout_id', $checkout->id)
                    ->max('sort_order') + 1,
            ]);

            $this->auditLogger->log('checkout.deposit_credit_applied', $checkout, null, [
                'deposit_record_id' => $record->id,
                'amount_cents' => $creditAmount,
            ]);

            $this->eventPublisher->publish(new CommerceEventDto(
                eventName: CommerceEventName::DEPOSIT_APPLIED,
                tenantId: $checkout->tenant_id,
                aggregateType: 'commerce_checkout',
                aggregateId: $checkout->id,
                payload: [
                    'deposit_record_id' => $record->id,
                    'amount_cents' => $creditAmount,
                ],
            ));

            return $this->totalsRecalculator->recalculate($checkout);
        });
    }

    public function removeDepositCredit(string $checkoutId): CommerceCheckout
    {
        $checkout = $this->scope->findCheckout($checkoutId);
        $this->scope->assertEditable($checkout);

        return DB::transaction(function () use ($checkout) {
            CommerceCheckoutLine::query()
                ->where('checkout_id', $checkout->id)
                ->where('line_type', SaleLineType::DEPOSIT_CREDIT)
                ->delete();

            return $this->totalsRecalculator->recalculate($checkout);
        });
    }

    private function depositAlreadyAppliedInCheckout(CommerceCheckout $checkout, string $depositRecordId): bool
    {
        return CommerceCheckoutLine::query()
            ->where('checkout_id', $checkout->id)
            ->where('line_type', SaleLineType::DEPOSIT_CREDIT)
            ->where('reference_id', $depositRecordId)
            ->exists();
    }
}
