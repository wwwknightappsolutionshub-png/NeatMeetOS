'use client';

import { useCallback, useEffect, useState, type FormEvent } from 'react';
import {
  fetchCrmJoinBootstrap,
  submitCrmJoin,
  type CrmJoinBootstrap,
  type CrmJoinLoyaltyOffer,
  type CrmJoinMembershipOffer,
  type CrmJoinPackageOffer,
} from '@/services/crm-join.service';
import { markMemberJoined } from '@/lib/tenant-customer-pwa';
import { TurnstileFormGate } from '@/components/security/TurnstileBootstrap';
import { useTurnstileReady } from '@/hooks/useTurnstileReady';

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
          Tap an offer to see benefits. Fill the salon customer form below, then ask us to enrol you.{' '}
          <a
            href={`/book/${tenantSlug}/memberships?from=member`}
            className="font-semibold text-[var(--book-moss)] underline-offset-2 hover:underline"
          >
            Compare plan vs package vs loyalty
          </a>
        </p>
      </div>
      {offers.memberships.map((offer) => (
        <MembershipOfferCard key={offer.id} offer={offer} />
      ))}
      {offers.packages.map((offer) => (
        <PackageOfferCard key={offer.id} offer={offer} />
      ))}
      {offers.loyalty ? <LoyaltyOfferCard offer={offers.loyalty} /> : null}
    </section>
  );
}

export interface MembershipJoinFormProps {
  tenantSlug: string;
  referralCode?: string;
  locationFromQuery?: string | null;
  onJoined: (details: { email: string; phone: string }) => void;
}

export function MembershipJoinForm({
  tenantSlug,
  referralCode,
  locationFromQuery,
  onJoined,
}: MembershipJoinFormProps) {
  const [bootstrap, setBootstrap] = useState<CrmJoinBootstrap | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [preferredName, setPreferredName] = useState('');
  const [whatsapp, setWhatsapp] = useState('');
  const [email, setEmail] = useState('');
  const [locationId, setLocationId] = useState('');
  const [nextVisitDate, setNextVisitDate] = useState('');
  const [specialDate, setSpecialDate] = useState('');
  const [specialEventLabel, setSpecialEventLabel] = useState('');
  const [acceptTerms, setAcceptTerms] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const turnstileReady = useTurnstileReady();

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

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    setSubmitError(null);
    try {
      await submitCrmJoin(tenantSlug, {
        preferred_name: preferredName.trim(),
        whatsapp_number: whatsapp.trim(),
        email: email.trim(),
        next_visit_date: nextVisitDate,
        accept_terms: acceptTerms,
        location_id: locationId || undefined,
        special_date: specialDate.trim() || undefined,
        special_event_label: specialEventLabel.trim() || undefined,
        referral_code: referralCode,
      });
      markMemberJoined(tenantSlug);
      onJoined({ email: email.trim(), phone: whatsapp.trim() });
    } catch (err) {
      setSubmitError(err instanceof Error ? err.message : 'Could not save details');
    } finally {
      setSubmitting(false);
    }
  }

  if (loading) {
    return <p className="mt-6 text-sm text-[var(--book-muted)]">Loading the salon customer form…</p>;
  }
  if (loadError) {
    return (
      <p className="mt-6 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        {loadError}
      </p>
    );
  }

  return (
    <div className="mt-2">
      <h1 className="book-display text-3xl font-bold text-[var(--book-ink)]">
        Your Loyalty Is Treasured
      </h1>
      <p className="mt-2 text-sm font-semibold text-[var(--book-moss)]">
        Next Visit Is Tied Down
      </p>
      <p className="mt-2 text-sm leading-relaxed text-[var(--book-muted)]">
        You have many benefits to gain when you fill the form below and become a loyal partner.
      </p>

      {bootstrap ? (
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

      <form onSubmit={(e) => void handleSubmit(e)} className="mt-6 grid gap-4">
        <p className="text-xs font-semibold uppercase tracking-[0.14em] text-[var(--book-moss)]">
          Salon customer
        </p>
        <label className="block text-sm">
          <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">
            Preferred name / nickname <span className="text-red-600">*</span>
          </span>
          <input
            className={fieldClass()}
            required
            value={preferredName}
            onChange={(e) => setPreferredName(e.target.value)}
            autoComplete="nickname"
          />
        </label>
        <label className="block text-sm">
          <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">
            Phone number (WhatsApp) <span className="text-red-600">*</span>
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
            Email address <span className="text-red-600">*</span>
          </span>
          <input
            className={fieldClass()}
            required
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            autoComplete="email"
          />
        </label>
        <label className="block text-sm">
          <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">
            When next would you visit <span className="text-red-600">*</span>
          </span>
          <input
            className={fieldClass()}
            required
            type="date"
            value={nextVisitDate}
            min={new Date().toISOString().slice(0, 10)}
            onChange={(e) => setNextVisitDate(e.target.value)}
          />
        </label>
        <label className="block text-sm">
          <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">
            Special date in your life <span className="font-normal">(optional)</span>
          </span>
          <input
            className={fieldClass()}
            type="date"
            value={specialDate}
            onChange={(e) => setSpecialDate(e.target.value)}
          />
        </label>
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

        <label className="flex items-start gap-3 text-sm text-[var(--book-muted)]">
          <input
            type="checkbox"
            className="mt-1"
            checked={acceptTerms}
            onChange={(e) => setAcceptTerms(e.target.checked)}
            required
          />
          <span>
            I agree to the{' '}
            <a
              href={bootstrap?.terms_url || '/terms'}
              target="_blank"
              rel="noreferrer"
              className="font-semibold text-[var(--book-moss)] underline-offset-2 hover:underline"
            >
              Terms &amp; Conditions
            </a>
            .
          </span>
        </label>

        {submitError ? (
          <p className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {submitError}
          </p>
        ) : null}

        <TurnstileFormGate size="compact" deferExecution />

        <button
          type="submit"
          className={primaryBtnClass(submitting || !turnstileReady || !acceptTerms)}
          disabled={submitting || !turnstileReady || !acceptTerms}
        >
          {submitting ? 'Saving…' : 'Join Now'}
        </button>
        <p className="text-center text-xs text-[var(--book-muted)]">
          We&apos;ll use your WhatsApp number to send login codes and appointment updates.
        </p>
      </form>
    </div>
  );
}
