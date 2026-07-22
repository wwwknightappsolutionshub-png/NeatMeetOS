<?php

namespace App\Domains\Payments\Services;

use App\Domains\Payments\Enums\PaymentTransactionStatus;
use App\Domains\Payments\Enums\PaymentTransactionType;
use App\Domains\Payments\Models\PaymentTransaction;
use App\Shared\Commerce\Enums\DepositLifecycleState;
use App\Shared\Commerce\Models\CommerceDepositRecord;

class PaymentReportingService
{
    public function __construct(private readonly PaymentScopeValidator $scope) {}

    public function summary(array $filters = []): array
    {
        $tenantId = $this->scope->tenantId();

        $query = PaymentTransaction::query()->where('tenant_id', $tenantId);

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $transactions = (clone $query)->get();

        $byStatus = $transactions->groupBy('status')->map->count();
        $byType = $transactions->groupBy('transaction_type')->map->count();
        $byMethod = $transactions->whereNotNull('payment_method_type')->groupBy('payment_method_type')->map->count();

        $succeededInbound = $transactions
            ->where('status', PaymentTransactionStatus::SUCCEEDED)
            ->where('direction', 'inbound')
            ->sum('amount_cents');

        return [
            'total_transactions' => $transactions->count(),
            'succeeded_inbound_cents' => $succeededInbound,
            'by_status' => $byStatus,
            'by_transaction_type' => $byType,
            'by_payment_method' => $byMethod,
        ];
    }

    public function failed(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = PaymentTransaction::query()
            ->with(['client', 'appointment'])
            ->where('status', PaymentTransactionStatus::FAILED)
            ->orderByDesc('failed_at');

        if (! empty($filters['from'])) {
            $query->where('failed_at', '>=', $filters['from']);
        }

        return $query->limit(100)->get();
    }

    public function deposits(array $filters = []): array
    {
        $tenantId = $this->scope->tenantId();

        $query = CommerceDepositRecord::query()->where('tenant_id', $tenantId);

        if (! empty($filters['lifecycle_state'])) {
            $query->where('lifecycle_state', $filters['lifecycle_state']);
        }

        $records = $query->orderByDesc('updated_at')->limit(100)->get();

        return [
            'total' => $records->count(),
            'collected_cents' => $records->where('lifecycle_state', DepositLifecycleState::COLLECTED)->sum('collected_cents'),
            'pending_count' => $records->where('lifecycle_state', DepositLifecycleState::REQUIRED)->count(),
            'refunded_count' => $records->where('lifecycle_state', DepositLifecycleState::REFUNDED)->count(),
            'records' => $records,
        ];
    }
}
