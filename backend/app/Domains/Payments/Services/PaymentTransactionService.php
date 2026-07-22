<?php

namespace App\Domains\Payments\Services;

use App\Domains\Payments\Enums\PaymentDirection;
use App\Domains\Payments\Enums\PaymentProvider;
use App\Domains\Payments\Enums\PaymentTransactionStatus;
use App\Domains\Payments\Enums\PaymentTransactionType;
use App\Domains\Payments\Models\PaymentTransaction;
use App\Domains\Integrations\Contracts\PaymentProviderAttemptContract;
use App\Shared\Audit\AuditLogger;
use App\Shared\Commerce\DTO\CommerceEventDto;
use App\Shared\Commerce\Enums\CommerceEventName;
use App\Shared\Commerce\Services\CommerceEventPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentTransactionService
{
    public function __construct(
        private readonly PaymentScopeValidator $scope,
        private readonly PaymentAllocationService $allocationService,
        private readonly AuditLogger $auditLogger,
        private readonly CommerceEventPublisher $eventPublisher,
        private readonly PaymentProviderAttemptContract $providerAttempts,
    ) {}

    public function list(array $filters): \Illuminate\Database\Eloquent\Collection
    {
        $query = PaymentTransaction::query()
            ->with(['client', 'appointment', 'location', 'allocations'])
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['transaction_type'])) {
            $query->where('transaction_type', $filters['transaction_type']);
        }

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (! empty($filters['appointment_id'])) {
            $query->where('appointment_id', $filters['appointment_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return $query->limit(200)->get();
    }

    public function find(string $id): PaymentTransaction
    {
        return PaymentTransaction::query()
            ->with(['client', 'appointment', 'location', 'teamMember', 'allocations', 'refunds'])
            ->findOrFail($id);
    }

    public function createManual(array $data, ?string $teamMemberId = null): PaymentTransaction
    {
        return $this->createTransaction($data, PaymentProvider::MANUAL, 'payment.created.manual', $teamMemberId);
    }

    public function createPaymentLink(array $data, ?string $teamMemberId = null): PaymentTransaction
    {
        $data['provider'] = PaymentProvider::PAYMENT_LINK;
        $data['payment_method_type'] = $data['payment_method_type'] ?? 'payment_link';
        $data['status'] = PaymentTransactionStatus::PENDING;
        $data['provider_reference'] = $data['provider_reference'] ?? 'plink_'.Str::lower(Str::random(12));

        return $this->createTransaction($data, PaymentProvider::PAYMENT_LINK, 'payment.created.link', $teamMemberId, recordProviderAttempt: true);
    }

    public function markSucceeded(PaymentTransaction $transaction, ?string $teamMemberId = null): PaymentTransaction
    {
        return $this->updateStatus($transaction, PaymentTransactionStatus::SUCCEEDED, null, null, $teamMemberId);
    }

    public function markFailed(
        PaymentTransaction $transaction,
        ?string $failureCode = null,
        ?string $failureMessage = null,
        ?string $teamMemberId = null,
    ): PaymentTransaction {
        return $this->updateStatus($transaction, PaymentTransactionStatus::FAILED, $failureCode, $failureMessage, $teamMemberId);
    }

    public function cancel(PaymentTransaction $transaction, ?string $teamMemberId = null): PaymentTransaction
    {
        if ($transaction->status !== PaymentTransactionStatus::PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Only pending transactions can be cancelled.'],
            ]);
        }

        return $this->updateStatus($transaction, PaymentTransactionStatus::CANCELLED, null, null, $teamMemberId);
    }

    private function createTransaction(
        array $data,
        string $provider,
        string $auditAction,
        ?string $teamMemberId,
        bool $recordProviderAttempt = false,
    ): PaymentTransaction {
        if ($data['amount_cents'] <= 0) {
            throw ValidationException::withMessages([
                'amount_cents' => ['Amount must be positive.'],
            ]);
        }

        if (! empty($data['appointment_id'])) {
            $this->scope->findAppointment($data['appointment_id']);
        }

        if (! empty($data['idempotency_key'])) {
            $existing = PaymentTransaction::query()
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($data, $provider, $auditAction, $teamMemberId, $recordProviderAttempt) {
            $transaction = PaymentTransaction::query()->create([
                'tenant_id' => $this->scope->tenantId(),
                'location_id' => $data['location_id'] ?? null,
                'client_id' => $data['client_id'] ?? null,
                'appointment_id' => $data['appointment_id'] ?? null,
                'team_member_id' => $data['team_member_id'] ?? $teamMemberId,
                'transaction_type' => $data['transaction_type'],
                'direction' => $data['direction'] ?? PaymentDirection::INBOUND,
                'status' => $data['status'] ?? PaymentTransactionStatus::SUCCEEDED,
                'amount_cents' => $data['amount_cents'],
                'currency' => $data['currency'] ?? 'GBP',
                'provider' => $provider,
                'provider_reference' => $data['provider_reference'] ?? null,
                'external_reference' => $data['external_reference'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'payment_method_type' => $data['payment_method_type'] ?? null,
                'payment_method_label' => $data['payment_method_label'] ?? null,
                'processed_at' => ($data['status'] ?? PaymentTransactionStatus::SUCCEEDED) === PaymentTransactionStatus::SUCCEEDED
                    ? now()
                    : null,
                'metadata' => $data['metadata'] ?? null,
                'created_by_team_member_id' => $teamMemberId,
            ]);

            if (! empty($data['allocations'])) {
                $this->allocationService->attach($transaction, $data['allocations']);
            }

            $this->auditLogger->log($auditAction, $transaction, null, $transaction->only([
                'amount_cents', 'status', 'transaction_type', 'provider',
            ]));

            if ($transaction->status === PaymentTransactionStatus::SUCCEEDED) {
                $this->eventPublisher->publish(new CommerceEventDto(
                    eventName: CommerceEventName::PAYMENT_CAPTURED,
                    tenantId: $transaction->tenant_id,
                    aggregateType: 'payment_transaction',
                    aggregateId: $transaction->id,
                    payload: [
                        'amount_cents' => $transaction->amount_cents,
                        'transaction_type' => $transaction->transaction_type,
                        'appointment_id' => $transaction->appointment_id,
                    ],
                ));

                if ($provider === PaymentProvider::MANUAL) {
                    $this->eventPublisher->publish(new CommerceEventDto(
                        eventName: CommerceEventName::PAYMENT_RECORDED,
                        tenantId: $transaction->tenant_id,
                        aggregateType: 'payment_transaction',
                        aggregateId: $transaction->id,
                        payload: [
                            'amount_cents' => $transaction->amount_cents,
                            'provider' => $provider,
                        ],
                    ));
                }
            }

            if ($recordProviderAttempt) {
                $this->providerAttempts->recordPaymentLink($transaction);
            }

            return $transaction->fresh()->load(['allocations', 'client', 'appointment']);
        });
    }

    private function updateStatus(
        PaymentTransaction $transaction,
        string $status,
        ?string $failureCode,
        ?string $failureMessage,
        ?string $teamMemberId,
    ): PaymentTransaction {
        $this->scope->assertTenantModel($transaction);

        $old = ['status' => $transaction->status];
        $transaction->status = $status;
        $transaction->updated_by_team_member_id = $teamMemberId;

        if ($status === PaymentTransactionStatus::SUCCEEDED) {
            $transaction->processed_at = now();
        }

        if ($status === PaymentTransactionStatus::FAILED) {
            $transaction->failed_at = now();
            $transaction->failure_code = $failureCode;
            $transaction->failure_message = $failureMessage;
        }

        $transaction->save();

        $this->auditLogger->log('payment.status_updated', $transaction, $old, ['status' => $status]);

        return $transaction->fresh()->load(['allocations', 'client', 'appointment']);
    }
}
