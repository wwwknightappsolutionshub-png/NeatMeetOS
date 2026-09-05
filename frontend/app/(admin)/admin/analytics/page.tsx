'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminAnalyticsShell } from '@/components/admin/analytics/AdminAnalyticsShell';
import { AnalyticsFilterBar, type AnalyticsFilterValues } from '@/components/admin/analytics/AnalyticsFilterBar';
import { AnalyticsKeyValueTable } from '@/components/admin/analytics/AnalyticsKeyValueTable';
import { AnalyticsSectionCard } from '@/components/admin/analytics/AnalyticsSectionCard';
import { AnalyticsStatCard } from '@/components/admin/analytics/AnalyticsStatCard';
import { emptyAnalyticsFilters } from '@/components/admin/analytics/filters';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import {
  formatMoneyCents,
  formatNumber,
  formatRangeLabel,
  type AnalyticsOverview,
} from '@/lib/analytics-types';
import { fetchAnalyticsOverview } from '@/services/analytics.service';

export default function AnalyticsOverviewPage() {
  const [filters, setFilters] = useState<AnalyticsFilterValues>(emptyAnalyticsFilters());
  const [data, setData] = useState<AnalyticsOverview | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback((f: AnalyticsFilterValues) => {
    setLoading(true);
    setError(null);
    fetchAnalyticsOverview(f)
      .then(setData)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load analytics overview'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load(filters);
  }, [filters, load]);

  return (
    <AdminAnalyticsShell title="Operational overview">
      <AnalyticsFilterBar value={filters} onChange={setFilters} />

      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}
      {loading && !data ? <LoadingState label="Loading overview…" /> : null}

      {data ? (
        <>
          <p className="mb-4 text-xs text-zinc-500">
            Window: {formatRangeLabel(data.range)} ·{' '}
            <a href="/admin/analytics/intelligence" className="font-medium text-zinc-700 underline">
              Open Business Performance Intelligence
            </a>
          </p>

          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <AnalyticsStatCard
              label="Appointments"
              value={formatNumber(data.bookings.total_appointments)}
              hint={`${formatNumber(data.bookings.completed_appointments)} completed`}
            />
            <AnalyticsStatCard
              label="Payments collected"
              value={formatMoneyCents(data.payments.total_payment_collected_cents)}
              hint={`${formatMoneyCents(data.payments.refund_total_cents)} refunded`}
            />
            <AnalyticsStatCard
              label="POS sales"
              value={formatMoneyCents(data.pos.gross_sales_cents)}
              hint={`${formatNumber(data.pos.completed_checkouts_count)} checkouts`}
            />
            <AnalyticsStatCard
              label="New clients"
              value={formatNumber(data.clients.new_clients_in_period)}
              hint={`${formatNumber(data.clients.total_clients)} total`}
            />
          </div>

          <div className="mt-6 grid gap-4 lg:grid-cols-3">
            <AnalyticsSectionCard title="Bookings" href="/admin/analytics/bookings">
              <AnalyticsKeyValueTable
                rows={[
                  { label: 'Completed', value: formatNumber(data.bookings.completed_appointments) },
                  { label: 'Cancelled', value: formatNumber(data.bookings.cancelled_appointments) },
                  { label: 'No-show', value: formatNumber(data.bookings.no_show_appointments) },
                  { label: 'Checked in', value: formatNumber(data.bookings.checked_in_appointments) },
                  { label: 'Walk-in', value: formatNumber(data.bookings.walk_in_appointments) },
                  { label: 'Avg. booking value', value: formatMoneyCents(data.bookings.average_booking_value_cents) },
                ]}
              />
            </AnalyticsSectionCard>

            <AnalyticsSectionCard title="Revenue" href="/admin/analytics/revenue">
              <AnalyticsKeyValueTable
                rows={[
                  { label: 'Payments collected', value: formatMoneyCents(data.payments.total_payment_collected_cents) },
                  { label: 'Deposits collected', value: formatMoneyCents(data.payments.deposit_collected_cents) },
                  { label: 'Deposits refunded', value: formatMoneyCents(data.payments.deposit_refunded_cents) },
                  { label: 'Refunds', value: formatMoneyCents(data.payments.refund_total_cents) },
                  { label: 'Failed payments', value: formatNumber(data.payments.failed_payments_count) },
                  { label: 'POS refunds', value: formatMoneyCents(data.pos.refund_value_cents) },
                ]}
              />
            </AnalyticsSectionCard>

            <AnalyticsSectionCard title="Clients" href="/admin/analytics/clients">
              <AnalyticsKeyValueTable
                rows={[
                  { label: 'Total clients', value: formatNumber(data.clients.total_clients) },
                  { label: 'New in period', value: formatNumber(data.clients.new_clients_in_period) },
                  { label: 'Active', value: formatNumber(data.clients.active_clients) },
                  { label: 'Email opt-ins', value: formatNumber(data.clients.marketing_email_opt_in_count) },
                  { label: 'SMS opt-ins', value: formatNumber(data.clients.marketing_sms_opt_in_count) },
                ]}
              />
            </AnalyticsSectionCard>

            <AnalyticsSectionCard title="Memberships">
              <AnalyticsKeyValueTable
                rows={[
                  { label: 'Active memberships', value: formatNumber(data.memberships.active_memberships) },
                  { label: 'Active packages', value: formatNumber(data.memberships.active_packages) },
                  { label: 'Wallet liability', value: formatMoneyCents(data.memberships.wallet_liability_cents) },
                  { label: 'Loyalty points outstanding', value: formatNumber(data.memberships.loyalty_points_outstanding) },
                ]}
              />
            </AnalyticsSectionCard>

            <AnalyticsSectionCard title="Inventory" href="/admin/analytics/inventory">
              <AnalyticsKeyValueTable
                rows={[
                  { label: 'Low-stock items', value: formatNumber(data.inventory.low_stock_items_count) },
                  { label: 'Adjustments', value: formatNumber(data.inventory.stock_adjustments_count) },
                  { label: 'Consumption events', value: formatNumber(data.inventory.stock_consumption_events_count) },
                ]}
              />
            </AnalyticsSectionCard>

            <AnalyticsSectionCard title="Communications" href="/admin/analytics/communications">
              <AnalyticsKeyValueTable
                rows={[
                  { label: 'Marketing sent', value: formatNumber(data.marketing.messages_sent_count) },
                  { label: 'Marketing failed', value: formatNumber(data.marketing.messages_failed_count) },
                  { label: 'Campaigns', value: formatNumber(data.marketing.campaigns_count) },
                  { label: 'Notifications sent', value: formatNumber(data.notifications.messages_sent_count) },
                  { label: 'Notifications failed', value: formatNumber(data.notifications.messages_failed_count) },
                  { label: 'Notifications suppressed', value: formatNumber(data.notifications.messages_suppressed_count) },
                ]}
              />
            </AnalyticsSectionCard>
          </div>
        </>
      ) : null}
    </AdminAnalyticsShell>
  );
}
