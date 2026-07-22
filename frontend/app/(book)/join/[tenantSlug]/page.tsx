'use client';

import { Suspense, useCallback, useEffect, useMemo, useState, type CSSProperties } from 'react';
import { useParams, useSearchParams } from 'next/navigation';
import {
  fetchCrmJoinBootstrap,
  submitCrmJoin,
  type CrmJoinBootstrap,
  type CrmJoinLoyaltyOffer,
  type CrmJoinMembershipOffer,
  type CrmJoinPackageOffer,
} from '@/services/crm-join.service';
import { SocialFooterIcons } from '@/components/public/SocialFooterIcons';

function fieldClass(): string {
  return 'w-full rounded-md border border-[var(--book-line)] bg-white px-3 py-2.5 text-sm text-[var(--book-ink)] outline-none transition focus:border-[var(--book-moss)] focus:ring-2 focus:ring-[var(--book-moss-soft)]';
}

function primaryBtnClass(disabled?: boolean): string {
  return [
    'inline-flex w-full items-center justify-center rounded-md px-5 py-2.5 text-sm font-semibold tracking-wide transition',
    'bg-[var(--book-moss)] text-white hover:bg-[var(--book-moss-deep)]',
    disabled ? 'cursor-not-allowed opacity-50' : '',
  ].join(' ');
}

function formatMoney(cents: number | null | undefined): string {
  if (cents == null) return '';
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'GBP' }).format(cents / 100);
}

function billingLabel(freq: string | null): string {
  if (!freq) return '';
  return freq.replace(/_/g, ' ');
}

function MembershipOfferCard({ offer }: { offer: CrmJoinMembershipOffer }) {
  const [open, setOpen] = useState(false);
  const benefits: string[] = [];
  if (offer.included_wallet_credit_cents) {
    benefits.push(`${formatMoney(offer.included_wallet_credit_cents)} wallet credit each period`);
  }
  if (offer.included_loyalty_points) {
    benefits.push(`${offer.included_loyalty_points} loyalty points on join`);
  }
  if (offer.included_entitlement_quantity) {
    benefits.push(`${offer.included_entitlement_quantity} included sessions`);
  }
  benefits.push('Member rates on listed services');

  return (
    <div className="rounded-xl border border-[var(--book-line)] bg-[var(--book-wash)] p-3">
      <button
        type="button"
        className="flex w-full items-start justify-between gap-2 text-left"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
      >
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-[var(--book-moss)]">
            Membership
          </p>
          <p className="mt-1 font-semibold text-[var(--book-ink)]">{offer.name}</p>
          <p className="mt-0.5 text-sm text-[var(--book-muted)]">
            {formatMoney(offer.price_cents)}
            {offer.billing_frequency ? ` / ${billingLabel(offer.billing_frequency)}` : ''}
          </p>
        </div>
        <span className="shrink-0 text-sm font-semibold text-[var(--book-moss)]">
          {open ? 'Hide' : 'See offer'}
        </span>
      </button>
      {open ? (
        <div className="mt-3 border-t border-[var(--book-line)] pt-3 text-sm text-[var(--book-muted)]">
          {offer.description ? <p>{offer.description}</p> : null}
          <ul className="mt-2 list-disc space-y-1 pl-4">
            {benefits.map((b) => (
              <li key={b}>{b}</li>
            ))}
            {offer.joining_fee_cents ? (
              <li>Joining fee {formatMoney(offer.joining_fee_cents)}</li>
            ) : null}
          </ul>
          <p className="mt-2 text-xs">Ask the salon to enrol you after saving your details.</p>
        </div>
      ) : null}
    </div>
  );
}

function PackageOfferCard({ offer }: { offer: CrmJoinPackageOffer }) {
  const [open, setOpen] = useState(false);
  return (
    <div className="rounded-xl border border-[var(--book-line)] bg-[var(--book-wash)] p-3">
      <button
        type="button"
        className="flex w-full items-start justify-between gap-2 text-left"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
      >
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-[var(--book-moss)]">
            Package
          </p>
          <p className="mt-1 font-semibold text-[var(--book-ink)]">{offer.name}</p>
          <p className="mt-0.5 text-sm text-[var(--book-muted)]">
            {formatMoney(offer.price_cents)} · {offer.included_quantity} sessions
          </p>
        </div>
        <span className="shrink-0 text-sm font-semibold text-[var(--book-moss)]">
          {open ? 'Hide' : 'See offer'}
        </span>
      </button>
      {open ? (
        <div className="mt-3 border-t border-[var(--book-line)] pt-3 text-sm text-[var(--book-muted)]">
          {offer.description ? <p>{offer.description}</p> : null}
          {offer.expiry_days ? (
            <p className="mt-2">Valid for {offer.expiry_days} days after purchase.</p>
          ) : null}
          <p className="mt-2 text-xs">Ask the salon to assign this package after joining.</p>
        </div>
      ) : null}
    </div>
  );
}

function LoyaltyOfferCard({ offer }: { offer: CrmJoinLoyaltyOffer }) {
  const [open, setOpen] = useState(false);
  return (
    <div className="rounded-xl border border-[var(--book-line)] bg-[var(--book-wash)] p-3">
      <button
        type="button"
        className="flex w-full items-start justify-between gap-2 text-left"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
      >
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-[var(--book-moss)]">
            Loyalty
          </p>
          <p className="mt-1 font-semibold text-[var(--book-ink)]">{offer.headline}</p>
          <p className="mt-0.5 text-sm text-[var(--book-muted)]">
            {offer.points_per_redemption_block} pts → {formatMoney(offer.value_cents_per_block)}
          </p>
        </div>
        <span className="shrink-0 text-sm font-semibold text-[var(--book-moss)]">
          {open ? 'Hide' : 'See benefits'}
        </span>
      </button>
      {open ? (
        <div className="mt-3 border-t border-[var(--book-line)] pt-3 text-sm text-[var(--book-muted)]">
          <p>{offer.description}</p>
        </div>
      ) : null}
    </div>
  );
}

function JoinOffers({
  offers,
  tenantSlug,
}: {
  offers: CrmJoinBootstrap['offers'];
  tenantSlug: string;
}) {
  const hasAnything =
    offers.memberships.length > 0 || offers.packages.length > 0 || offers.loyalty != null;
  if (!hasAnything) return null;

  return (
    <section className="mt-6 space-y-3">
      <div>
        <h2 className="text-sm font-semibold uppercase tracking-[0.14em] text-[var(--book-muted)]">
          Offers for you
        </h2>
        <p className="mt-1 text-sm text-[var(--book-muted)]">
          Tap an offer to see benefits. Join the list below, then ask us to enrol you.{' '}
          <a
            href={`/book/${tenantSlug}/memberships`}
            className="font-semibold text-[var(--book-moss)] underline-offset-2 hover:underline"
          >
            Compare plan vs package vs loyalty
          </a>
        </p>
      </div>
      {offers.memberships.map((m) => (
        <MembershipOfferCard key={m.id} offer={m} />
      ))}
      {offers.packages.map((p) => (
        <PackageOfferCard key={p.id} offer={p} />
      ))}
      {offers.loyalty ? <LoyaltyOfferCard offer={offers.loyalty} /> : null}
    </section>
  );
}

function CrmJoinFormInner() {
  const params = useParams<{ tenantSlug: string }>();
  const search = useSearchParams();
  const tenantSlug = params.tenantSlug;
  const locationFromQuery = search.get('location');
  const referralCode = search.get('ref')?.trim() || undefined;

  const [bootstrap, setBootstrap] = useState<CrmJoinBootstrap | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);

  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [whatsapp, setWhatsapp] = useState('');
  const [email, setEmail] = useState('');
  const [locationId, setLocationId] = useState('');
  const [specialEventMonth, setSpecialEventMonth] = useState('');
  const [specialEventDay, setSpecialEventDay] = useState('');
  const [specialEventLabel, setSpecialEventLabel] = useState('');
  const [dateOfBirth, setDateOfBirth] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [doneMessage, setDoneMessage] = useState<string | null>(null);
  const [closeIn, setCloseIn] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setLoadError(null);
    try {
      const data = await fetchCrmJoinBootstrap(tenantSlug, locationFromQuery || undefined);
      setBootstrap(data);
      const matched = locationFromQuery
        ? data.locations.find((l) => l.id === locationFromQuery)
        : null;
      if (matched) setLocationId(matched.id);
      else if (data.locations[0]) setLocationId(data.locations[0].id);
    } catch (e) {
      setLoadError(e instanceof Error ? e.message : 'Unable to load form');
    } finally {
      setLoading(false);
    }
  }, [tenantSlug, locationFromQuery]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    if (!doneMessage) {
      setCloseIn(null);
      return;
    }
    setCloseIn(8);
    const tick = window.setInterval(() => {
      setCloseIn((prev) => {
        if (prev === null) return null;
        if (prev <= 1) {
          window.clearInterval(tick);
          window.close();
          // Tabs not opened by script often ignore window.close(); leave a quiet finish state.
          window.setTimeout(() => {
            if (!document.hidden) {
              window.location.replace(`/book/${tenantSlug}`);
            }
          }, 250);
          return 0;
        }
        return prev - 1;
      });
    }, 1000);
    return () => window.clearInterval(tick);
  }, [doneMessage, tenantSlug]);

  const salonName = useMemo(() => {
    const brand = bootstrap?.tenant.branding?.brand_display_name;
    return brand || bootstrap?.tenant.name || tenantSlug;
  }, [bootstrap, tenantSlug]);

  const accent = bootstrap?.tenant.branding?.primary_color || '#2f5a45';

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    setSubmitError(null);
    try {
      const result = await submitCrmJoin(tenantSlug, {
        first_name: firstName.trim(),
        last_name: lastName.trim() || undefined,
        whatsapp_number: whatsapp.trim(),
        email: email.trim() || undefined,
        location_id: locationId || undefined,
        special_event_month: specialEventMonth ? Number(specialEventMonth) : undefined,
        special_event_day: specialEventDay ? Number(specialEventDay) : undefined,
        special_event_label: specialEventLabel.trim() || undefined,
        date_of_birth: dateOfBirth.trim() || undefined,
        referral_code: referralCode,
      });
      setDoneMessage(result.message);
    } catch (err) {
      setSubmitError(err instanceof Error ? err.message : 'Could not save details');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="book-portal min-h-screen" style={{ ['--book-moss' as string]: accent } as CSSProperties}>
      <main className="mx-auto flex min-h-screen max-w-lg flex-col justify-center px-4 py-10 sm:px-6">
        <div className="rounded-2xl border border-[var(--book-line)] bg-white p-6 shadow-[var(--book-shadow)] sm:p-8">
          <p className="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--book-muted)]">
            Client details
          </p>
          <h1 className="book-display mt-2 text-3xl font-bold text-[var(--book-ink)]">{salonName}</h1>
          <p className="mt-2 text-sm text-[var(--book-muted)]">
            Quick form so we can keep you on our client list. WhatsApp is required. Add an email to
            receive a branded welcome with membership and loyalty offers.
          </p>

          {loading ? (
            <p className="mt-8 text-sm text-[var(--book-muted)]">Loading…</p>
          ) : null}
          {loadError ? (
            <p className="mt-8 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
              {loadError}
            </p>
          ) : null}

          {!loading && !loadError && bootstrap && !doneMessage ? (
            <JoinOffers
              tenantSlug={tenantSlug}
              offers={
                bootstrap.offers ?? {
                  memberships: [],
                  packages: [],
                  loyalty: null,
                }
              }
            />
          ) : null}

          {!loading && !loadError && doneMessage ? (
            <div className="mt-8 space-y-4">
              <div className="rounded-xl bg-[var(--book-wash)] px-4 py-6 text-center">
                <p className="text-lg font-semibold text-[var(--book-ink)]">{doneMessage}</p>
                {closeIn !== null && closeIn > 0 ? (
                  <p className="mt-3 text-xs text-[var(--book-muted)]">
                    This page closes in {closeIn}s
                  </p>
                ) : null}
              </div>
              <a
                href="mailto:"
                className={primaryBtnClass()}
              >
                Check mail
              </a>
            </div>
          ) : null}

          {!loading && !loadError && !doneMessage ? (
            <form onSubmit={(e) => void handleSubmit(e)} className="mt-8 grid gap-4">
              <label className="block text-sm">
                <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">First name</span>
                <input
                  className={fieldClass()}
                  required
                  value={firstName}
                  onChange={(e) => setFirstName(e.target.value)}
                  autoComplete="given-name"
                />
              </label>
              <label className="block text-sm">
                <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">
                  Last name <span className="font-normal">(optional)</span>
                </span>
                <input
                  className={fieldClass()}
                  value={lastName}
                  onChange={(e) => setLastName(e.target.value)}
                  autoComplete="family-name"
                />
              </label>
              <label className="block text-sm">
                <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">
                  WhatsApp number <span className="text-red-600">*</span>
                </span>
                <input
                  className={fieldClass()}
                  required
                  type="tel"
                  inputMode="tel"
                  placeholder="+44…"
                  value={whatsapp}
                  onChange={(e) => setWhatsapp(e.target.value)}
                  autoComplete="tel"
                />
              </label>
              <label className="block text-sm">
                <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">
                  Email <span className="font-normal">(optional)</span>
                </span>
                <input
                  className={fieldClass()}
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  autoComplete="email"
                />
              </label>
              {(bootstrap?.locations.length ?? 0) > 1 ? (
                <label className="block text-sm">
                  <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">Location</span>
                  <select
                    className={fieldClass()}
                    value={locationId}
                    onChange={(e) => setLocationId(e.target.value)}
                  >
                    {bootstrap?.locations.map((loc) => (
                      <option key={loc.id} value={loc.id}>
                        {loc.name}
                      </option>
                    ))}
                  </select>
                </label>
              ) : null}

              <label className="block text-sm">
                <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">
                  Date of birth <span className="font-normal">(optional — for birthday greetings)</span>
                </span>
                <input
                  className={fieldClass()}
                  type="date"
                  value={dateOfBirth}
                  onChange={(e) => setDateOfBirth(e.target.value)}
                  max={new Date().toISOString().slice(0, 10)}
                />
              </label>

              <div className="grid grid-cols-2 gap-3">
                <label className="block text-sm">
                  <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">
                    Special event month <span className="font-normal">(optional)</span>
                  </span>
                  <select
                    className={fieldClass()}
                    value={specialEventMonth}
                    onChange={(e) => setSpecialEventMonth(e.target.value)}
                  >
                    <option value="">—</option>
                    {[
                      ['1', 'January'],
                      ['2', 'February'],
                      ['3', 'March'],
                      ['4', 'April'],
                      ['5', 'May'],
                      ['6', 'June'],
                      ['7', 'July'],
                      ['8', 'August'],
                      ['9', 'September'],
                      ['10', 'October'],
                      ['11', 'November'],
                      ['12', 'December'],
                    ].map(([value, label]) => (
                      <option key={value} value={value}>
                        {label}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="block text-sm">
                  <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">
                    Day <span className="font-normal">(optional)</span>
                  </span>
                  <select
                    className={fieldClass()}
                    value={specialEventDay}
                    onChange={(e) => setSpecialEventDay(e.target.value)}
                  >
                    <option value="">—</option>
                    {Array.from({ length: 31 }, (_, i) => i + 1).map((d) => (
                      <option key={d} value={d}>
                        {d}
                      </option>
                    ))}
                  </select>
                </label>
              </div>
              <label className="block text-sm">
                <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">
                  What is it? <span className="font-normal">(birthday, anniversary…)</span>
                </span>
                <input
                  className={fieldClass()}
                  value={specialEventLabel}
                  onChange={(e) => setSpecialEventLabel(e.target.value)}
                  placeholder="Birthday, anniversary…"
                />
              </label>

              {submitError ? (
                <p className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                  {submitError}
                </p>
              ) : null}

              <button type="submit" className={primaryBtnClass(submitting)} disabled={submitting}>
                {submitting ? 'Saving…' : 'Save my details'}
              </button>
              <p className="text-center text-xs text-[var(--book-muted)]">
                We’ll use your WhatsApp number to contact you about appointments.
              </p>
            </form>
          ) : null}
        </div>
        <SocialFooterIcons
          className="mt-10"
          facebookUrl={bootstrap?.tenant.branding?.social_facebook_url}
          instagramUrl={bootstrap?.tenant.branding?.social_instagram_url}
          tiktokUrl={bootstrap?.tenant.branding?.social_tiktok_url}
        />
      </main>
    </div>
  );
}

export default function CrmJoinPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-screen items-center justify-center text-sm text-zinc-500">
          Loading…
        </div>
      }
    >
      <CrmJoinFormInner />
    </Suspense>
  );
}
