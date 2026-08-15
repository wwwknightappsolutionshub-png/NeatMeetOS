'use client';

import Image from 'next/image';
import Link from 'next/link';
import { useParams, useSearchParams } from 'next/navigation';
import { Suspense, useCallback, useEffect, useState, type CSSProperties } from 'react';
import type { Appointment, OnlineBookingCatalog } from '@/lib/booking-types';
import { buildGoogleCalendarUrl, downloadIcsFile } from '@/lib/booking-calendar';
import { resolveMediaUrl } from '@/lib/media-url';
import {
  cancelManagedAppointment,
  fetchManagedAppointment,
  fetchOnlineCatalog,
} from '@/services/online-booking.service';

function formatSlotTime(iso: string): string {
  return new Date(iso).toLocaleString(undefined, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function primaryBtnClass(disabled?: boolean): string {
  return [
    'inline-flex items-center justify-center rounded-md px-5 py-2.5 text-sm font-semibold tracking-wide transition',
    'bg-[var(--book-moss)] text-white hover:bg-[var(--book-moss-deep)]',
    disabled ? 'cursor-not-allowed opacity-50' : '',
  ].join(' ');
}

function secondaryBtnClass(): string {
  return 'inline-flex items-center justify-center rounded-md border border-[var(--book-line)] bg-white px-5 py-2.5 text-sm font-semibold text-[var(--book-ink)] transition hover:bg-[var(--book-wash)]';
}

function ManageBookingInner() {
  const params = useParams<{ tenantSlug: string }>();
  const search = useSearchParams();
  const tenantSlug = params.tenantSlug;
  const ref = search.get('ref') ?? '';
  const token = search.get('token') ?? '';

  const [catalog, setCatalog] = useState<OnlineBookingCatalog | null>(null);
  const [appointment, setAppointment] = useState<Appointment | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [cancelling, setCancelling] = useState(false);
  const [cancelRequested, setCancelRequested] = useState(false);

  const branding = catalog?.tenant.branding;
  const salonName =
    (branding?.brand_display_name as string | undefined) ||
    catalog?.tenant.name ||
    'Salon';

  const brandStyle = {
    '--book-moss': (branding?.primary_color as string | undefined) || '#3d5a45',
    '--book-moss-deep': (branding?.primary_color as string | undefined) || '#2f4636',
  } as CSSProperties;

  const load = useCallback(async () => {
    if (!ref || !token) {
      setError('This manage link is missing a booking reference or token.');
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const [cat, appt] = await Promise.all([
        fetchOnlineCatalog(tenantSlug),
        fetchManagedAppointment(tenantSlug, ref, token),
      ]);
      setCatalog(cat);
      setAppointment(appt);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not load this booking.');
    } finally {
      setLoading(false);
    }
  }, [ref, token, tenantSlug]);

  useEffect(() => {
    void load();
  }, [load]);

  async function handleCancel() {
    if (!appointment?.booking_reference || !token) return;
    if (
      !window.confirm(
        'Submit a cancellation request? The salon will confirm it (or it may auto-confirm shortly).',
      )
    ) {
      return;
    }
    setCancelling(true);
    setError(null);
    try {
      await cancelManagedAppointment(
        tenantSlug,
        appointment.booking_reference,
        token,
      );
      setCancelRequested(true);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not cancel this booking.');
    } finally {
      setCancelling(false);
    }
  }

  const canCancel =
    appointment &&
    !cancelRequested &&
    !['cancelled', 'completed', 'no_show'].includes(appointment.status) &&
    new Date(appointment.starts_at).getTime() > Date.now();

  const heroImageSrc =
    resolveMediaUrl(branding?.hero_image_url) || '/book/hero.jpg';

  return (
    <div className="book-shell min-h-screen" style={brandStyle}>
      <main className="mx-auto max-w-lg px-4 py-10 sm:px-6">
        <header className="mb-8 text-center">
          <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--book-muted)]">
            Manage booking
          </p>
          <h1 className="book-display mt-2 text-3xl font-bold">{salonName}</h1>
        </header>

        {loading ? (
          <p className="text-center text-sm text-[var(--book-muted)]">Loading…</p>
        ) : error && !appointment ? (
          <div className="rounded-2xl border border-[var(--book-line)] bg-white p-8 text-center shadow-[var(--book-shadow)]">
            <p className="text-sm text-red-700">{error}</p>
            <Link href={`/book/${tenantSlug}`} className={`${secondaryBtnClass()} mt-6`}>
              Book a new appointment
            </Link>
          </div>
        ) : appointment ? (
          <section className="overflow-hidden rounded-2xl border border-[var(--book-line)] bg-white shadow-[var(--book-shadow)]">
            <div className="relative h-36 w-full">
              <Image
                src={heroImageSrc}
                alt=""
                fill
                sizes="100vw"
                className="object-cover object-center"
                unoptimized={heroImageSrc.startsWith('http')}
              />
              <div className="absolute inset-0 bg-[rgba(18,24,22,0.45)]" />
              <p className="absolute bottom-4 left-5 text-xs font-semibold uppercase tracking-[0.2em] text-white/85">
                {appointment.status === 'cancelled' ? 'Cancelled' : 'Your booking'}
              </p>
            </div>
            <div className="p-8">
              <p className="text-sm text-[var(--book-muted)]">
                Reference{' '}
                <span className="font-semibold text-[var(--book-ink)]">
                  {appointment.booking_reference}
                </span>
              </p>
              <dl className="mt-6 space-y-3 text-sm">
                <div className="flex justify-between gap-4 border-b border-[var(--book-line)] pb-3">
                  <dt className="text-[var(--book-muted)]">When</dt>
                  <dd className="font-semibold">{formatSlotTime(appointment.starts_at)}</dd>
                </div>
                <div className="flex justify-between gap-4 border-b border-[var(--book-line)] pb-3">
                  <dt className="text-[var(--book-muted)]">With</dt>
                  <dd className="font-semibold">
                    {appointment.team_member?.display_name ?? '—'}
                  </dd>
                </div>
                <div className="flex justify-between gap-4 border-b border-[var(--book-line)] pb-3">
                  <dt className="text-[var(--book-muted)]">Where</dt>
                  <dd className="font-semibold">{appointment.location?.name ?? '—'}</dd>
                </div>
                <div className="flex justify-between gap-4 border-b border-[var(--book-line)] pb-3">
                  <dt className="text-[var(--book-muted)]">Status</dt>
                  <dd className="font-semibold capitalize">{appointment.status}</dd>
                </div>
              </dl>

              {error ? <p className="mt-4 text-sm text-red-700">{error}</p> : null}

              {appointment.status !== 'cancelled' ? (
                <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                  <a
                    className={secondaryBtnClass()}
                    href={buildGoogleCalendarUrl({
                      title: `${salonName} appointment`,
                      startsAt: appointment.starts_at,
                      endsAt: appointment.ends_at,
                      location: appointment.location?.name,
                      details: appointment.booking_reference
                        ? `Booking ref ${appointment.booking_reference}`
                        : undefined,
                    })}
                    target="_blank"
                    rel="noreferrer"
                  >
                    Google Calendar
                  </a>
                  <button
                    type="button"
                    className={secondaryBtnClass()}
                    onClick={() =>
                      downloadIcsFile({
                        title: `${salonName} appointment`,
                        startsAt: appointment.starts_at,
                        endsAt: appointment.ends_at,
                        location: appointment.location?.name,
                        description: appointment.booking_reference
                          ? `Booking ref ${appointment.booking_reference}`
                          : undefined,
                        filename: `${appointment.booking_reference ?? 'appointment'}.ics`,
                      })
                    }
                  >
                    Download .ics
                  </button>
                </div>
              ) : null}

              {canCancel ? (
                <button
                  type="button"
                  className={`${primaryBtnClass(cancelling)} mt-6 w-full bg-red-800 hover:bg-red-900`}
                  disabled={cancelling}
                  onClick={() => void handleCancel()}
                >
                  {cancelling ? 'Submitting…' : 'Request cancellation'}
                </button>
              ) : null}

              {cancelRequested ? (
                <p className="mt-6 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                  Cancellation request sent. The salon will confirm shortly (or it may auto-confirm).
                </p>
              ) : null}

              <Link href={`/book/${tenantSlug}`} className={`${secondaryBtnClass()} mt-6 w-full`}>
                Book another visit
              </Link>
            </div>
          </section>
        ) : null}
      </main>
    </div>
  );
}

export default function ManageBookingPage() {
  return (
    <Suspense
      fallback={
        <div className="book-shell flex min-h-screen items-center justify-center text-sm text-[var(--book-muted)]">
          Loading…
        </div>
      }
    >
      <ManageBookingInner />
    </Suspense>
  );
}
