'use client';

import { useCallback, useEffect, useState } from 'react';
import {
  PlatformButton,
  PlatformCard,
  PlatformCodeBlock,
  PlatformEmptyState,
  PlatformErrorAlert,
  PlatformField,
  PlatformLoadingState,
  PlatformPage,
  PlatformPageIntro,
  platformInputClass,
  platformSelectClass,
} from '@/components/platform/ui';
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
    <PlatformPage>
      <PlatformPageIntro
        title="Audit log"
        description="Cross-tenant activity trail — immutable event stream for super admins."
      />

      <PlatformCard title="Filters">
        <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
          <div className="block flex-1 sm:min-w-[10rem]">
            <PlatformField label="Tenant">
              <select
                value={filters.tenant_id}
                onChange={(e) => setFilters({ ...filters, tenant_id: e.target.value })}
                className={platformSelectClass}
              >
                <option value="">All tenants</option>
                {tenants.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.trading_name || t.name}
                  </option>
                ))}
              </select>
            </PlatformField>
          </div>
          <div className="block flex-1 sm:min-w-[10rem]">
            <PlatformField label="Action">
              <input
                value={filters.action}
                onChange={(e) => setFilters({ ...filters, action: e.target.value })}
                placeholder="e.g. location.created"
                className={platformInputClass}
              />
            </PlatformField>
          </div>
          <div className="block flex-1 sm:min-w-[10rem]">
            <PlatformField label="Entity type">
              <input
                value={filters.entity_type}
                onChange={(e) => setFilters({ ...filters, entity_type: e.target.value })}
                placeholder="Model class or type"
                className={platformInputClass}
              />
            </PlatformField>
          </div>
          <PlatformButton type="button" onClick={() => load(1)}>
            Apply
          </PlatformButton>
        </div>
      </PlatformCard>

      {error ? <PlatformErrorAlert message={error} /> : null}

      <PlatformCard title={`Entries (${meta.total})`}>
        {loading ? <PlatformLoadingState label="Loading audit log…" /> : null}
        {!loading && items.length === 0 ? (
          <PlatformEmptyState message="No audit entries match your filters." />
        ) : null}
        <ul className="divide-y divide-[var(--platform-line-subtle)]">
          {items.map((entry) => (
            <li key={entry.id} className="py-3">
              <button
                type="button"
                className="w-full text-left"
                onClick={() => setExpandedId(expandedId === entry.id ? null : entry.id)}
              >
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <span className="font-mono text-sm font-medium text-white">{entry.action}</span>
                  <span className="font-mono text-xs text-[var(--platform-muted)]">
                    {entry.created_at ? new Date(entry.created_at).toLocaleString() : '—'}
                  </span>
                </div>
                <p className="mt-0.5 text-xs text-[var(--platform-label)]">
                  {entry.tenant?.name ?? '—'}
                  {entry.tenant?.slug ? ` · ${entry.tenant.slug}` : ''}
                  {' · '}
                  {entry.actor_name ?? entry.actor_id ?? 'system'}
                  {entry.entity_type ? ` · ${entry.entity_type.split('\\').pop()}` : ''}
                </p>
              </button>
              {expandedId === entry.id ? (
                <PlatformCodeBlock>
                  {JSON.stringify({ old: entry.old_values, new: entry.new_values }, null, 2)}
                </PlatformCodeBlock>
              ) : null}
            </li>
          ))}
        </ul>
        {meta.last_page > 1 ? (
          <div className="mt-4 flex gap-2">
            <PlatformButton
              variant="secondary"
              disabled={meta.current_page <= 1}
              onClick={() => load(meta.current_page - 1)}
            >
              Previous
            </PlatformButton>
            <PlatformButton
              variant="secondary"
              disabled={meta.current_page >= meta.last_page}
              onClick={() => load(meta.current_page + 1)}
            >
              Next
            </PlatformButton>
          </div>
        ) : null}
      </PlatformCard>
    </PlatformPage>
  );
}
