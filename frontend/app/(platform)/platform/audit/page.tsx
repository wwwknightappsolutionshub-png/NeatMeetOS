'use client';

import { useCallback, useEffect, useState } from 'react';
import { EmptyState, ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { AuditLogEntry } from '@/lib/identity-types';
import type { PlatformTenantRow } from '@/lib/types';
import {
  fetchPlatformAuditLogs,
  fetchPlatformTenants,
} from '@/services/platform.service';

export default function PlatformAuditPage() {
  const [items, setItems] = useState<AuditLogEntry[]>([]);
  const [tenants, setTenants] = useState<PlatformTenantRow[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [filters, setFilters] = useState({
    action: '',
    entity_type: '',
    tenant_id: '',
  });
  const [expandedId, setExpandedId] = useState<string | null>(null);

  useEffect(() => {
    void fetchPlatformTenants()
      .then(setTenants)
      .catch(() => setTenants([]));
  }, []);

  const load = useCallback(
    (page = 1) => {
      setLoading(true);
      setError(null);
      fetchPlatformAuditLogs({
        page,
        action: filters.action || undefined,
        entity_type: filters.entity_type || undefined,
        tenant_id: filters.tenant_id || undefined,
      })
        .then((data) => {
          setItems(data.items);
          setMeta(data.meta);
        })
        .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
        .finally(() => setLoading(false));
    },
    [filters],
  );

  useEffect(() => {
    load(1);
  }, [load]);

  return (
    <div className="mx-auto grid max-w-6xl gap-5">
      <div>
        <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-400/90">
          Platform
        </p>
        <h1 className="mt-1 text-2xl font-semibold tracking-tight text-white">Audit log</h1>
        <p className="mt-1 text-sm text-stone-400">
          Cross-tenant activity trail — visible to super admins only.
        </p>
      </div>

      <Card className="border-white/10 bg-white/5 text-stone-100" title="Filters">
        <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
          <label className="block flex-1 text-sm sm:min-w-[10rem]">
            <span className="mb-1 block text-stone-400">Tenant</span>
            <select
              value={filters.tenant_id}
              onChange={(e) => setFilters({ ...filters, tenant_id: e.target.value })}
              className="w-full rounded-lg border border-white/15 bg-stone-950/40 px-3 py-2 text-sm text-white outline-none focus:border-amber-500"
            >
              <option value="">All tenants</option>
              {tenants.map((t) => (
                <option key={t.id} value={t.id}>
                  {t.trading_name || t.name}
                </option>
              ))}
            </select>
          </label>
          <label className="block flex-1 text-sm sm:min-w-[10rem]">
            <span className="mb-1 block text-stone-400">Action</span>
            <input
              value={filters.action}
              onChange={(e) => setFilters({ ...filters, action: e.target.value })}
              placeholder="e.g. location.created"
              className="w-full rounded-lg border border-white/15 bg-stone-950/40 px-3 py-2 text-sm text-white outline-none focus:border-amber-500"
            />
          </label>
          <label className="block flex-1 text-sm sm:min-w-[10rem]">
            <span className="mb-1 block text-stone-400">Entity type</span>
            <input
              value={filters.entity_type}
              onChange={(e) =>
                setFilters({ ...filters, entity_type: e.target.value })
              }
              placeholder="Model class or type"
              className="w-full rounded-lg border border-white/15 bg-stone-950/40 px-3 py-2 text-sm text-white outline-none focus:border-amber-500"
            />
          </label>
          <Button
            type="button"
            className="!bg-[var(--platform-accent)]"
            onClick={() => load(1)}
          >
            Apply
          </Button>
        </div>
      </Card>

      {error ? <ErrorAlert message={error} /> : null}

      <Card
        className="border-white/10 bg-white/5 text-stone-100"
        title={`Entries (${meta.total})`}
      >
        {loading ? <LoadingState label="Loading audit log…" /> : null}
        {!loading && items.length === 0 ? (
          <EmptyState message="No audit entries match your filters." />
        ) : null}
        <ul className="divide-y divide-white/10">
          {items.map((entry) => (
            <li key={entry.id} className="py-3">
              <button
                type="button"
                className="w-full text-left"
                onClick={() =>
                  setExpandedId(expandedId === entry.id ? null : entry.id)
                }
              >
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <span className="font-medium text-white">{entry.action}</span>
                  <span className="text-xs text-stone-500">
                    {entry.created_at
                      ? new Date(entry.created_at).toLocaleString()
                      : '—'}
                  </span>
                </div>
                <p className="mt-0.5 text-xs text-stone-400">
                  {entry.tenant?.name ?? '—'}
                  {entry.tenant?.slug ? ` · ${entry.tenant.slug}` : ''}
                  {' · '}
                  {entry.actor_name ?? entry.actor_id ?? 'system'}
                  {entry.entity_type
                    ? ` · ${entry.entity_type.split('\\').pop()}`
                    : ''}
                </p>
              </button>
              {expandedId === entry.id ? (
                <pre className="mt-2 overflow-x-auto rounded-lg border border-white/10 bg-stone-950/50 p-2 text-xs text-stone-300">
                  {JSON.stringify(
                    { old: entry.old_values, new: entry.new_values },
                    null,
                    2,
                  )}
                </pre>
              ) : null}
            </li>
          ))}
        </ul>
        {meta.last_page > 1 ? (
          <div className="mt-4 flex gap-2">
            <Button
              type="button"
              className="!border !border-white/15 !bg-transparent !text-stone-100"
              disabled={meta.current_page <= 1}
              onClick={() => load(meta.current_page - 1)}
            >
              Previous
            </Button>
            <Button
              type="button"
              className="!border !border-white/15 !bg-transparent !text-stone-100"
              disabled={meta.current_page >= meta.last_page}
              onClick={() => load(meta.current_page + 1)}
            >
              Next
            </Button>
          </div>
        ) : null}
      </Card>
    </div>
  );
}
