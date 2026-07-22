<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\DTOs\DateRange;
use App\Domains\Analytics\Services\Concerns\BuildsDailySeries;
use App\Domains\Payments\Enums\PaymentDirection;
use App\Domains\Payments\Enums\PaymentTransactionStatus;
use App\Shared\Commerce\Enums\DepositLifecycleState;
use Illuminate\Support\Facades\DB;

/**
 * Cross-domain operational revenue reporting combining Payments, booking
 * deposits and POS checkouts. This is not accounting — it is a read-only
 * commercial summary.
 *
 * Timestamp semantics:
 *  - payments/refunds: payment_transactions.created_at / payment_refunds.created_at
 *  - deposits: commerce_deposit_records.collected_at / refunded_at
 *  - POS sales: commerce_checkouts.completed_at (only settled sales)
 */
class RevenueAnalyticsService
{
    use BuildsDailySeries;

    /**
     * @return array<string, mixed>
     */
    public function report(string $tenantId, DateRange $range, ?string $locationId = null, ?string $providerId = null): array
    {
        return [
            'range' => $range->toArray(),
            'summary' => [
                'payments' => $this->paymentsMetrics($tenantId, $range, $locationId, $providerId),
                'pos' => $this->posMetrics($tenantId, $range, $locationId, $providerId),
            ],
            'daily' => $this->daily($tenantId, $range, $locationId, $providerId),
            'payment_status_breakdown' => $this->statusBreakdown($tenantId, $range, $locationId, $providerId),
            'payment_type_breakdown' => $this->typeBreakdown($tenantId, $range, $locationId, $providerId),
            'provider_breakdown' => $this->providerBreakdown($tenantId, $range, $locationId, $providerId),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function paymentsMetrics(string $tenantId, DateRange $range, ?string $locationId = null, ?string $providerId = null): array
    {
        $payments = $this->paymentQuery($tenantId, $range, $locationId, $providerId);

        $collected = (int) (clone $payments)
            ->where('status', PaymentTransactionStatus::SUCCEEDED)
            ->where('direction', PaymentDirection::INBOUND)
            ->sum('amount_cents');

        $failed = (int) (clone $payments)
            ->where('status', PaymentTransactionStatus::FAILED)
            ->count();

        $refundTotal = (int) DB::table('payment_refunds')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$range->from, $range->to])
            ->where('status', PaymentTransactionStatus::SUCCEEDED)
            ->sum('amount_cents');

        $depositCollected = (int) DB::table('commerce_deposit_records')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('collected_at')
            ->whereBetween('collected_at', [$range->from, $range->to])
            ->sum('collected_cents');

        $depositRefunded = (int) DB::table('commerce_deposit_records')
            ->where('tenant_id', $tenantId)
            ->where('lifecycle_state', DepositLifecycleState::REFUNDED)
            ->whereNotNull('refunded_at')
            ->whereBetween('refunded_at', [$range->from, $range->to])
            ->sum('collected_cents');

        return [
            'total_payment_collected_cents' => $collected,
            'deposit_collected_cents' => $depositCollected,
            'deposit_refunded_cents' => $depositRefunded,
            'refund_total_cents' => $refundTotal,
            'failed_payments_count' => $failed,
            'net_collected_cents' => $collected - $refundTotal,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function posMetrics(string $tenantId, DateRange $range, ?string $locationId = null, ?string $providerId = null): array
    {
        $checkouts = $this->completedCheckoutQuery($tenantId, $range, $locationId, $providerId);

        $count = (int) (clone $checkouts)->count();
        $gross = (int) (clone $checkouts)->sum('total_cents');
        $refunds = (int) (clone $checkouts)->sum('refunded_total_cents');

        return [
            'completed_checkouts_count' => $count,
            'gross_sales_cents' => $gross,
            'refund_value_cents' => $refunds,
            'average_checkout_value_cents' => $count > 0 ? intdiv($gross, $count) : 0,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function daily(string $tenantId, DateRange $range, ?string $locationId, ?string $providerId): array
    {
        $paymentRows = $this->paymentQuery($tenantId, $range, $locationId, $providerId)
            ->where('status', PaymentTransactionStatus::SUCCEEDED)
            ->where('direction', PaymentDirection::INBOUND)
            ->get(['created_at', 'amount_cents']);

        $paymentSeries = $this->dailySeries(
            $range,
            $paymentRows,
            'created_at',
            fn ($row) => ['collected_cents' => (int) $row->amount_cents],
            ['collected_cents' => 0],
        );

        $posRows = $this->completedCheckoutQuery($tenantId, $range, $locationId, $providerId)
            ->get(['completed_at', 'total_cents']);

        $posByDay = [];
        foreach ($this->dailySeries(
            $range,
            $posRows,
            'completed_at',
            fn ($row) => ['pos_sales_cents' => (int) $row->total_cents],
            ['pos_sales_cents' => 0],
        ) as $entry) {
            $posByDay[$entry['date']] = $entry['pos_sales_cents'];
        }

        return array_map(fn ($entry) => [
            'date' => $entry['date'],
            'collected_cents' => $entry['collected_cents'],
            'pos_sales_cents' => $posByDay[$entry['date']] ?? 0,
        ], $paymentSeries);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function statusBreakdown(string $tenantId, DateRange $range, ?string $locationId, ?string $providerId): array
    {
        return $this->paymentQuery($tenantId, $range, $locationId, $providerId)
            ->selectRaw('status, COUNT(*) as total, COALESCE(SUM(amount_cents), 0) as amount_cents')
            ->groupBy('status')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'total' => (int) $row->total,
                'amount_cents' => (int) $row->amount_cents,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function typeBreakdown(string $tenantId, DateRange $range, ?string $locationId, ?string $providerId): array
    {
        return $this->paymentQuery($tenantId, $range, $locationId, $providerId)
            ->where('status', PaymentTransactionStatus::SUCCEEDED)
            ->selectRaw('transaction_type, COUNT(*) as total, COALESCE(SUM(amount_cents), 0) as amount_cents')
            ->groupBy('transaction_type')
            ->orderByDesc('amount_cents')
            ->get()
            ->map(fn ($row) => [
                'transaction_type' => $row->transaction_type,
                'total' => (int) $row->total,
                'amount_cents' => (int) $row->amount_cents,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function providerBreakdown(string $tenantId, DateRange $range, ?string $locationId, ?string $providerId): array
    {
        return $this->paymentQuery($tenantId, $range, $locationId, $providerId)
            ->where('status', PaymentTransactionStatus::SUCCEEDED)
            ->where('direction', PaymentDirection::INBOUND)
            ->selectRaw('provider, COUNT(*) as total, COALESCE(SUM(amount_cents), 0) as amount_cents')
            ->groupBy('provider')
            ->orderByDesc('amount_cents')
            ->get()
            ->map(fn ($row) => [
                'provider' => $row->provider ?? 'unknown',
                'total' => (int) $row->total,
                'amount_cents' => (int) $row->amount_cents,
            ])
            ->all();
    }

    private function paymentQuery(string $tenantId, DateRange $range, ?string $locationId, ?string $providerId): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('payment_transactions')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$range->from, $range->to]);

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }
        if ($providerId !== null) {
            $query->where('team_member_id', $providerId);
        }

        return $query;
    }

    private function completedCheckoutQuery(string $tenantId, DateRange $range, ?string $locationId, ?string $providerId): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('commerce_checkouts')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$range->from, $range->to]);

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }
        if ($providerId !== null) {
            $query->where('team_member_id', $providerId);
        }

        return $query;
    }
}
