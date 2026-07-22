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
  formatNumber,
  formatRangeLabel,
  humanizeToken,
  type ClientAnalytics,
} from '@/lib/analytics-types';
import { fetchClientAnalytics } from '@/services/analytics.service';

export default function ClientAnalyticsPage() {
  const [filters, setFilters] = useState<AnalyticsFilterValues>(emptyAnalyticsFilters());
  const [data, setData] = useState<ClientAnalytics | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback((f: AnalyticsFilterValues) => {
    setLoading(true);
    setError(null);
    fetchClientAnalytics({ from: f.from, to: f.to, location_id: f.location_id })
      .then(setData)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load client analytics'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load(filters);
  }, [filters, load]);

  const consentRows = data ? Object.entries(data.consents) : [];

  return (
    <AdminAnalyticsShell title="Client analytics">
      <AnalyticsFilterBar value={filters} onChange={setFilters} showProvider={false} />

      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}
      {loading && !data ? <LoadingState label="Loading clients…" /> : null}

      {data ? (
        <>
          <p className="mb-4 text-xs text-zinc-500">Window: {formatRangeLabel(data.range)}</p>

          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <AnalyticsStatCard label="Total clients" value={formatNumber(data.summary.total_clients)} />
            <AnalyticsStatCard label="New in period" value={formatNumber(data.summary.new_clients_in_period)} />
            <AnalyticsStatCard label="Active clients" value={formatNumber(data.summary.active_clients)} />
          </div>

          <div className="mt-6 grid gap-4 lg:grid-cols-3">
            <div className="lg:col-span-1">
              <Card title="Client growth">
                <AnalyticsDailySeriesTable
                  rows={data.growth}
                  columns={[{ key: 'new_clients', label: 'New clients' }]}
                  emptyMessage="No new clients in this period."
                />
              </Card>
            </div>

            <div className="lg:col-span-2 grid gap-4 sm:grid-cols-2">
              <Card title="Consent / communication uptake">
                {consentRows.length === 0 ? (
                  <AnalyticsEmptyState />
                ) : (
                  <table className="w-full text-left text-sm">
                    <thead>
                      <tr className="border-b text-zinc-500">
                        <th className="py-2">Consent</th>
                        <th className="text-right">Granted</th>
                        <th className="text-right">Denied</th>
                      </tr>
                    </thead>
                    <tbody>
                      {consentRows.map(([type, counts]) => (
                        <tr key={type} className="border-b border-zinc-100">
                          <td className="py-1.5 font-medium">{humanizeToken(type)}</td>
                          <td className="py-1.5 text-right tabular-nums">{formatNumber(counts.granted)}</td>
                          <td className="py-1.5 text-right tabular-nums">{formatNumber(counts.denied)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </Card>

              <Card title="Membership attachment">
                <ul className="space-y-1 text-sm">
                  <li className="flex justify-between">
                    <span className="text-zinc-600">With active membership</span>
                    <span className="font-medium">{formatNumber(data.membership_attachment.clients_with_active_membership)}</span>
                  </li>
                  <li className="flex justify-between">
                    <span className="text-zinc-600">With active package</span>
                    <span className="font-medium">{formatNumber(data.membership_attachment.clients_with_active_package)}</span>
                  </li>
                </ul>
              </Card>

              <Card title="Top tags">
                {data.tags.length === 0 ? (
                  <AnalyticsEmptyState message="No tags applied." />
                ) : (
                  <ul className="space-y-1 text-sm">
                    {data.tags.map((tag) => (
                      <li key={tag.tag_id} className="flex justify-between">
                        <span className="text-zinc-600">{tag.name}</span>
                        <span className="font-medium">{formatNumber(tag.total)}</span>
                      </li>
                    ))}
                  </ul>
                )}
              </Card>
            </div>
          </div>
        </>
      ) : null}
    </AdminAnalyticsShell>
  );
}
