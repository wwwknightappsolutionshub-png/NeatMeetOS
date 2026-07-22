'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminAnalyticsShell } from '@/components/admin/analytics/AdminAnalyticsShell';
import { AnalyticsEmptyState } from '@/components/admin/analytics/AnalyticsEmptyState';
import { AnalyticsFilterBar, type AnalyticsFilterValues } from '@/components/admin/analytics/AnalyticsFilterBar';
import { AnalyticsStatCard } from '@/components/admin/analytics/AnalyticsStatCard';
import { emptyAnalyticsFilters } from '@/components/admin/analytics/filters';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import {
  channelLabel,
  formatNumber,
  formatRangeLabel,
  humanizeToken,
  type CommunicationsAnalytics,
  type CommunicationsChannelRow,
} from '@/lib/analytics-types';
import { fetchCommunicationsAnalytics } from '@/services/analytics.service';

export default function CommunicationsAnalyticsPage() {
  const [filters, setFilters] = useState<AnalyticsFilterValues>(emptyAnalyticsFilters());
  const [data, setData] = useState<CommunicationsAnalytics | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback((f: AnalyticsFilterValues) => {
    setLoading(true);
    setError(null);
    fetchCommunicationsAnalytics({ from: f.from, to: f.to })
      .then(setData)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load communications analytics'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load(filters);
  }, [filters, load]);

  return (
    <AdminAnalyticsShell title="Communications analytics">
      <AnalyticsFilterBar value={filters} onChange={setFilters} showLocation={false} showProvider={false} />

      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}
      {loading && !data ? <LoadingState label="Loading communications…" /> : null}

      {data ? (
        <>
          <p className="mb-4 text-xs text-zinc-500">Window: {formatRangeLabel(data.range)}</p>

          <section className="mb-6 rounded-xl border border-indigo-200 bg-indigo-50/40 p-4">
            <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-indigo-800">Marketing</h2>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <AnalyticsStatCard label="Sent" value={formatNumber(data.marketing.messages_sent_count)} />
              <AnalyticsStatCard label="Failed" value={formatNumber(data.marketing.messages_failed_count)} />
              <AnalyticsStatCard label="Suppressed" value={formatNumber(data.marketing.messages_suppressed_count)} />
              <AnalyticsStatCard label="Campaigns" value={formatNumber(data.marketing.campaigns_count)} hint={`${formatNumber(data.marketing.workflow_executions_count)} workflow runs`} />
            </div>
            <div className="mt-4 grid gap-4 lg:grid-cols-2">
              <Card title="Marketing by channel">
                <ChannelTable rows={data.marketing.by_channel} />
              </Card>
              <Card title="Workflow executions by status">
                {data.marketing.workflow_execution_status_breakdown.length === 0 ? (
                  <AnalyticsEmptyState />
                ) : (
                  <ul className="space-y-1 text-sm">
                    {data.marketing.workflow_execution_status_breakdown.map((row) => (
                      <li key={row.status} className="flex justify-between">
                        <span className="text-zinc-600">{humanizeToken(row.status)}</span>
                        <span className="font-medium">{formatNumber(row.total)}</span>
                      </li>
                    ))}
                  </ul>
                )}
              </Card>
            </div>
          </section>

          <section className="rounded-xl border border-emerald-200 bg-emerald-50/40 p-4">
            <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-emerald-800">Notifications (operational)</h2>
            <div className="grid gap-4 sm:grid-cols-3">
              <AnalyticsStatCard label="Sent" value={formatNumber(data.notifications.messages_sent_count)} />
              <AnalyticsStatCard label="Failed" value={formatNumber(data.notifications.messages_failed_count)} />
              <AnalyticsStatCard label="Suppressed" value={formatNumber(data.notifications.messages_suppressed_count)} />
            </div>
            <div className="mt-4">
              <Card title="Notifications by channel">
                <ChannelTable rows={data.notifications.by_channel} />
              </Card>
            </div>
          </section>
        </>
      ) : null}
    </AdminAnalyticsShell>
  );
}

function ChannelTable({ rows }: { rows: CommunicationsChannelRow[] }) {
  if (rows.length === 0) {
    return <AnalyticsEmptyState />;
  }
  return (
    <table className="w-full text-left text-sm">
      <thead>
        <tr className="border-b text-zinc-500">
          <th className="py-2">Channel</th>
          <th className="text-right">Total</th>
          <th className="text-right">Sent</th>
          <th className="text-right">Failed</th>
          <th className="text-right">Suppressed</th>
        </tr>
      </thead>
      <tbody>
        {rows.map((row) => (
          <tr key={row.channel} className="border-b border-zinc-100">
            <td className="py-1.5 font-medium">{channelLabel(row.channel)}</td>
            <td className="py-1.5 text-right tabular-nums">{formatNumber(row.total)}</td>
            <td className="py-1.5 text-right tabular-nums">{formatNumber(row.sent)}</td>
            <td className="py-1.5 text-right tabular-nums">{formatNumber(row.failed)}</td>
            <td className="py-1.5 text-right tabular-nums">{formatNumber(row.suppressed)}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}
