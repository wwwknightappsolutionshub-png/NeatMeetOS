<?php

namespace App\Domains\Payments\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Payments\Enums\PaymentDirection;
use App\Domains\Payments\Enums\PaymentTransactionStatus;
use App\Domains\Payments\Enums\PaymentTransactionType;
use App\Domains\Payments\Models\PaymentRefund;
use App\Domains\Payments\Models\PaymentTransaction;
use App\Shared\Audit\AuditLogger;
use App\Shared\Commerce\DTO\CommerceEventDto;
use App\Shared\Commerce\Enums\CommerceEventName;
use App\Shared\Commerce\Enums\DepositLifecycleState;
use App\Shared\Commerce\Enums\PaymentAllocationType;
use App\Shared\Commerce\Models\CommerceDepositRecord;
use App\Shared\Commerce\Services\CommerceEventPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentRefundService
{
    public function __construct(
        private readonly PaymentScopeValidator $scope,
        private readonly PaymentTransactionService $transactionService,
        private readonly AuditLogger $auditLogger,
        private readonly CommerceEventPublisher $eventPublisher,
    ) {}

    public function listForTransaction(string $transactionId): \Illuminate\Database\Eloquent\Collection
    {
        $transaction = $this->scope->findTransaction($transactionId);

        return $transaction->refunds()->orderByDesc('created_at')->get();
    }

    public function createRefund(PaymentTransaction $transaction, array $data, ?string $teamMemberId = null): PaymentRefund
    {
        $this->scope->assertTenantModel($transaction);

        $amountCents = $data['amount_cents'] ?? $transaction->refundableAmountCents();

        if ($amountCents <= 0) {
            throw ValidationException::withMessages([
                'amount_cents' => ['Nothing refundable on this transaction.'],
            ]);
        }

        if ($amountCents > $transaction->refundableAmountCents()) {
            throw ValidationException::withMessages([
                'amount_cents' => ['Refund exceeds refundable balance.'],
            ]);
        }

        return DB::transaction(function () use ($transaction, $data, $amountCents, $teamMemberId) {
            $refundTxn = $this->transactionService->createManual([
                'location_id' => $transaction->location_id,
                'client_id' => $transaction->client_id,
                'appointment_id' => $transaction->appointment_id,
                'transaction_type' => PaymentTransactionType::REFUND,
                'direction' => PaymentDirection::OUTBOUND,
                'status' => PaymentTransactionStatus::SUCCEEDED,
                'amount_cents' => $amountCents,
                'payment_method_type' => $transaction->payment_method_type,
                'payment_method_label' => $transaction->payment_method_label,
                'metadata' => ['source_transaction_id' => $transaction->id],
                'allocations' => [[
                    'allocation_type' => PaymentAllocationType::REFUND,
                    'amount_cents' => $amountCents,
                    'appointment_id' => $transaction->appointment_id,
                    'notes' => $data['reason'] ?? null,
                ]],
            ], $teamMemberId);

            $refund = PaymentRefund::query()->create([
                'tenant_id' => $transaction->tenant_id,
                'payment_transaction_id' => $transaction->id,
                'refund_transaction_id' => $refundTxn->id,
                'amount_cents' => $amountCents,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'source' => $data['source'] ?? null,
                'commerce_checkout_id' => $data['commerce_checkout_id'] ?? null,
                'status' => PaymentRefund::STATUS_SUCCEEDED,
                'processed_at' => now(),
                'created_by_team_member_id' => $teamMemberId,
                'metadata' => $data['metadata'] ?? null,
            ]);

            $newRefundedTotal = $transaction->refunds()->where('status', PaymentRefund::STATUS_SUCCEEDED)->sum('amount_cents');
            $transaction->status = $newRefundedTotal >= $transaction->amount_cents
                ? PaymentTransactionStatus::REFUNDED
                : PaymentTransactionStatus::PARTIALLY_REFUNDED;
            $transaction->save();

            if ($transaction->transaction_type === PaymentTransactionType::DEPOSIT && $transaction->appointment_id) {
                $this->syncDepositRefund($transaction, $refundTxn, $amountCents);
            }

            $this->auditLogger->log('payment.refund_created', $refund, null, [
                'payment_transaction_id' => $transaction->id,
                'amount_cents' => $amountCents,
            ]);

            $this->eventPublisher->publish(new CommerceEventDto(
                eventName: CommerceEventName::REFUND_COMPLETED,
                tenantId: $transaction->tenant_id,
                aggregateType: 'payment_refund',
                aggregateId: $refund->id,
                payload: [
                    'payment_transaction_id' => $transaction->id,
                    'refund_transaction_id' => $refundTxn->id,
                    'amount_cents' => $amountCents,
                ],
            ));

            return $refund->load(['paymentTransaction', 'refundTransaction']);
        });
    }

    public function refundAppointmentDeposit(string $appointmentId, array $data, ?string $teamMemberId = null): array
    {
        $appointment = $this->scope->findAppointment($appointmentId);

        $record = CommerceDepositRecord::query()
            ->where('appointment_id', $appointmentId)
            ->where('lifecycle_state', DepositLifecycleState::COLLECTED)
            ->orderByDesc('created_at')
            ->first();

        if ($record === null || $record->payment_transaction_id === null) {
            throw ValidationException::withMessages([
                'deposit' => ['No collected deposit to refund.'],
            ]);
        }

        $transaction = $this->scope->findTransaction($record->payment_transaction_id);
        $refund = $this->createRefund($transaction, [
            'amount_cents' => $data['amount_cents'] ?? $record->collected_cents,
            'reason' => $data['reason'] ?? 'Deposit refund',
        ], $teamMemberId);

        return [
            'refund' => $refund,
            'deposit_record' => $record->fresh(),
            'appointment' => $appointment->fresh(),
        ];
    }

    private function syncDepositRefund(PaymentTransaction $source, PaymentTransaction $refundTxn, int $amountCents): void
    {
        $record = CommerceDepositRecord::query()
            ->where('payment_transaction_id', $source->id)
            ->first();

        if ($record === null) {
            return;
        }

        $record->update([
            'lifecycle_state' => DepositLifecycleState::REFUNDED,
            'refunded_payment_transaction_id' => $refundTxn->id,
            'refunded_at' => now(),
            'booking_deposit_status' => Appointment::DEPOSIT_PENDING,
        ]);

        if ($record->appointment_id) {
            Appointment::query()
                ->where('id', $record->appointment_id)
                ->update(['deposit_status' => Appointment::DEPOSIT_PENDING]);
        }

        $this->eventPublisher->publish(new CommerceEventDto(
            eventName: CommerceEventName::DEPOSIT_REFUNDED,
            tenantId: $record->tenant_id,
            aggregateType: 'commerce_deposit_record',
            aggregateId: $record->id,
            payload: [
                'appointment_id' => $record->appointment_id,
                'refund_transaction_id' => $refundTxn->id,
                'amount_cents' => $amountCents,
            ],
        ));
    }
}
