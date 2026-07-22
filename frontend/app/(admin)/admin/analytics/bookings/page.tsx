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
  formatRate,
  type BookingAnalytics,
} from '@/lib/analytics-types';
import { fetchBookingAnalytics } from '@/services/analytics.service';

export default function BookingAnalyticsPage() {
  const [filters, setFilters] = useState<AnalyticsFilterValues>(emptyAnalyticsFilters());
  const [data, setData] = useState<BookingAnalytics | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback((f: AnalyticsFilterValues) => {
    setLoading(true);
    setError(null);
    fetchBookingAnalytics(f)
      .then(setData)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load booking analytics'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load(filters);
  }, [filters, load]);

  const summary = data?.summary;

  return (
    <AdminAnalyticsShell title="Booking analytics">
      <AnalyticsFilterBar value={filters} onChange={setFilters} />

      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}
      {loading && !data ? <LoadingState label="Loading bookings…" /> : null}

      {data && summary ? (
        <>
          <p className="mb-4 text-xs text-zinc-500">Window: {formatRangeLabel(data.range)}</p>

          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <AnalyticsStatCard label="Total" value={formatNumber(summary.total_appointments)} />
            <AnalyticsStatCard label="Completed" value={formatNumber(summary.completed_appointments)} />
            <AnalyticsStatCard label="Cancelled" value={formatNumber(summary.cancelled_appointments)} hint={formatRate(summary.cancellation_rate)} />
            <AnalyticsStatCard label="No-show" value={formatNumber(summary.no_show_appointments)} hint={formatRate(summary.no_show_rate)} />
            <AnalyticsStatCard label="Checked in" value={formatNumber(summary.checked_in_appointments)} />
            <AnalyticsStatCard label="Confirmed" value={formatNumber(summary.confirmed_appointments)} />
            <AnalyticsStatCard label="Walk-in" value={formatNumber(summary.walk_in_appointments)} />
            <AnalyticsStatCard label="Avg. value" value={formatMoneyCents(summary.average_booking_value_cents)} />
          </div>

          <div className="mt-6 grid gap-4 lg:grid-cols-3">
            <div className="lg:col-span-1">
              <Card title="Daily activity">
                <AnalyticsDailySeriesTable
                  rows={data.daily}
                  columns={[
                    { key: 'total', label: 'Total' },
                    { key: 'completed', label: 'Completed' },
                  ]}
                />
              </Card>
            </div>

            <div className="lg:col-span-2">
              <Card title="Provider performance">
                {data.providers.length === 0 ? (
                  <AnalyticsEmptyState />
                ) : (
                  <table className="w-full text-left text-sm">
                    <thead>
                      <tr className="border-b text-zinc-500">
                        <th className="py-2">Provider</th>
                        <th className="text-right">Total</th>
                        <th className="text-right">Completed</th>
                        <th className="text-right">No-show</th>
                      </tr>
                    </thead>
                    <tbody>
                      {data.providers.map((row) => (
                        <tr key={row.provider_id ?? 'unassigned'} className="border-b border-zinc-100">
                          <td className="py-1.5 font-medium">{row.provider_name ?? 'Unassigned'}</td>
                          <td className="py-1.5 text-right tabular-nums">{formatNumber(row.total_appointments)}</td>
                          <td className="py-1.5 text-right tabular-nums">{formatNumber(row.completed_appointments)}</td>
                          <td className="py-1.5 text-right tabular-nums">{formatNumber(row.no_show_appointments)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </Card>

              <div className="mt-4">
                <Card title="Top services">
                  {data.services.length === 0 ? (
                    <AnalyticsEmptyState />
                  ) : (
                    <table className="w-full text-left text-sm">
                      <thead>
                        <tr className="border-b text-zinc-500">
                          <th className="py-2">Service</th>
                          <th className="text-right">Bookings</th>
                          <th className="text-right">Revenue</th>
                        </tr>
                      </thead>
                      <tbody>
                        {data.services.map((row) => (
                          <tr key={row.service_name ?? 'unknown'} className="border-b border-zinc-100">
                            <td className="py-1.5 font-medium">{row.service_name ?? '—'}</td>
                            <td className="py-1.5 text-right tabular-nums">{formatNumber(row.bookings)}</td>
                            <td className="py-1.5 text-right tabular-nums">{formatMoneyCents(row.revenue_cents)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  )}
                </Card>
              </div>
            </div>
          </div>
        </>
      ) : null}
    </AdminAnalyticsShell>
  );
}
