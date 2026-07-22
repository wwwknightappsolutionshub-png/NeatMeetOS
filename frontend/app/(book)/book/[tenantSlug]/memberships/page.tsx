'use client';

import Link from 'next/link';
import { Suspense, useEffect, useState, type CSSProperties } from 'react';
import { useParams } from 'next/navigation';
import {
  fetchPublicMembershipLanding,
  formatMoneyCents,
  type PublicMembershipLanding,
} from '@/services/public-memberships.service';

function primaryBtnClass(): string {
  return 'inline-flex items-center justify-center rounded-md bg-[var(--book-moss)] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[var(--book-moss-deep)]';
}

function secondaryBtnClass(): string {
  return 'inline-flex items-center justify-center rounded-md border border-[var(--book-line)] bg-white px-5 py-2.5 text-sm font-semibold text-[var(--book-ink)] transition hover:bg-[var(--book-wash)]';
}

export default function PublicMembershipsPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-screen items-center justify-center text-sm text-zinc-500">
          Loading memberships…
        </div>
      }
    >
      <PublicMembershipsPageInner />
    </Suspense>
  );
}

function PublicMembershipsPageInner() {
  const params = useParams<{ tenantSlug: string }>();
  const tenantSlug = params.tenantSlug;
  const [data, setData] = useState<PublicMembershipLanding | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    fetchPublicMembershipLanding(tenantSlug)
      .then((landing) => {
        if (!cancelled) setData(landing);
      })
      .catch((e) => {
        if (!cancelled) setError(e instanceof Error ? e.message : 'Unable to load memberships');
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [tenantSlug]);

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center text-sm text-[var(--book-muted)]">
        Loading memberships…
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="mx-auto max-w-lg px-4 py-16 text-center">
        <p className="text-sm text-red-700">{error ?? 'Not found'}</p>
        <Link href={`/book/${tenantSlug}`} className={`${secondaryBtnClass()} mt-4`}>
          Back to booking
        </Link>
      </div>
    );
  }

  const salonName = data.tenant.branding?.brand_display_name || data.tenant.name;
  const accent = data.tenant.branding?.primary_color || '#2f5a45';
  const style = { ['--book-moss' as string]: accent } as CSSProperties;

  return (
    <div className="min-h-screen bg-[var(--book-wash)] text-[var(--book-ink)]" style={style}>
      <header className="border-b border-[var(--book-line)] bg-[var(--book-surface)]">
        <div className="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-[var(--book-moss)]">
              Memberships
            </p>
            <h1 className="book-display text-2xl font-bold tracking-tight sm:text-3xl">{salonName}</h1>
          </div>
          <nav className="flex flex-wrap gap-2 text-sm">
            <Link href={data.paths.book} className="text-[var(--book-muted)] underline-offset-2 hover:underline">
              Book
            </Link>
            <Link href={data.paths.join} className="text-[var(--book-muted)] underline-offset-2 hover:underline">
              Join
            </Link>
            <Link href={data.paths.member} className="text-[var(--book-muted)] underline-offset-2 hover:underline">
              Member app
            </Link>
          </nav>
        </div>
      </header>

      <main className="mx-auto max-w-3xl space-y-10 px-4 py-8 sm:px-6 sm:py-12">
        <section className="space-y-3">
          <h2 className="book-display text-xl font-bold sm:text-2xl">Choose what fits how you visit</h2>
          <p className="max-w-2xl text-sm leading-relaxed text-[var(--book-muted)]">
            Three different benefits — pick one, combine them, or start with loyalty alone. Plans and
            packages are paid products; loyalty points are free rewards you earn.
          </p>
        </section>

        <section className="grid gap-3 sm:grid-cols-3">
          {data.education.map((card) => (
            <article
              key={card.key}
              className="rounded-2xl border border-[var(--book-line)] bg-[var(--book-surface)] p-4 shadow-[var(--book-shadow)]"
            >
              <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-[var(--book-moss)]">
                {card.title}
              </p>
              <p className="mt-2 text-sm font-semibold text-[var(--book-ink)]">{card.summary}</p>
              <p className="mt-2 text-xs leading-relaxed text-[var(--book-muted)]">
                <span className="font-semibold text-[var(--book-ink)]">Best for:</span> {card.best_for}
              </p>
              <p className="mt-2 text-xs leading-relaxed text-[var(--book-muted)]">{card.how_it_works}</p>
            </article>
          ))}
        </section>

        <section className="overflow-hidden rounded-2xl border border-[var(--book-line)] bg-[var(--book-surface)] shadow-[var(--book-shadow)]">
          <div className="border-b border-[var(--book-line)] px-4 py-3">
            <h3 className="text-sm font-semibold">Quick comparison</h3>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[32rem] text-left text-sm">
              <thead>
                <tr className="border-b border-[var(--book-line)] text-[var(--book-muted)]">
                  <th className="px-4 py-2 font-medium"> </th>
                  <th className="px-4 py-2 font-medium">Plan</th>
                  <th className="px-4 py-2 font-medium">Package</th>
                  <th className="px-4 py-2 font-medium">Loyalty</th>
                </tr>
              </thead>
              <tbody>
                {data.comparison.map((row) => (
                  <tr key={row.aspect} className="border-b border-[var(--book-line)] last:border-0">
                    <td className="px-4 py-2.5 font-medium text-[var(--book-ink)]">{row.aspect}</td>
                    <td className="px-4 py-2.5 text-[var(--book-muted)]">{row.plan}</td>
                    <td className="px-4 py-2.5 text-[var(--book-muted)]">{row.package}</td>
                    <td className="px-4 py-2.5 text-[var(--book-muted)]">{row.loyalty}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>

        <section className="space-y-4">
          <div>
            <h3 className="text-sm font-semibold">Membership plans</h3>
            <p className="mt-1 text-xs text-[var(--book-muted)]">
              Ongoing membership — buy or renew in the member app after you join.
            </p>
          </div>
          {data.offers.plans.length === 0 ? (
            <p className="rounded-xl border border-dashed border-[var(--book-line)] px-4 py-6 text-sm text-[var(--book-muted)]">
              No public membership plans yet. Ask the salon what’s available.
            </p>
          ) : (
            <ul className="space-y-3">
              {data.offers.plans.map((plan) => (
                <li
                  key={plan.id}
                  className="rounded-2xl border border-[var(--book-line)] bg-[var(--book-surface)] p-4"
                >
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                      <p className="font-semibold">{plan.name}</p>
                      <p className="mt-0.5 text-sm text-[var(--book-muted)]">
                        {formatMoneyCents(plan.price_cents)}
                        {plan.billing_frequency ? ` / ${plan.billing_frequency.replace(/_/g, ' ')}` : ''}
                        {plan.joining_fee_cents > 0
                          ? ` · + ${formatMoneyCents(plan.joining_fee_cents)} joining`
                          : ''}
                      </p>
                    </div>
                    <span className="rounded-full bg-[var(--book-moss-soft)] px-2.5 py-0.5 text-[11px] font-semibold text-[var(--book-moss)]">
                      Plan
                    </span>
                  </div>
                  {plan.description ? (
                    <p className="mt-2 text-sm text-[var(--book-muted)]">{plan.description}</p>
                  ) : null}
                  <ul className="mt-2 list-disc space-y-1 pl-4 text-xs text-[var(--book-muted)]">
                    <li>{plan.best_for}</li>
                    {plan.included_wallet_credit_cents > 0 ? (
                      <li>{formatMoneyCents(plan.included_wallet_credit_cents)} wallet credit included</li>
                    ) : null}
                    {plan.included_loyalty_points > 0 ? (
                      <li>{plan.included_loyalty_points} loyalty points included</li>
                    ) : null}
                  </ul>
                </li>
              ))}
            </ul>
          )}
        </section>

        <section className="space-y-4">
          <div>
            <h3 className="text-sm font-semibold">Visit packages</h3>
            <p className="mt-1 text-xs text-[var(--book-muted)]">
              Prepaid sessions — purchase in the member app or ask reception to assign one.
            </p>
          </div>
          {data.offers.packages.length === 0 ? (
            <p className="rounded-xl border border-dashed border-[var(--book-line)] px-4 py-6 text-sm text-[var(--book-muted)]">
              No public packages yet. Ask the salon for session bundles.
            </p>
          ) : (
            <ul className="space-y-3">
              {data.offers.packages.map((pkg) => (
                <li
                  key={pkg.id}
                  className="rounded-2xl border border-[var(--book-line)] bg-[var(--book-surface)] p-4"
                >
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                      <p className="font-semibold">{pkg.name}</p>
                      <p className="mt-0.5 text-sm text-[var(--book-muted)]">
                        {formatMoneyCents(pkg.price_cents)} · {pkg.included_quantity} visits
                      </p>
                    </div>
                    <span className="rounded-full bg-[var(--book-moss-soft)] px-2.5 py-0.5 text-[11px] font-semibold text-[var(--book-moss)]">
                      Package
                    </span>
                  </div>
                  {pkg.description ? (
                    <p className="mt-2 text-sm text-[var(--book-muted)]">{pkg.description}</p>
                  ) : null}
                  <ul className="mt-2 list-disc space-y-1 pl-4 text-xs text-[var(--book-muted)]">
                    <li>{pkg.best_for}</li>
                    {pkg.expiry_days ? <li>Valid for {pkg.expiry_days} days after purchase</li> : null}
                  </ul>
                </li>
              ))}
            </ul>
          )}
        </section>

        <section className="rounded-2xl border border-[var(--book-line)] bg-[var(--book-surface)] p-5 shadow-[var(--book-shadow)]">
          <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-[var(--book-moss)]">
            Loyalty
          </p>
          <h3 className="mt-1 text-base font-semibold">Free points — you don’t buy this</h3>
          <p className="mt-2 text-sm text-[var(--book-muted)]">
            Earn points when you visit (for example via check-in in the member app).
            {data.loyalty.crm_join_signup_points > 0
              ? ` New clients may receive ${data.loyalty.crm_join_signup_points} points when they join.`
              : ''}
            {data.loyalty.redemption_enabled
              ? ` Redeem ${data.loyalty.points_per_redemption_block} pts for ${formatMoneyCents(data.loyalty.value_cents_per_block)} at checkout when enabled.`
              : ' Ask the salon when redemption is available at checkout.'}
          </p>
        </section>

        <section className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
          <Link href={data.paths.join} className={primaryBtnClass()}>
            Join as a client
          </Link>
          <Link href={data.paths.member} className={secondaryBtnClass()}>
            Open member app to buy
          </Link>
          <Link href={data.paths.book} className={secondaryBtnClass()}>
            Book an appointment
          </Link>
        </section>
      </main>
    </div>
  );
}
