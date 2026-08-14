'use client';

import Link from 'next/link';
import { AdminMembershipsShell } from '@/components/admin/memberships/AdminMembershipsShell';
import { Card } from '@/components/ui/Card';

function OfferCard({
  href,
  title,
  body,
  cta,
}: {
  href: string;
  title: string;
  body: string;
  cta: string;
}) {
  return (
    <Card title={title}>
      <p className="text-sm text-zinc-600">{body}</p>
      <Link
        href={href}
        className="mt-4 inline-flex rounded-lg bg-[var(--admin-accent)] px-3 py-2 text-sm font-semibold text-white hover:brightness-110"
      >
        {cta}
      </Link>
    </Card>
  );
}

export default function MembershipOffersHubPage() {
  return (
    <AdminMembershipsShell title="Offers — what you sell">
      <p className="mb-4 text-sm text-zinc-600">
        Create the products first. Then go to <strong>Client benefits</strong> to put them on a
        client.
      </p>
      <div className="grid gap-4 md:grid-cols-2">
        <OfferCard
          href="/admin/memberships/plans"
          title="Memberships"
          body="Recurring club (monthly / yearly). Clients stay enrolled until you pause or cancel. Can include store credit or points each period."
          cta="Create / edit memberships"
        />
        <OfferCard
          href="/admin/memberships/packages"
          title="Visit packs"
          body="Prepaid visits (e.g. 6 blow-drys). Sold once; each visit uses 1 from the pack until it runs out."
          cta="Create / edit visit packs"
        />
      </div>
    </AdminMembershipsShell>
  );
}
