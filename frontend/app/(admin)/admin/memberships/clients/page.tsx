'use client';

import Link from 'next/link';
import { AdminMembershipsShell } from '@/components/admin/memberships/AdminMembershipsShell';
import { Card } from '@/components/ui/Card';

function ActionCard({
  href,
  step,
  title,
  body,
  cta,
}: {
  href: string;
  step: string;
  title: string;
  body: string;
  cta: string;
}) {
  return (
    <Card title={title}>
      <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-500">{step}</p>
      <p className="mt-2 text-sm text-zinc-600">{body}</p>
      <Link
        href={href}
        className="mt-4 inline-flex rounded-lg border border-[var(--admin-line)] bg-white px-3 py-2 text-sm font-semibold text-[var(--admin-ink)] hover:bg-[var(--admin-wash)]"
      >
        {cta}
      </Link>
    </Card>
  );
}

export default function MembershipClientsHubPage() {
  return (
    <AdminMembershipsShell title="Client benefits — allocate & renew">
      <p className="mb-4 text-sm text-zinc-600">
        Put an offer on a client, adjust balances when needed, and renew when a period ends or a pack
        runs out. Day-to-day use at checkout still happens in POS / day board.
      </p>
      <div className="grid gap-4 sm:grid-cols-2">
        <ActionCard
          href="/admin/memberships/subscriptions"
          step="Allocate · Membership"
          title="Enroll members"
          body="Assign a membership to a client. Pause, resume, or cancel when they renew or leave."
          cta="Enroll / manage members"
        />
        <ActionCard
          href="/admin/memberships/client-packages"
          step="Allocate · Visit pack"
          title="Sell visit packs"
          body="Give a client a pack and track visits left. Redeem 1 when a visit is used (or restore if undone)."
          cta="Sell / use visit packs"
        />
        <ActionCard
          href="/admin/memberships/wallet"
          step="Adjust · Money credit"
          title="Store credit"
          body="Top up or deduct a client’s money balance (store credit). Applied at POS as cash-like credit."
          cta="Adjust store credit"
        />
        <ActionCard
          href="/admin/memberships/loyalty"
          step="Adjust · Points"
          title="Loyalty points"
          body="Award or deduct points. Redemption value is controlled in Settings."
          cta="Adjust points"
        />
      </div>
    </AdminMembershipsShell>
  );
}
