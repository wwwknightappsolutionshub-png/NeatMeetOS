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
  formatNumber,
  formatRangeLabel,
  humanizeToken,
  movementTypeLabel,
  type InventoryAnalytics,
} from '@/lib/analytics-types';
import { fetchInventoryAnalytics } from '@/services/analytics.service';

export default function InventoryAnalyticsPage() {
  const [filters, setFilters] = useState<AnalyticsFilterValues>(emptyAnalyticsFilters());
  const [data, setData] = useState<InventoryAnalytics | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback((f: AnalyticsFilterValues) => {
    setLoading(true);
    setError(null);
    fetchInventoryAnalytics({ from: f.from, to: f.to, location_id: f.location_id })
      .then(setData)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load inventory analytics'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load(filters);
  }, [filters, load]);

  const summary = data?.summary;

  return (
    <AdminAnalyticsShell title="Inventory analytics">
      <AnalyticsFilterBar value={filters} onChange={setFilters} showProvider={false} />

      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}
      {loading && !data ? <LoadingState label="Loading inventory…" /> : null}

      {data && summary ? (
        <>
          <p className="mb-4 text-xs text-zinc-500">Window: {formatRangeLabel(data.range)}</p>

          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <AnalyticsStatCard label="Low-stock items" value={formatNumber(summary.low_stock_items_count)} hint="Current snapshot" />
            <AnalyticsStatCard label="Movements" value={formatNumber(summary.total_movements_count)} />
            <AnalyticsStatCard label="Adjustments" value={formatNumber(summary.stock_adjustments_count)} />
            <AnalyticsStatCard label="Consumption events" value={formatNumber(summary.stock_consumption_events_count)} hint={`${formatNumber(summary.consumption_total_quantity)} units`} />
          </div>

          <div className="mt-6 grid gap-4 lg:grid-cols-3">
            <div className="lg:col-span-1">
              <Card title="Movement breakdown">
                {data.movement_breakdown.length === 0 ? (
                  <AnalyticsEmptyState />
                ) : (
                  <table className="w-full text-left text-sm">
                    <thead>
                      <tr className="border-b text-zinc-500">
                        <th className="py-2">Type</th>
                        <th className="text-right">Count</th>
                        <th className="text-right">Qty</th>
                      </tr>
                    </thead>
                    <tbody>
                      {data.movement_breakdown.map((row) => (
                        <tr key={row.movement_type} className="border-b border-zinc-100">
                          <td className="py-1.5 font-medium">{movementTypeLabel(row.movement_type)}</td>
                          <td className="py-1.5 text-right tabular-nums">{formatNumber(row.total)}</td>
                          <td className="py-1.5 text-right tabular-nums">{formatNumber(row.quantity)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </Card>
            </div>

            <div className="lg:col-span-2 grid gap-4">
              <Card title="Low stock">
                {data.low_stock.length === 0 ? (
                  <AnalyticsEmptyState message="No low-stock items." />
                ) : (
                  <table className="w-full text-left text-sm">
                    <thead>
                      <tr className="border-b text-zinc-500">
                        <th className="py-2">Item</th>
                        <th>Type</th>
                        <th className="text-right">On hand</th>
                        <th className="text-right">Reorder point</th>
                      </tr>
                    </thead>
                    <tbody>
                      {data.low_stock.map((row) => (
                        <tr key={`${row.item_id}-${row.location_id ?? ''}`} className="border-b border-zinc-100">
                          <td className="py-1.5 font-medium">{row.item_name ?? '—'}</td>
                          <td className="py-1.5 text-zinc-600">{humanizeToken(row.item_type)}</td>
                          <td className="py-1.5 text-right tabular-nums">{formatNumber(row.on_hand_quantity)}</td>
                          <td className="py-1.5 text-right tabular-nums">{formatNumber(row.reorder_point)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </Card>

              <Card title="Top consumed items">
                {data.top_consumed_items.length === 0 ? (
                  <AnalyticsEmptyState />
                ) : (
                  <table className="w-full text-left text-sm">
                    <thead>
                      <tr className="border-b text-zinc-500">
                        <th className="py-2">Item</th>
                        <th>Type</th>
                        <th className="text-right">Quantity</th>
                        <th className="text-right">Events</th>
                      </tr>
                    </thead>
                    <tbody>
                      {data.top_consumed_items.map((row) => (
                        <tr key={row.item_id} className="border-b border-zinc-100">
                          <td className="py-1.5 font-medium">{row.item_name ?? '—'}</td>
                          <td className="py-1.5 text-zinc-600">{humanizeToken(row.item_type)}</td>
                          <td className="py-1.5 text-right tabular-nums">{formatNumber(row.quantity)}</td>
                          <td className="py-1.5 text-right tabular-nums">{formatNumber(row.events)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </Card>
            </div>
          </div>
        </>
      ) : null}
    </AdminAnalyticsShell>
  );
}
