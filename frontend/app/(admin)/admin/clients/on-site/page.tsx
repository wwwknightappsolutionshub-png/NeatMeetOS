'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { AdminCrmShell } from '@/components/admin/crm/AdminCrmShell';
import { EmptyState, ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import { fetchOpenVisits, type OpenClientVisit } from '@/services/crm.service';

function formatWhen(iso: string | null | undefined): string {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleString();
}

export default function WhosInPage() {
  const [items, setItems] = useState<OpenClientVisit[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    setError(null);
    fetchOpenVisits()
      .then((data) => setItems(data.items))
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
    const id = window.setInterval(load, 30000);
    return () => window.clearInterval(id);
  }, [load]);

  return (
    <AdminCrmShell title="Who's in">
      <div className="mb-4 flex items-center justify-between gap-3">
        <p className="text-sm text-zinc-600">
          Customers currently checked in via the membership app ({items.length}).
        </p>
        <button
          type="button"
          onClick={() => load()}
          className="rounded-md border border-zinc-200 px-3 py-1.5 text-sm font-semibold text-zinc-700 hover:bg-zinc-50"
        >
          Refresh
        </button>
      </div>

      {error ? <ErrorAlert message={error} /> : null}
      {loading && items.length === 0 ? <LoadingState /> : null}

      {!loading && items.length === 0 ? (
        <EmptyState message="Nobody on site yet. Open visits appear here when clients clock in." />
      ) : null}

      {items.length > 0 ? (
        <Card title="On site now">
          <ul className="divide-y divide-zinc-100">
            {items.map((visit) => {
              const name =
                visit.client?.resolved_display_name ||
                visit.client?.display_name ||
                visit.client?.first_name ||
                'Guest';
              return (
                <li key={visit.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                  <div>
                    <Link
                      href={`/admin/clients/${visit.client_id}`}
                      className="font-semibold text-[#2f5a45] hover:underline"
                    >
                      {name}
                    </Link>
                    <p className="text-sm text-zinc-500">
                      {visit.location?.name || 'No location'} · since {formatWhen(visit.checked_in_at)}
                    </p>
                  </div>
                  <p className="text-xs text-zinc-500">{visit.client?.phone || visit.client?.email || ''}</p>
                </li>
              );
            })}
          </ul>
        </Card>
      ) : null}
    </AdminCrmShell>
  );
}
