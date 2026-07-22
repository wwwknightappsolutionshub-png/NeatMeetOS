<?php

namespace App\Domains\Payments\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Payments\Enums\PaymentDirection;
use App\Domains\Payments\Enums\PaymentProvider;
use App\Domains\Payments\Enums\PaymentTransactionStatus;
use App\Domains\Payments\Enums\PaymentTransactionType;
use App\Shared\Audit\AuditLogger;
use App\Shared\Commerce\Contracts\DepositSettlementContract;
use App\Shared\Commerce\DTO\CommerceEventDto;
use App\Shared\Commerce\Enums\CommerceEventName;
use App\Shared\Commerce\Enums\DepositLifecycleState;
use App\Shared\Commerce\Enums\PaymentAllocationType;
use App\Shared\Commerce\Models\CommerceDepositRecord;
use App\Shared\Commerce\Services\CommerceEventPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DepositPaymentService
{
    public function __construct(
        private readonly PaymentScopeValidator $scope,
        private readonly PaymentTransactionService $transactionService,
        private readonly DepositSettlementContract $depositSettlement,
        private readonly AuditLogger $auditLogger,
        private readonly CommerceEventPublisher $eventPublisher,
    ) {}

    public function inspect(string $appointmentId): array
    {
        $appointment = $this->scope->findAppointment($appointmentId);
        $contract = $this->depositSettlement->resolveForAppointment($appointmentId);

        $record = CommerceDepositRecord::query()
            ->where('appointment_id', $appointmentId)
            ->orderByDesc('created_at')
            ->with(['appliedCheckout'])
            ->first();

        return [
            'appointment' => [
                'id' => $appointment->id,
                'deposit_status' => $appointment->deposit_status,
                'deposit_required_cents' => $appointment->deposit_required_cents,
                'deposit_rule_snapshot' => $appointment->deposit_rule_snapshot,
            ],
            'deposit_contract' => $contract->toArray(),
            'deposit_record' => $record?->toArray(),
        ];
    }

    public function recordPayment(array $data, ?string $teamMemberId = null): array
    {
        $appointment = $this->scope->findAppointment($data['appointment_id']);

        if (! in_array($appointment->deposit_status, [Appointment::DEPOSIT_PENDING, Appointment::DEPOSIT_FAILED], true)) {
            throw ValidationException::withMessages([
                'deposit_status' => ['Deposit is not awaiting payment.'],
            ]);
        }

        $amountCents = $data['amount_cents'] ?? $appointment->deposit_required_cents;

        if ($amountCents === null || $amountCents <= 0) {
            throw ValidationException::withMessages([
                'amount_cents' => ['Deposit amount is required.'],
            ]);
        }

        return DB::transaction(function () use ($appointment, $data, $amountCents, $teamMemberId) {
            $record = $this->ensureDepositRecord($appointment);

            $transaction = $this->transactionService->createManual([
                'location_id' => $appointment->location_id,
                'client_id' => $appointment->client_id,
                'appointment_id' => $appointment->id,
                'transaction_type' => PaymentTransactionType::DEPOSIT,
                'direction' => PaymentDirection::INBOUND,
                'status' => PaymentTransactionStatus::SUCCEEDED,
                'amount_cents' => $amountCents,
                'payment_method_type' => $data['payment_method_type'] ?? null,
                'payment_method_label' => $data['payment_method_label'] ?? null,
                'external_reference' => $data['external_reference'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'allocations' => [[
                    'allocation_type' => PaymentAllocationType::DEPOSIT,
                    'amount_cents' => $amountCents,
                    'appointment_id' => $appointment->id,
                    'commerce_deposit_record_id' => $record->id,
                ]],
            ], $teamMemberId);

            $record->update([
                'collected_cents' => $amountCents,
                'lifecycle_state' => DepositLifecycleState::COLLECTED,
                'payment_transaction_id' => $transaction->id,
                'collected_at' => now(),
                'booking_deposit_status' => Appointment::DEPOSIT_SATISFIED,
                'failure_code' => null,
                'failure_message' => null,
            ]);

            $appointment->deposit_status = Appointment::DEPOSIT_SATISFIED;
            $appointment->save();

            $this->auditLogger->log('payment.deposit_recorded', $record, null, [
                'payment_transaction_id' => $transaction->id,
                'amount_cents' => $amountCents,
            ]);

            $this->eventPublisher->publish(new CommerceEventDto(
                eventName: CommerceEventName::DEPOSIT_COLLECTED,
                tenantId: $appointment->tenant_id,
                aggregateType: 'commerce_deposit_record',
                aggregateId: $record->id,
                payload: [
                    'appointment_id' => $appointment->id,
                    'payment_transaction_id' => $transaction->id,
                    'collected_cents' => $amountCents,
                ],
            ));

            return [
                'deposit_record' => $record->fresh(),
                'payment_transaction' => $transaction,
                'appointment' => $appointment->fresh(),
            ];
        });
    }

    public function waive(string $appointmentId, ?string $notes = null, ?string $teamMemberId = null): array
    {
        $appointment = $this->scope->findAppointment($appointmentId);

        if ($appointment->deposit_status === Appointment::DEPOSIT_NOT_REQUIRED) {
            throw ValidationException::withMessages([
                'deposit_status' => ['No deposit requirement on this appointment.'],
            ]);
        }

        return DB::transaction(function () use ($appointment, $notes) {
            $record = $this->ensureDepositRecord($appointment);

            $record->update([
                'lifecycle_state' => DepositLifecycleState::WAIVED,
                'booking_deposit_status' => Appointment::DEPOSIT_WAIVED,
                'manual_notes' => $notes,
            ]);

            $appointment->deposit_status = Appointment::DEPOSIT_WAIVED;
            $appointment->save();

            $this->auditLogger->log('payment.deposit_waived', $record, null, ['notes' => $notes]);

            $this->eventPublisher->publish(new CommerceEventDto(
                eventName: CommerceEventName::DEPOSIT_WAIVED,
                tenantId: $appointment->tenant_id,
                aggregateType: 'commerce_deposit_record',
                aggregateId: $record->id,
                payload: ['appointment_id' => $appointment->id, 'notes' => $notes],
            ));

            return [
                'deposit_record' => $record->fresh(),
                'appointment' => $appointment->fresh(),
            ];
        });
    }

    public function markFailed(string $appointmentId, ?string $reason = null): array
    {
        $appointment = $this->scope->findAppointment($appointmentId);
        $record = $this->ensureDepositRecord($appointment);

        $record->update([
            'lifecycle_state' => DepositLifecycleState::REQUIRED,
            'failure_message' => $reason,
            'booking_deposit_status' => Appointment::DEPOSIT_FAILED,
        ]);

        $appointment->deposit_status = Appointment::DEPOSIT_FAILED;
        $appointment->save();

        $this->eventPublisher->publish(new CommerceEventDto(
            eventName: CommerceEventName::DEPOSIT_FAILED,
            tenantId: $appointment->tenant_id,
            aggregateType: 'commerce_deposit_record',
            aggregateId: $record->id,
            payload: ['appointment_id' => $appointment->id, 'reason' => $reason],
        ));

        return ['deposit_record' => $record->fresh(), 'appointment' => $appointment->fresh()];
    }

    private function ensureDepositRecord(Appointment $appointment): CommerceDepositRecord
    {
        $existing = CommerceDepositRecord::query()
            ->where('appointment_id', $appointment->id)
            ->orderByDesc('created_at')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return CommerceDepositRecord::query()->create([
            'tenant_id' => $appointment->tenant_id,
            'appointment_id' => $appointment->id,
            'booking_deposit_status' => $appointment->deposit_status,
            'required_cents' => $appointment->deposit_required_cents ?? 0,
            'lifecycle_state' => DepositLifecycleState::REQUIRED,
            'rule_snapshot' => $appointment->deposit_rule_snapshot,
        ]);
    }
}
