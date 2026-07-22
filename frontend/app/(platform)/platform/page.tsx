'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import {
  fetchPlatformOverview,
  type PlatformOverview,
} from '@/services/platform.service';

function formatMoney(cents: number): string {
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: 'GBP',
  }).format(cents / 100);
}

function Stat({
  label,
  value,
  hint,
}: {
  label: string;
  value: string;
  hint?: string;
}) {
  return (
    <div className="rounded-2xl border border-white/15 bg-white/[0.07] p-4">
      <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-stone-200">
        {label}
      </p>
      <p className="mt-2 text-2xl font-semibold tracking-tight text-white">{value}</p>
      {hint ? <p className="mt-1.5 text-xs text-stone-300">{hint}</p> : null}
    </div>
  );
}

export default function PlatformOverviewPage() {
  const [overview, setOverview] = useState<PlatformOverview | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setOverview(await fetchPlatformOverview());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load platform overview');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="mx-auto grid max-w-6xl gap-5">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-300">
            Platform
          </p>
          <h1 className="mt-1 text-2xl font-semibold tracking-tight text-white">Overview</h1>
          <p className="mt-1 text-sm text-stone-300">
            Cross-tenant health across the NeatMeet network.
          </p>
        </div>
        <div className="flex gap-2">
          <Link
            href="/platform/tenants"
            className="rounded-lg bg-[var(--platform-accent)] px-3 py-1.5 text-sm font-semibold text-white hover:brightness-110"
          >
            View tenants
          </Link>
          <button
            type="button"
            onClick={() => void load()}
            className="rounded-lg border border-white/25 bg-white/10 px-3 py-1.5 text-sm font-medium text-white hover:bg-white/15"
          >
            Refresh
          </button>
        </div>
      </div>

      {error ? <ErrorAlert message={error} /> : null}
      {loading && !overview ? <LoadingState label="Loading platform…" /> : null}

      {overview ? (
        <>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Stat
              label="Tenants"
              value={String(overview.tenants_total)}
              hint={`${overview.tenants_active} active · ${overview.tenants_suspended} suspended`}
            />
            <Stat
              label="Users"
              value={String(overview.users_total)}
              hint={`${overview.team_members_total} team members`}
            />
            <Stat
              label="Clients"
              value={String(overview.clients_total)}
              hint="All tenants"
            />
            <Stat
              label="Last 7 days"
              value={String(overview.appointments_last_7d)}
              hint={`${formatMoney(overview.payments_collected_last_7d_cents)} collected`}
            />
          </div>

          <Card
            className="border-white/15 bg-white/[0.07] text-stone-100 shadow-none"
            title="Network snapshot"
          >
            <dl className="grid gap-3 text-sm sm:grid-cols-2">
              <div className="flex justify-between gap-3 border-b border-white/15 pb-2">
                <dt className="text-stone-300">Active tenants</dt>
                <dd className="font-semibold text-white">{overview.tenants_active}</dd>
              </div>
              <div className="flex justify-between gap-3 border-b border-white/15 pb-2">
                <dt className="text-stone-300">Trial tenants</dt>
                <dd className="font-semibold text-white">{overview.tenants_trial}</dd>
              </div>
              <div className="flex justify-between gap-3 border-b border-white/15 pb-2">
                <dt className="text-stone-300">Appointments (7d)</dt>
                <dd className="font-semibold text-white">{overview.appointments_last_7d}</dd>
              </div>
              <div className="flex justify-between gap-3 border-b border-white/15 pb-2">
                <dt className="text-stone-300">Payments collected (7d)</dt>
                <dd className="font-semibold text-white">
                  {formatMoney(overview.payments_collected_last_7d_cents)}
                </dd>
              </div>
            </dl>
          </Card>
        </>
      ) : null}
    </div>
  );
}
