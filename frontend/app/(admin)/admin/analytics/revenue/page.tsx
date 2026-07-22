'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminAnalyticsShell } from '@/components/admin/analytics/AdminAnalyticsShell';
import { AnalyticsDailySeriesTable } from '@/components/admin/analytics/AnalyticsDailySeriesTable';
import { AnalyticsEmptyState } from '@/components/admin/analytics/AnalyticsEmptyState';
import { AnalyticsFilterBar, type AnalyticsFilterValues } from '@/components/admin/analytics/AnalyticsFilterBar';
import { AnalyticsStatCard } from '@/components/admin/analytics/AnalyticsStatCard';
import { emptyAnalyticsFilters } from '@/components/admin/analytics/filters';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import {
  formatMoneyCents,
  formatNumber,
  formatRangeLabel,
  humanizeToken,
  type RevenueAnalytics,
} from '@/lib/analytics-types';
import { fetchRevenueAnalytics } from '@/services/analytics.service';

export default function RevenueAnalyticsPage() {
  const [filters, setFilters] = useState<AnalyticsFilterValues>(emptyAnalyticsFilters());
  const [data, setData] = useState<RevenueAnalytics | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback((f: AnalyticsFilterValues) => {
    setLoading(true);
    setError(null);
    fetchRevenueAnalytics(f)
      .then(setData)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load revenue analytics'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load(filters);
  }, [filters, load]);

  const payments = data?.summary.payments;
  const pos = data?.summary.pos;

  return (
    <AdminAnalyticsShell title="Revenue analytics">
      <AnalyticsFilterBar value={filters} onChange={setFilters} />

      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}
      {loading && !data ? <LoadingState label="Loading revenue…" /> : null}

      {data && payments && pos ? (
        <>
          <p className="mb-1 text-xs text-zinc-500">Window: {formatRangeLabel(data.range)}</p>
          <p className="mb-4 text-xs text-zinc-500">Operational revenue reporting — not finance-grade accounting.</p>

          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <AnalyticsStatCard label="Total collected" value={formatMoneyCents(payments.total_payment_collected_cents)} hint={`Net ${formatMoneyCents(payments.net_collected_cents)}`} />
            <AnalyticsStatCard label="Deposits collected" value={formatMoneyCents(payments.deposit_collected_cents)} hint={`${formatMoneyCents(payments.deposit_refunded_cents)} refunded`} />
            <AnalyticsStatCard label="POS sales" value={formatMoneyCents(pos.gross_sales_cents)} hint={`${formatNumber(pos.completed_checkouts_count)} checkouts`} />
            <AnalyticsStatCard label="Refunds" value={formatMoneyCents(payments.refund_total_cents)} hint={`POS ${formatMoneyCents(pos.refund_value_cents)}`} />
          </div>

          <div className="mt-6 grid gap-4 lg:grid-cols-3">
            <div className="lg:col-span-1">
              <Card title="Daily revenue">
                <AnalyticsDailySeriesTable
                  rows={data.daily}
                  columns={[
                    { key: 'collected_cents', label: 'Payments', format: (v) => formatMoneyCents(v) },
                    { key: 'pos_sales_cents', label: 'POS', format: (v) => formatMoneyCents(v) },
                  ]}
                />
              </Card>
            </div>

            <div className="lg:col-span-2 grid gap-4">
              <Card title="Payment status breakdown">
                {data.payment_status_breakdown.length === 0 ? (
                  <AnalyticsEmptyState />
                ) : (
                  <BreakdownTable
                    rows={data.payment_status_breakdown.map((row) => ({
                      label: humanizeToken(row.status),
                      total: row.total,
                      amount_cents: row.amount_cents,
                    }))}
                  />
                )}
              </Card>

              <Card title="Payment type breakdown">
                {data.payment_type_breakdown.length === 0 ? (
                  <AnalyticsEmptyState />
                ) : (
                  <BreakdownTable
                    rows={data.payment_type_breakdown.map((row) => ({
                      label: humanizeToken(row.transaction_type),
                      total: row.total,
                      amount_cents: row.amount_cents,
                    }))}
                  />
                )}
              </Card>

              <Card title="Provider breakdown">
                {data.provider_breakdown.length === 0 ? (
                  <AnalyticsEmptyState />
                ) : (
                  <BreakdownTable
                    rows={data.provider_breakdown.map((row) => ({
                      label: humanizeToken(row.provider),
                      total: row.total,
                      amount_cents: row.amount_cents,
                    }))}
                  />
                )}
              </Card>
            </div>
          </div>
        </>
      ) : null}
    </AdminAnalyticsShell>
  );
}

function BreakdownTable({ rows }: { rows: { label: string; total: number; amount_cents: number }[] }) {
  return (
    <table className="w-full text-left text-sm">
      <thead>
        <tr className="border-b text-zinc-500">
          <th className="py-2">Category</th>
          <th className="text-right">Count</th>
          <th className="text-right">Amount</th>
        </tr>
      </thead>
      <tbody>
        {rows.map((row) => (
          <tr key={row.label} className="border-b border-zinc-100">
            <td className="py-1.5 font-medium">{row.label}</td>
            <td className="py-1.5 text-right tabular-nums">{formatNumber(row.total)}</td>
            <td className="py-1.5 text-right tabular-nums">{formatMoneyCents(row.amount_cents)}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}
