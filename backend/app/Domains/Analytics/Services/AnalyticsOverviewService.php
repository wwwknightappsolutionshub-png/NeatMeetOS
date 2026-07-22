<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\DTOs\DateRange;

/**
 * Cross-domain operational overview for the admin dashboard.
 *
 * Delegates to the focused analytics services and returns a stable top-level
 * payload with one section per domain.
 */
class AnalyticsOverviewService
{
    public function __construct(
        private readonly BookingAnalyticsService $bookingAnalytics,
        private readonly RevenueAnalyticsService $revenueAnalytics,
        private readonly ClientAnalyticsService $clientAnalytics,
        private readonly InventoryAnalyticsService $inventoryAnalytics,
        private readonly CommunicationsAnalyticsService $communicationsAnalytics,
        private readonly MembershipMetricsService $membershipMetrics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(string $tenantId, DateRange $range, ?string $locationId = null, ?string $providerId = null): array
    {
        $bookings = $this->bookingAnalytics->summary($tenantId, $range, $locationId, $providerId);
        $payments = $this->revenueAnalytics->paymentsMetrics($tenantId, $range, $locationId, $providerId);
        $pos = $this->revenueAnalytics->posMetrics($tenantId, $range, $locationId, $providerId);
        $clients = $this->clientAnalytics->summary($tenantId, $range, $locationId);
        $memberships = $this->membershipMetrics->snapshot($tenantId);
        $inventory = $this->inventoryAnalytics->summary($tenantId, $range, $locationId);
        $communications = $this->communicationsAnalytics->report($tenantId, $range);

        return [
            'range' => $range->toArray(),
            'bookings' => [
                'total_appointments' => $bookings['total_appointments'],
                'completed_appointments' => $bookings['completed_appointments'],
                'cancelled_appointments' => $bookings['cancelled_appointments'],
                'no_show_appointments' => $bookings['no_show_appointments'],
                'checked_in_appointments' => $bookings['checked_in_appointments'],
                'walk_in_appointments' => $bookings['walk_in_appointments'],
                'average_booking_value_cents' => $bookings['average_booking_value_cents'],
            ],
            'payments' => [
                'total_payment_collected_cents' => $payments['total_payment_collected_cents'],
                'deposit_collected_cents' => $payments['deposit_collected_cents'],
                'deposit_refunded_cents' => $payments['deposit_refunded_cents'],
                'refund_total_cents' => $payments['refund_total_cents'],
                'failed_payments_count' => $payments['failed_payments_count'],
            ],
            'pos' => $pos,
            'clients' => [
                'total_clients' => $clients['total_clients'],
                'new_clients_in_period' => $clients['new_clients_in_period'],
                'active_clients' => $clients['active_clients'],
                'marketing_email_opt_in_count' => $clients['marketing_email_opt_in_count'],
                'marketing_sms_opt_in_count' => $clients['marketing_sms_opt_in_count'],
            ],
            'memberships' => $memberships,
            'inventory' => [
                'low_stock_items_count' => $inventory['low_stock_items_count'],
                'stock_adjustments_count' => $inventory['stock_adjustments_count'],
                'stock_consumption_events_count' => $inventory['stock_consumption_events_count'],
            ],
            'marketing' => [
                'campaigns_count' => $communications['marketing']['campaigns_count'],
                'messages_sent_count' => $communications['marketing']['messages_sent_count'],
                'messages_failed_count' => $communications['marketing']['messages_failed_count'],
                'workflow_executions_count' => $communications['marketing']['workflow_executions_count'],
            ],
            'notifications' => [
                'messages_sent_count' => $communications['notifications']['messages_sent_count'],
                'messages_failed_count' => $communications['notifications']['messages_failed_count'],
                'messages_suppressed_count' => $communications['notifications']['messages_suppressed_count'],
            ],
        ];
    }
}
