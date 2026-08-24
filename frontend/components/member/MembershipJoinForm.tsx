'use client';

import { useCallback, useEffect, useState, type FormEvent } from 'react';
import {
  fetchCrmJoinBootstrap,
  submitCrmJoin,
  type CrmJoinBootstrap,
} from '@/services/crm-join.service';
import { markMemberJoined } from '@/lib/tenant-customer-pwa';

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
    return <p className="mt-6 text-sm text-[var(--book-muted)]">Loading…</p>;
  }
  if (loadError) {
    return (
      <p className="mt-6 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        {loadError}
      </p>
    );
  }

  return (
    <form onSubmit={(e) => void handleSubmit(e)} className="mt-6 grid gap-4">
      <p className="text-xs font-semibold tracking-[0.04em] text-[var(--book-muted)]">
        Join Our Membership Family
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
          Next visit date <span className="text-red-600">*</span>
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

      <button type="submit" className={primaryBtnClass(submitting)} disabled={submitting || !acceptTerms}>
        {submitting ? 'Saving…' : 'Join Our Membership Family'}
      </button>
    </form>
  );
}
