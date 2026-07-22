'use client';

import { useEffect, useState } from 'react';
import { AdminMembershipsShell } from '@/components/admin/memberships/AdminMembershipsShell';
import { Card } from '@/components/ui/Card';
import { formatMoneyCents, type MembershipSummary } from '@/lib/memberships-types';
import { fetchMembershipSummary } from '@/services/memberships.service';

export default function MembershipsSummaryPage() {
  const [summary, setSummary] = useState<MembershipSummary | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchMembershipSummary()
      .then(setSummary)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load summary'));
  }, []);

  return (
    <AdminMembershipsShell title="Memberships overview">
      {error ? <p className="mb-4 text-sm text-red-600">{error}</p> : null}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Card title="Active subscriptions">
          <p className="text-2xl font-semibold">{summary?.active_subscriptions_count ?? '—'}</p>
        </Card>
        <Card title="MRR estimate">
          <p className="text-2xl font-semibold">{summary ? formatMoneyCents(summary.mrr_estimate_cents) : '—'}</p>
          <p className="mt-1 text-xs text-zinc-500">Monthly-normalized from active plans</p>
        </Card>
        <Card title="Wallet liability">
          <p className="text-2xl font-semibold">{summary ? formatMoneyCents(summary.wallet_liability_cents) : '—'}</p>
        </Card>
        <Card title="Outstanding packages">
          <p className="text-2xl font-semibold">{summary?.outstanding_package_balances_count ?? '—'}</p>
        </Card>
        <Card title="Loyalty points issued">
          <p className="text-2xl font-semibold">{summary?.loyalty_points_issued_total ?? '—'}</p>
          <p className="mt-1 text-xs text-zinc-500">Total credit entries (all time)</p>
        </Card>
      </div>
      <p className="mt-6 text-sm text-zinc-500">
        Admin-managed memberships only. Recurring billing, online purchase, and automatic POS earning are deferred.
      </p>
    </AdminMembershipsShell>
  );
}
