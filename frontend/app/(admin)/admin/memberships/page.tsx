'use client';

import Link from 'next/link';
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
    <AdminMembershipsShell title="Overview">
      {error ? <p className="mb-4 text-sm text-red-600">{error}</p> : null}

      <div className="mb-6 rounded-2xl border border-[var(--admin-line)] bg-white p-4 sm:p-5">
        <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-zinc-500">
          How it works
        </p>
        <ol className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <li className="rounded-xl bg-zinc-50 px-3 py-3 text-sm">
            <span className="font-semibold text-zinc-900">1. Create offers</span>
            <p className="mt-1 text-xs text-zinc-600">Memberships or visit packs</p>
            <Link href="/admin/memberships/offers" className="mt-2 inline-block text-xs font-semibold text-[var(--admin-accent)] underline">
              Open offers
            </Link>
          </li>
          <li className="rounded-xl bg-zinc-50 px-3 py-3 text-sm">
            <span className="font-semibold text-zinc-900">2. Put on a client</span>
            <p className="mt-1 text-xs text-zinc-600">Enroll, sell pack, add credit</p>
            <Link href="/admin/memberships/clients" className="mt-2 inline-block text-xs font-semibold text-[var(--admin-accent)] underline">
              Client benefits
            </Link>
          </li>
          <li className="rounded-xl bg-zinc-50 px-3 py-3 text-sm">
            <span className="font-semibold text-zinc-900">3. Use at visit</span>
            <p className="mt-1 text-xs text-zinc-600">Deduct visits / credit in POS</p>
            <Link href="/admin/pos" className="mt-2 inline-block text-xs font-semibold text-[var(--admin-accent)] underline">
              Open POS
            </Link>
          </li>
          <li className="rounded-xl bg-zinc-50 px-3 py-3 text-sm">
            <span className="font-semibold text-zinc-900">4. Renew</span>
            <p className="mt-1 text-xs text-zinc-600">Period end or pack empty → sell again</p>
            <Link href="/admin/memberships/clients" className="mt-2 inline-block text-xs font-semibold text-[var(--admin-accent)] underline">
              Manage renewals
            </Link>
          </li>
        </ol>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Card title="Active members">
          <p className="text-2xl font-semibold">{summary?.active_subscriptions_count ?? '—'}</p>
          <p className="mt-1 text-xs text-zinc-500">Clients currently enrolled</p>
        </Card>
        <Card title="Monthly revenue (est.)">
          <p className="text-2xl font-semibold">
            {summary ? formatMoneyCents(summary.mrr_estimate_cents) : '—'}
          </p>
          <p className="mt-1 text-xs text-zinc-500">From active memberships</p>
        </Card>
        <Card title="Store credit owed">
          <p className="text-2xl font-semibold">
            {summary ? formatMoneyCents(summary.wallet_liability_cents) : '—'}
          </p>
          <p className="mt-1 text-xs text-zinc-500">Client money balances</p>
        </Card>
        <Card title="Open visit packs">
          <p className="text-2xl font-semibold">
            {summary?.outstanding_package_balances_count ?? '—'}
          </p>
          <p className="mt-1 text-xs text-zinc-500">Packs with visits still left</p>
        </Card>
        <Card title="Points issued">
          <p className="text-2xl font-semibold">{summary?.loyalty_points_issued_total ?? '—'}</p>
          <p className="mt-1 text-xs text-zinc-500">Loyalty credits (all time)</p>
        </Card>
      </div>
    </AdminMembershipsShell>
  );
}
