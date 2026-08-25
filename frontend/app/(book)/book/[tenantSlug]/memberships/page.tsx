'use client';

import Link from 'next/link';
import { Suspense, useEffect, useState, type CSSProperties } from 'react';
import { useParams, useRouter, useSearchParams } from 'next/navigation';
import {
  fetchPublicMembershipLanding,
  formatMoneyCents,
  type PublicMembershipLanding,
} from '@/services/public-memberships.service';
import { registerMemberServiceWorker } from '@/services/member-portal.service';
import {
  bookingPagePath,
  isMemberAppEntry,
  isStandaloneDisplay,
  openTenantBookingPage,
  promptTenantCustomerPwaInstall,
  tenantCustomerPwaInstallHint,
  tenantCustomerPwaPath,
  type BeforeInstallPromptEvent,
} from '@/lib/tenant-customer-pwa';

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
  const searchParams = useSearchParams();
  const router = useRouter();
  const tenantSlug = params.tenantSlug;
  const fromMemberApp = isMemberAppEntry(searchParams.get('from'));
  const [data, setData] = useState<PublicMembershipLanding | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [installEvent, setInstallEvent] = useState<BeforeInstallPromptEvent | null>(null);

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

  useEffect(() => {
    void registerMemberServiceWorker();
    const onBeforeInstall = (event: Event) => {
      event.preventDefault();
      setInstallEvent(event as BeforeInstallPromptEvent);
    };
    window.addEventListener('beforeinstallprompt', onBeforeInstall);
    return () => window.removeEventListener('beforeinstallprompt', onBeforeInstall);
  }, []);

  function goToBookingPage(fallbackPath: string) {
    const cameFromBooking = searchParams.get('from') === 'book';
    if (cameFromBooking) {
      router.back();
      return;
    }
    router.push(fallbackPath || bookingPagePath(tenantSlug));
  }

  function handleMembershipApp() {
    void promptTenantCustomerPwaInstall(tenantSlug, installEvent, (path) => router.push(path)).then(
      (result) => {
        if (result === 'manual') {
          window.alert(tenantCustomerPwaInstallHint());
        }
      },
    );
  }

  function returnToMemberHome() {
    openTenantBookingPage(tenantCustomerPwaPath(tenantSlug));
  }

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
        {fromMemberApp ? (
          <button type="button" onClick={returnToMemberHome} className={`${secondaryBtnClass()} mt-4`}>
            Back to membership home
          </button>
        ) : (
          <Link href={bookingPagePath(tenantSlug)} className={`${secondaryBtnClass()} mt-4`}>
            Back to booking
          </Link>
        )}
      </div>
    );
  }

  const salonName = data.tenant.branding?.brand_display_name || data.tenant.name;
  const accent = data.tenant.branding?.primary_color || '#2f5a45';
  const style = { ['--book-moss' as string]: accent } as CSSProperties;
  const joinHref = isStandaloneDisplay() ? data.paths.member : data.paths.book;

  return (
    <div className="min-h-screen bg-[var(--book-wash)] text-[var(--book-ink)]" style={style}>
      <header className="border-b border-[var(--book-line)] bg-[var(--book-surface)]">
        <div className="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
          <div className="min-w-0">
            <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-[var(--book-moss)]">
              {fromMemberApp ? 'Member pricing' : 'Memberships'}
            </p>
            <h1 className="book-display text-2xl font-bold tracking-tight sm:text-3xl">
              {fromMemberApp ? 'Book with member pricing' : salonName}
            </h1>
            {fromMemberApp ? (
              <p className="mt-1 text-sm text-[var(--book-muted)]">{salonName}</p>
            ) : null}
          </div>
          {!fromMemberApp ? (
            <nav className="flex flex-wrap items-center gap-3 text-sm">
              <button
                type="button"
                onClick={() => goToBookingPage(data.paths.book)}
                className="text-[var(--book-muted)] underline-offset-2 hover:underline"
              >
                Book
              </button>
              <Link href={joinHref} className="text-[var(--book-muted)] underline-offset-2 hover:underline">
                Join
              </Link>
              <button
                type="button"
                onClick={handleMembershipApp}
                className="text-[var(--book-muted)] underline-offset-2 hover:underline"
              >
                Membership app
              </button>
            </nav>
          ) : (
            <button
              type="button"
              onClick={returnToMemberHome}
              className="shrink-0 text-sm font-semibold text-[var(--book-moss)] underline-offset-2 hover:underline"
            >
              Back
            </button>
          )}
        </div>
      </header>

      <main className="mx-auto max-w-3xl space-y-10 px-4 py-8 sm:px-6 sm:py-12">
        <section className="space-y-3">
          <h2 className="book-display text-xl font-bold sm:text-2xl">
            {fromMemberApp ? 'Your member rates and benefits' : 'Choose what fits how you visit'}
          </h2>
          <p className="max-w-2xl text-sm leading-relaxed text-[var(--book-muted)]">
            {fromMemberApp
              ? 'Review membership plans, visit packages, and loyalty rewards available to you. Buy or renew from the Shop tab in your membership app.'
              : 'Three different benefits — pick one, combine them, or start with loyalty alone. Plans and packages are paid products; loyalty points are free rewards you earn.'}
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

        <section className="space-y-4">
          <div>
            <h3 className="text-sm font-semibold">Membership plans</h3>
            <p className="mt-1 text-xs text-[var(--book-muted)]">
              {fromMemberApp
                ? 'Ongoing membership with recurring perks and member rates.'
                : 'Ongoing membership — buy or renew in the member app after you join.'}
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
              {fromMemberApp
                ? 'Prepaid visit bundles you can use until sessions run out.'
                : 'Prepaid sessions — purchase in the member app or ask reception to assign one.'}
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

        {!fromMemberApp ? (
          <section className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
            <Link href={joinHref} className={primaryBtnClass()}>
              Join as a client
            </Link>
            <button type="button" onClick={handleMembershipApp} className={secondaryBtnClass()}>
              Open membership app to buy
            </button>
            <button
              type="button"
              onClick={() => goToBookingPage(data.paths.book)}
              className={secondaryBtnClass()}
            >
              Book an appointment
            </button>
          </section>
        ) : null}
      </main>
    </div>
  );
}
