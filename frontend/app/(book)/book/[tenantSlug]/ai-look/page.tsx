'use client';

import { Suspense, useCallback, useEffect, useState, type CSSProperties } from 'react';
import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import { markAiHairstyleLandingSkipped } from '@/lib/ai-hairstyle-landing';
import type { AiHairstyleSession } from '@/lib/ai-hairstyle-types';
import type { OnlineBookingCatalog } from '@/lib/booking-types';
import { resolveMediaUrl } from '@/lib/media-url';
import {
  createAiHairstyleSession,
  generateAiHairstylePreviews,
  pollAiHairstyleSessionUntilSettled,
  selectAiHairstylePreviews,
  submitAiHairstyleSession,
} from '@/services/ai-hairstyle.service';
import { fetchOnlineCatalog } from '@/services/online-booking.service';

type Step = 'upload' | 'generating' | 'compare' | 'details' | 'done';

function AiLookPageInner() {
  const params = useParams<{ tenantSlug: string }>();
  const router = useRouter();
  const tenantSlug = params.tenantSlug;

  const [catalog, setCatalog] = useState<OnlineBookingCatalog | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const [step, setStep] = useState<Step>('upload');
  const [session, setSession] = useState<AiHairstyleSession | null>(null);
  const [selectedIds, setSelectedIds] = useState<string[]>([]);
  const [actionError, setActionError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [notes, setNotes] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setLoadError(null);
    try {
      const data = await fetchOnlineCatalog(tenantSlug);
      setCatalog(data);
      if (!data.ai_hairstyle_landing) {
        router.replace(`/book/${tenantSlug}`);
        return;
      }
      const created = await createAiHairstyleSession(tenantSlug);
      setSession(created);
    } catch (e) {
      setLoadError(e instanceof Error ? e.message : 'Unable to load look studio');
      setCatalog(null);
    } finally {
      setLoading(false);
    }
  }, [tenantSlug, router]);

  useEffect(() => {
    void load();
  }, [load]);

  function skipToBooking() {
    markAiHairstyleLandingSkipped(tenantSlug);
    router.push(`/book/${tenantSlug}`);
  }

  async function onSelfieChosen(file: File | null) {
    if (!file || !session) return;
    setActionError(null);
    setBusy(true);
    setStep('generating');
    try {
      let next = await generateAiHairstylePreviews(
        tenantSlug,
        session.id,
        session.public_token,
        file,
      );
      if (next.status === 'generating') {
        next = await pollAiHairstyleSessionUntilSettled(
          tenantSlug,
          next.id,
          next.public_token,
        );
      }
      if (next.status === 'failed') {
        throw new Error(next.error_message || 'Could not generate looks');
      }
      setSession(next);
      setSelectedIds([]);
      setStep('compare');
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Could not generate looks');
      setStep('upload');
    } finally {
      setBusy(false);
    }
  }

  function togglePreview(id: string) {
    setSelectedIds((prev) =>
      prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
    );
  }

  async function confirmSelection() {
    if (!session || selectedIds.length === 0) return;
    setActionError(null);
    setBusy(true);
    try {
      const next = await selectAiHairstylePreviews(
        tenantSlug,
        session.id,
        session.public_token,
        selectedIds,
      );
      setSession(next);
      setStep('details');
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Could not save selection');
    } finally {
      setBusy(false);
    }
  }

  async function submitLook(e: React.FormEvent) {
    e.preventDefault();
    if (!session) return;
    setActionError(null);
    setBusy(true);
    try {
      const next = await submitAiHairstyleSession(
        tenantSlug,
        session.id,
        session.public_token,
        {
          first_name: firstName.trim() || undefined,
          last_name: lastName.trim() || undefined,
          email: email.trim() || undefined,
          phone: phone.trim(),
          notes: notes.trim() || undefined,
        },
      );
      setSession(next);
      setStep('done');
    } catch (err) {
      setActionError(err instanceof Error ? err.message : 'Could not submit look');
    } finally {
      setBusy(false);
    }
  }

  const salonName =
    catalog?.tenant.branding?.brand_display_name || catalog?.tenant.name || 'Salon';
  const brandPrimary = catalog?.tenant.branding?.primary_color || undefined;
  const brandLogo = resolveMediaUrl(catalog?.tenant.branding?.logo_url);

  if (loading) {
    return (
      <div className="flex min-h-full items-center justify-center bg-[var(--book-wash,#f6f4f1)] px-4 py-16 text-sm text-[var(--book-muted,#71717a)]">
        Loading look studio…
      </div>
    );
  }

  if (loadError || !catalog?.ai_hairstyle_landing || !session) {
    return (
      <div className="mx-auto flex min-h-full max-w-md flex-col justify-center gap-4 px-5 py-16">
        <p className="text-sm text-[var(--book-muted,#71717a)]">
          {loadError ?? 'AI look preview is not available for this salon.'}
        </p>
        <Link
          href={`/book/${tenantSlug}`}
          className="inline-flex min-h-11 items-center justify-center rounded-xl border border-[var(--book-line,#e4e4e7)] bg-white px-4 text-sm font-semibold"
        >
          Back to booking
        </Link>
      </div>
    );
  }

  return (
    <div
      className="min-h-full bg-[var(--book-wash,#f6f4f1)] text-[var(--book-ink,#18181b)]"
      style={
        brandPrimary
          ? ({
              ['--book-moss' as string]: brandPrimary,
              ['--book-moss-deep' as string]: brandPrimary,
            } as CSSProperties)
          : undefined
      }
    >
      <div className="mx-auto w-full max-w-lg px-5 py-8 sm:px-8 sm:py-12">
        <div className="flex items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            {brandLogo ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={brandLogo} alt="" className="h-9 w-9 rounded-md object-cover" />
            ) : (
              <span className="flex h-9 w-9 items-center justify-center rounded-md bg-[var(--book-moss,#3f5d4a)] text-sm font-bold text-white">
                {salonName.slice(0, 1).toUpperCase()}
              </span>
            )}
            <p className="text-sm font-semibold">{salonName}</p>
          </div>
          {step !== 'done' ? (
            <button
              type="button"
              onClick={skipToBooking}
              className="text-sm font-semibold text-[var(--book-muted,#71717a)] underline-offset-2 hover:underline"
            >
              Skip to booking
            </button>
          ) : null}
        </div>

        <h1 className="book-display mt-8 text-3xl font-bold leading-tight tracking-tight sm:text-4xl">
          How would you like to look today?
        </h1>
        <p className="mt-3 text-sm leading-relaxed text-[var(--book-muted,#71717a)]">
          Your selfie is used only to create previews and is not kept.
        </p>

        {actionError ? (
          <p className="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
            {actionError}
          </p>
        ) : null}

        {step === 'upload' ? (
          <section className="mt-8 rounded-2xl border border-[var(--book-line,#e4e4e7)] bg-white p-5">
            <label className="block text-sm font-semibold">Upload a selfie</label>
            <p className="mt-1 text-xs text-[var(--book-muted,#71717a)]">
              Clear face photo, well lit. JPG or PNG up to 5MB.
            </p>
            <input
              type="file"
              accept="image/*"
              capture="user"
              disabled={busy}
              className="mt-4 block w-full text-sm"
              onChange={(e) => {
                const file = e.target.files?.[0] ?? null;
                void onSelfieChosen(file);
              }}
            />
          </section>
        ) : null}

        {step === 'generating' ? (
          <section className="mt-8 rounded-2xl border border-[var(--book-line,#e4e4e7)] bg-white p-8 text-center">
            <p className="text-sm font-semibold">Creating your looks…</p>
            <p className="mt-2 text-xs text-[var(--book-muted,#71717a)]">
              This usually takes a few seconds.
            </p>
          </section>
        ) : null}

        {step === 'compare' ? (
          <section className="mt-8">
            <p className="text-sm font-semibold">Compare looks — tap to select</p>
            <ul className="mt-4 grid grid-cols-2 gap-3">
              {session.previews.map((preview) => {
                const active = selectedIds.includes(preview.id);
                return (
                  <li key={preview.id}>
                    <button
                      type="button"
                      onClick={() => togglePreview(preview.id)}
                      className={`w-full overflow-hidden rounded-xl border text-left transition ${
                        active
                          ? 'border-[var(--book-moss,#3f5d4a)] ring-2 ring-[var(--book-moss,#3f5d4a)]'
                          : 'border-[var(--book-line,#e4e4e7)]'
                      }`}
                    >
                      {/* eslint-disable-next-line @next/next/no-img-element */}
                      <img
                        src={preview.composite_image_url ?? ''}
                        alt={preview.style_label ?? 'Look preview'}
                        className="aspect-[4/5] w-full object-cover bg-stone-200"
                      />
                      <span className="block px-2 py-2 text-xs font-semibold">
                        {preview.style_label ?? 'Look'}
                      </span>
                    </button>
                  </li>
                );
              })}
            </ul>
            <button
              type="button"
              disabled={busy || selectedIds.length === 0}
              onClick={() => void confirmSelection()}
              className="mt-5 inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-[var(--book-moss,#3f5d4a)] px-5 text-base font-semibold text-white disabled:opacity-50"
            >
              Continue with selected look{selectedIds.length > 1 ? 's' : ''}
            </button>
            <button
              type="button"
              disabled={busy}
              onClick={() => setStep('upload')}
              className="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-[var(--book-line,#e4e4e7)] bg-white px-4 text-sm font-semibold"
            >
              Try another selfie
            </button>
          </section>
        ) : null}

        {step === 'details' ? (
          <form onSubmit={(e) => void submitLook(e)} className="mt-8 space-y-3 rounded-2xl border border-[var(--book-line,#e4e4e7)] bg-white p-5">
            <p className="text-sm font-semibold">Send your look to the salon</p>
            <input
              required
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              placeholder="Mobile / WhatsApp *"
              inputMode="tel"
              autoComplete="tel"
              className="w-full rounded-lg border border-[var(--book-line,#e4e4e7)] px-3 py-2.5 text-sm outline-none focus:border-[var(--book-moss,#3f5d4a)]"
            />
            <input
              value={firstName}
              onChange={(e) => setFirstName(e.target.value)}
              placeholder="First name (optional)"
              className="w-full rounded-lg border border-[var(--book-line,#e4e4e7)] px-3 py-2.5 text-sm outline-none focus:border-[var(--book-moss,#3f5d4a)]"
            />
            <input
              value={lastName}
              onChange={(e) => setLastName(e.target.value)}
              placeholder="Last name (optional)"
              className="w-full rounded-lg border border-[var(--book-line,#e4e4e7)] px-3 py-2.5 text-sm outline-none focus:border-[var(--book-moss,#3f5d4a)]"
            />
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="Email (optional)"
              className="w-full rounded-lg border border-[var(--book-line,#e4e4e7)] px-3 py-2.5 text-sm outline-none focus:border-[var(--book-moss,#3f5d4a)]"
            />
            <textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder="Notes for your stylist (optional)"
              rows={3}
              className="w-full rounded-lg border border-[var(--book-line,#e4e4e7)] px-3 py-2.5 text-sm outline-none focus:border-[var(--book-moss,#3f5d4a)]"
            />
            <button
              type="submit"
              disabled={busy || !phone.trim()}
              className="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-[var(--book-moss,#3f5d4a)] px-5 text-base font-semibold text-white disabled:opacity-50"
            >
              {busy ? 'Sending…' : 'Submit look'}
            </button>
          </form>
        ) : null}

        {step === 'done' ? (
          <section className="mt-8 rounded-2xl border border-emerald-200 bg-emerald-50 p-6 text-center">
            <p className="text-lg font-semibold text-emerald-950">Look sent</p>
            <p className="mt-2 text-sm text-emerald-900/80">
              {salonName} will review your approved look. You can book your visit now.
            </p>
            <button
              type="button"
              onClick={skipToBooking}
              className="mt-6 inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-[var(--book-moss,#3f5d4a)] px-5 text-base font-semibold text-white"
            >
              Continue to booking
            </button>
          </section>
        ) : null}
      </div>
    </div>
  );
}

export default function AiLookPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-full items-center justify-center px-4 py-16 text-sm text-[var(--book-muted,#71717a)]">
          Loading…
        </div>
      }
    >
      <AiLookPageInner />
    </Suspense>
  );
}
