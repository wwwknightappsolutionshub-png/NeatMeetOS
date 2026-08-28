'use client';

import { useCallback, useEffect, useState } from 'react';
import {
  PlatformCard,
  PlatformErrorAlert,
  PlatformLinkButton,
  PlatformLoadingState,
  PlatformPage,
  PlatformPageIntro,
  PlatformStatCard,
  PlatformButton,
} from '@/components/platform/ui';
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
    <PlatformPage>
      <PlatformPageIntro
        title="Network overview"
        description="Cross-tenant health across the NeatMeet OS fleet — live counts and seven-day activity."
        actions={
          <>
            <PlatformLinkButton href="/platform/tenants" variant="primary">
              View tenants
            </PlatformLinkButton>
            <PlatformButton variant="secondary" onClick={() => void load()}>
              Refresh
            </PlatformButton>
          </>
        }
      />

      {error ? <PlatformErrorAlert message={error} /> : null}
      {loading && !overview ? <PlatformLoadingState label="Loading fleet metrics…" /> : null}

      {overview ? (
        <>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <PlatformStatCard
              label="Tenants"
              value={String(overview.tenants_total)}
              hint={`${overview.tenants_active} active · ${overview.tenants_suspended} suspended`}
            />
            <PlatformStatCard
              label="Users"
              value={String(overview.users_total)}
              hint={`${overview.team_members_total} team members`}
            />
            <PlatformStatCard
              label="Clients"
              value={String(overview.clients_total)}
              hint="All tenants"
            />
            <PlatformStatCard
              label="Last 7 days"
              value={String(overview.appointments_last_7d)}
              hint={`${formatMoney(overview.payments_collected_last_7d_cents)} collected`}
              tone="success"
            />
          </div>

          <PlatformCard title="Network snapshot">
            <dl className="grid gap-3 text-sm sm:grid-cols-2">
              {[
                ['Active tenants', overview.tenants_active],
                ['Trial tenants', overview.tenants_trial],
                ['Appointments (7d)', overview.appointments_last_7d],
                ['Payments collected (7d)', formatMoney(overview.payments_collected_last_7d_cents)],
              ].map(([label, value]) => (
                <div
                  key={String(label)}
                  className="flex justify-between gap-3 border-b border-[var(--platform-line-subtle)] pb-2"
                >
                  <dt className="text-[var(--platform-muted)]">{label}</dt>
                  <dd className="font-mono font-semibold text-white">{value}</dd>
                </div>
              ))}
            </dl>
          </PlatformCard>
        </>
      ) : null}
    </PlatformPage>
  );
}
