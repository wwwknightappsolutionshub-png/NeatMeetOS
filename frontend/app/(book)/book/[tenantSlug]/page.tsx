'use client';

import dynamic from 'next/dynamic';
import Image from 'next/image';
import { Suspense, useCallback, useEffect, useMemo, useState, type CSSProperties } from 'react';
import { useParams, useRouter, useSearchParams } from 'next/navigation';
import { NeatMeetLogo } from '@/components/brand/NeatMeetLogo';
import type {
  Appointment,
  BookableService,
  OnlineBookingCatalog,
  OnlineBookingSlot,
} from '@/lib/booking-types';
import {
  createOnlineAppointment,
  fetchOnlineCatalog,
  fetchOnlineSlots,
} from '@/services/online-booking.service';
import { buildGoogleCalendarUrl, downloadIcsFile } from '@/lib/booking-calendar';
import { resolveMediaUrl } from '@/lib/media-url';
import {
  hasSkippedAiHairstyleLanding,
  markAiHairstyleLandingSkipped,
} from '@/lib/ai-hairstyle-landing';
import Link from 'next/link';
import { AiHairstyleLandingGate } from '@/components/booking/AiHairstyleLandingGate';
import { SocialFooterIcons } from '@/components/public/SocialFooterIcons';
import { loadMemberSession } from '@/services/member-portal.service';

const VoiceBookingConcierge = dynamic(
  () =>
    import('@/components/booking/VoiceBookingConcierge').then((m) => m.VoiceBookingConcierge),
  { ssr: false },
);
const BookingReviewsSection = dynamic(
  () =>
    import('@/components/booking/BookingReviewsSection').then((m) => m.BookingReviewsSection),
  { ssr: false },
);
const BookingShopCarousel = dynamic(
  () =>
    import('@/components/booking/BookingShopCarousel').then((m) => m.BookingShopCarousel),
  { ssr: false },
);
const PublicGallerySection = dynamic(
  () =>
    import('@/components/public/PublicGallerySection').then((m) => m.PublicGallerySection),
  { ssr: false },
);
const PublicLookbookSection = dynamic(
  () =>
    import('@/components/public/PublicLookbookSection').then((m) => m.PublicLookbookSection),
  { ssr: false },
);

type Step = 'service' | 'when' | 'details' | 'done';

const STEPS: { key: Step; label: string }[] = [
  { key: 'service', label: 'Service' },
  { key: 'when', label: 'Date & time' },
  { key: 'details', label: 'Your details' },
  { key: 'done', label: 'Confirmed' },
];

function formatMoney(cents: number | null): string {
  if (cents === null) return 'Price on request';
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: 'GBP',
  }).format(cents / 100);
}

function formatLocationAddress(
  address:
    | {
        line1?: string;
        line2?: string;
        city?: string;
        county?: string;
        postcode?: string;
        country?: string;
      }
    | null
    | undefined,
): string | null {
  if (!address) return null;
  const parts = [
    address.line1,
    address.line2,
    address.city,
    address.county,
    address.postcode,
  ]
    .map((p) => (typeof p === 'string' ? p.trim() : ''))
    .filter(Boolean);
  return parts.length > 0 ? parts.join(', ') : null;
}

function storeStatusMeta(status: string | null | undefined): {
  label: string;
  beaconClass: string;
} {
  switch (status) {
    case 'opening_soon':
      return {
        label: "We're opening soon",
        beaconClass: 'bg-sky-500 shadow-[0_0_0_3px_rgba(14,165,233,0.28)]',
      };
    case 'closing':
      return {
        label: "We're Closing",
        beaconClass: 'bg-amber-500 shadow-[0_0_0_3px_rgba(245,158,11,0.25)]',
      };
    case 'closed':
      return {
        label: 'Close for the day',
        beaconClass: 'bg-zinc-400',
      };
    case 'open':
    default:
      return {
        label: "We're open",
        beaconClass: 'bg-emerald-500 shadow-[0_0_0_3px_rgba(16,185,129,0.28)]',
      };
  }
}

function minutesFromMidnight(hhmm: string | null | undefined): number | null {
  if (!hhmm) return null;
  const match = /^(\d{1,2}):(\d{2})/.exec(hhmm.trim());
  if (!match) return null;
  return Number(match[1]) * 60 + Number(match[2]);
}

function resolveLiveStoreStatus(opts: {
  brandedStatus: string | null | undefined;
  openingHours:
    | Array<{
        day_of_week: number;
        start_time: string | null;
        end_time: string | null;
        is_closed?: boolean;
      }>
    | null
    | undefined;
  timezone: string | null | undefined;
  now?: Date;
}): 'open' | 'opening_soon' | 'closing' | 'closed' {
  const manual = opts.brandedStatus ?? 'auto';
  if (manual !== 'auto' && ['open', 'opening_soon', 'closing', 'closed'].includes(manual)) {
    return manual as 'open' | 'opening_soon' | 'closing' | 'closed';
  }

  const hours = opts.openingHours;
  if (!hours || hours.length === 0) {
    return 'open';
  }

  const tz = opts.timezone || 'Europe/London';
  const now = opts.now ?? new Date();
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: tz,
    weekday: 'short',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).formatToParts(now);

  const weekday = parts.find((p) => p.type === 'weekday')?.value ?? '';
  const hour = Number(parts.find((p) => p.type === 'hour')?.value ?? '0');
  const minute = Number(parts.find((p) => p.type === 'minute')?.value ?? '0');
  const nowMins = hour * 60 + minute;

  const weekdayToIso: Record<string, number> = {
    Mon: 1,
    Tue: 2,
    Wed: 3,
    Thu: 4,
    Fri: 5,
    Sat: 6,
    Sun: 7,
  };
  const dayIso = weekdayToIso[weekday] ?? (now.getDay() || 7);

  const today = hours.find((h) => h.day_of_week === dayIso);
  if (!today || today.is_closed) {
    return 'closed';
  }

  const openMins = minutesFromMidnight(today.start_time);
  const closeMins = minutesFromMidnight(today.end_time);
  if (openMins === null || closeMins === null) {
    return 'closed';
  }

  if (nowMins < openMins - 30 || nowMins >= closeMins) {
    return 'closed';
  }
  if (nowMins >= openMins - 30 && nowMins < openMins) {
    return 'opening_soon';
  }
  if (nowMins >= closeMins - 30 && nowMins < closeMins) {
    return 'closing';
  }
  return 'open';
}

type PricingTier = 'regular' | 'membership' | 'loyalty';

function ServicePriceTiers({
  service,
  selected,
  onSelect,
}: {
  service: BookableService;
  selected?: PricingTier;
  onSelect?: (tier: PricingTier) => void;
}) {
  const tiers: { key: PricingTier; label: string; cents: number | null }[] = [
    { key: 'regular', label: 'Regular', cents: service.base_price_cents },
  ];
  if (service.membership_price_cents != null) {
    tiers.push({ key: 'membership', label: 'Membership', cents: service.membership_price_cents });
  }
  if (service.loyalty_price_cents != null) {
    tiers.push({ key: 'loyalty', label: 'Loyalty', cents: service.loyalty_price_cents });
  }

  return (
    <ul className="mt-3 space-y-1 text-sm">
      {tiers.map((tier) => {
        const isSelected = selected === tier.key;
        const interactive = Boolean(onSelect);
        const content = (
          <>
            <span className="text-[var(--book-muted)]">- {tier.label}</span>
            <span className="font-semibold tabular-nums text-[var(--book-ink)]">
              {formatMoney(tier.cents)}
            </span>
          </>
        );
        if (!interactive) {
          return (
            <li key={tier.key} className="flex items-baseline justify-between gap-2">
              {content}
            </li>
          );
        }
        return (
          <li key={tier.key}>
            <button
              type="button"
              onClick={() => onSelect?.(tier.key)}
              className={[
                'flex w-full items-baseline justify-between gap-2 rounded-md px-2 py-1.5 text-left transition',
                isSelected
                  ? 'bg-[var(--book-moss-soft)] ring-1 ring-[var(--book-moss)]'
                  : 'hover:bg-[var(--book-wash)]',
              ].join(' ')}
            >
              {content}
            </button>
          </li>
        );
      })}
    </ul>
  );
}

function ReferFriendModal({
  tenantSlug,
  onClose,
}: {
  tenantSlug: string;
  onClose: () => void;
}) {
  const joinHref = `/join/${tenantSlug}`;
  const memberHref = `/member/${tenantSlug}`;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog">
      <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
        <h3 className="book-display text-xl font-bold text-[var(--book-ink)]">
          Whoops, you are about to miss a reward
        </h3>
        <p className="mt-2 text-sm leading-relaxed text-[var(--book-muted)]">
          Why not sign-up, install and then refer, this will count towards your loyalty and will be
          rewarded
        </p>
        <div className="mt-5 flex flex-col gap-2">
          <Link
            href={joinHref}
            className="inline-flex items-center justify-center rounded-md bg-[var(--book-moss)] px-4 py-2.5 text-sm font-semibold text-white"
          >
            Sign up
          </Link>
          <Link
            href={memberHref}
            className="inline-flex items-center justify-center rounded-md border border-[var(--book-line)] px-4 py-2.5 text-sm font-semibold text-[var(--book-ink)]"
          >
            Already a member? Log in
          </Link>
          <button type="button" className="mt-1 text-sm text-[var(--book-muted)]" onClick={onClose}>
            Close
          </button>
        </div>
      </div>
    </div>
  );
}

function PricingGateModal({
  tier,
  tenantSlug,
  onClose,
}: {
  tier: PricingTier;
  tenantSlug: string;
  onClose: () => void;
}) {
  const next = typeof window !== 'undefined' ? window.location.pathname + window.location.search : `/book/${tenantSlug}`;
  const loginHref = `/member/${tenantSlug}?next=${encodeURIComponent(next)}&tier=${tier}`;
  const joinHref = `/join/${tenantSlug}`;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog">
      <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
        <h3 className="book-display text-xl font-bold text-[var(--book-ink)]">
          {tier === 'membership' ? 'Membership pricing' : 'Loyalty pricing'}
        </h3>
        <p className="mt-2 text-sm text-[var(--book-muted)]">
          Log in to the membership app to use this rate. If you have not joined yet, sign up on the
          CRM form first. Or{' '}
          <Link href={`/book/${tenantSlug}/memberships`} className="font-semibold text-[var(--book-moss)] underline-offset-2 hover:underline">
            compare plans, packages, and loyalty
          </Link>
          .
        </p>
        <div className="mt-5 flex flex-col gap-2">
          <Link
            href={loginHref}
            className="inline-flex items-center justify-center rounded-md bg-[var(--book-moss)] px-4 py-2.5 text-sm font-semibold text-white"
          >
            Log in to membership app
          </Link>
          <Link
            href={joinHref}
            className="inline-flex items-center justify-center rounded-md border border-[var(--book-line)] px-4 py-2.5 text-sm font-semibold text-[var(--book-ink)]"
          >
            Not registered? CRM signup
          </Link>
          <button type="button" className="mt-1 text-sm text-[var(--book-muted)]" onClick={onClose}>
            Stay on Regular pricing
          </button>
        </div>
      </div>
    </div>
  );
}

function formatSlotTime(iso: string): string {
  return new Date(iso).toLocaleString(undefined, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function tomorrowDateInput(): string {
  const d = new Date();
  d.setDate(d.getDate() + 1);
  return d.toISOString().slice(0, 10);
}

function addDaysDateInput(base: string, days: number): string {
  const d = new Date(`${base}T12:00:00`);
  d.setDate(d.getDate() + days);
  return d.toISOString().slice(0, 10);
}

function categoryLabel(category: string | null): string {
  if (!category) return 'Service';
  return category.charAt(0).toUpperCase() + category.slice(1);
}

function serviceImageSrc(category: string | null): string {
  if (category?.toLowerCase() === 'colour' || category?.toLowerCase() === 'color') {
    return '/book/service-colour.jpg';
  }
  return '/book/service-hair.jpg';
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

function fieldClass(): string {
  return 'w-full rounded-md border border-[var(--book-line)] bg-white px-3 py-2.5 text-sm text-[var(--book-ink)] outline-none transition focus:border-[var(--book-moss)] focus:ring-2 focus:ring-[var(--book-moss-soft)]';
}

function panelClass(): string {
  return 'rounded-2xl border border-[var(--book-line)] bg-[var(--book-surface)] p-5 shadow-[var(--book-shadow)] sm:p-6';
}

export default function OnlineBookingPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-screen items-center justify-center text-sm text-zinc-500">
          Loading booking…
        </div>
      }
    >
      <OnlineBookingPageInner />
    </Suspense>
  );
}

function OnlineBookingPageInner() {
  const params = useParams<{ tenantSlug: string }>();
  const searchParams = useSearchParams();
  const router = useRouter();
  const tenantSlug = params.tenantSlug;
  const locationFromQuery = searchParams.get('location');

  const [step, setStep] = useState<Step>('service');
  const [catalog, setCatalog] = useState<OnlineBookingCatalog | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [aiLandingSkipped, setAiLandingSkipped] = useState(false);

  const [serviceId, setServiceId] = useState('');
  const [locationId, setLocationId] = useState('');
  const [providerId, setProviderId] = useState('');
  const [date, setDate] = useState(tomorrowDateInput);
  const [slots, setSlots] = useState<OnlineBookingSlot[]>([]);
  const [slotsLoading, setSlotsLoading] = useState(false);
  const [slotsError, setSlotsError] = useState<string | null>(null);
  const [selectedSlot, setSelectedSlot] = useState<OnlineBookingSlot | null>(null);

  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [whatsappOptIn, setWhatsappOptIn] = useState(false);
  const [notes, setNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [appointment, setAppointment] = useState<Appointment | null>(null);
  const [pricingTier, setPricingTier] = useState<PricingTier>('regular');
  const [pricingGate, setPricingGate] = useState<PricingTier | null>(null);
  const [referGateOpen, setReferGateOpen] = useState(false);
  const [referNotice, setReferNotice] = useState<string | null>(null);
  const [liveStoreStatus, setLiveStoreStatus] = useState<
    'open' | 'opening_soon' | 'closing' | 'closed'
  >('open');

  useEffect(() => {
    setAiLandingSkipped(hasSkippedAiHairstyleLanding(tenantSlug));
  }, [tenantSlug]);

  const loadCatalog = useCallback(async () => {
    setLoading(true);
    setLoadError(null);
    try {
      const data = await fetchOnlineCatalog(tenantSlug);
      setCatalog(data);
      const matched = locationFromQuery
        ? data.locations.find((l) => l.id === locationFromQuery)
        : null;
      if (matched) {
        setLocationId(matched.id);
      } else if (data.locations[0]) {
        setLocationId((prev) => prev || data.locations[0].id);
      }
    } catch (e) {
      setLoadError(e instanceof Error ? e.message : 'Unable to load booking catalog');
      setCatalog(null);
    } finally {
      setLoading(false);
    }
  }, [tenantSlug, locationFromQuery]);

  useEffect(() => {
    void loadCatalog();
  }, [loadCatalog]);

  const showAiLandingGate =
    !loading &&
    !loadError &&
    Boolean(catalog?.ai_hairstyle_landing) &&
    !aiLandingSkipped;

  const selectedService: BookableService | undefined = useMemo(
    () => catalog?.services.find((s) => s.id === serviceId),
    [catalog, serviceId],
  );

  const loadSlots = useCallback(async () => {
    if (!serviceId || !locationId || !date) return;
    setSlotsLoading(true);
    setSlotsError(null);
    setSelectedSlot(null);
    try {
      let result = await fetchOnlineSlots(tenantSlug, {
        booking_service_id: serviceId,
        location_id: locationId,
        date,
        team_member_id: providerId || undefined,
      });

      // If default/picked day has no openings, walk forward up to 14 days.
      if (result.length === 0) {
        for (let offset = 1; offset <= 14; offset += 1) {
          const candidate = addDaysDateInput(date, offset);
          const next = await fetchOnlineSlots(tenantSlug, {
            booking_service_id: serviceId,
            location_id: locationId,
            date: candidate,
            team_member_id: providerId || undefined,
          });
          if (next.length > 0) {
            setDate(candidate);
            result = next;
            break;
          }
        }
      }

      setSlots(result);
    } catch (e) {
      setSlots([]);
      setSlotsError(e instanceof Error ? e.message : 'Unable to load slots');
    } finally {
      setSlotsLoading(false);
    }
  }, [tenantSlug, serviceId, locationId, date, providerId]);

  useEffect(() => {
    if (step === 'when') {
      void loadSlots();
    }
  }, [step, loadSlots]);

  async function handleBook() {
    if (!selectedSlot || !selectedService) return;
    setSubmitting(true);
    setSubmitError(null);
    try {
      const member = loadMemberSession(tenantSlug);
      const created = await createOnlineAppointment(tenantSlug, {
        booking_service_id: selectedService.id,
        location_id: selectedSlot.location_id,
        team_member_id: selectedSlot.team_member_id,
        workspace_id: selectedSlot.workspace_id,
        starts_at: selectedSlot.starts_at,
        first_name: firstName.trim(),
        last_name: lastName.trim(),
        email: email.trim(),
        phone: phone.trim() || undefined,
        whatsapp_opt_in: Boolean(whatsappOptIn && phone.trim()),
        client_notes: notes.trim() || undefined,
        pricing_tier: pricingTier,
        member_token: pricingTier !== 'regular' ? member?.token : undefined,
      });
      setAppointment(created);
      setStep('done');
    } catch (e) {
      setSubmitError(e instanceof Error ? e.message : 'Booking failed');
    } finally {
      setSubmitting(false);
    }
  }

  function selectPricingTier(tier: PricingTier) {
    if (tier === 'regular') {
      setPricingTier('regular');
      setPricingGate(null);
      return;
    }
    const member = loadMemberSession(tenantSlug);
    if (!member?.token) {
      setPricingGate(tier);
      return;
    }
    if (tier === 'membership' && !member.benefits.has_membership) {
      setSubmitError(null);
      setPricingGate(tier);
      return;
    }
    if (tier === 'loyalty' && !member.benefits.loyalty_eligible) {
      setPricingGate(tier);
      return;
    }
    setPricingTier(tier);
    setPricingGate(null);
  }

  function resetFlow() {
    setStep('service');
    setServiceId('');
    setProviderId('');
    setSelectedSlot(null);
    setSlots([]);
    setAppointment(null);
    setSubmitError(null);
    setPricingTier('regular');
    setPricingGate(null);
    setFirstName('');
    setLastName('');
    setEmail('');
    setPhone('');
    setWhatsappOptIn(false);
    setNotes('');
  }

  function handleReferFriend() {
    const member = loadMemberSession(tenantSlug);
    if (!member?.benefits?.has_membership) {
      setReferGateOpen(true);
      return;
    }
    const url = `${window.location.origin}/join/${tenantSlug}?ref=${encodeURIComponent(member.client.id)}`;
    void navigator.clipboard.writeText(url).then(
      () => setReferNotice('Referral link copied — share it with a friend.'),
      () => setReferNotice(url),
    );
  }

  const salonName =
    catalog?.tenant.branding?.brand_display_name ||
    catalog?.tenant.name ||
    'Salon';
  const brandPrimary = catalog?.tenant.branding?.primary_color || undefined;
  const brandLogo = resolveMediaUrl(catalog?.tenant.branding?.logo_url);
  const activeLocation =
    catalog?.locations.find((l) => l.id === locationId) ?? catalog?.locations[0] ?? null;
  const locationName = activeLocation?.name ?? null;
  const locationPhone =
    activeLocation?.contact_phone ||
    catalog?.tenant.branding?.support_phone ||
    null;
  const locationAddress = formatLocationAddress(activeLocation?.address ?? null);
  const storeStatusSetting = catalog?.tenant.branding?.store_status ?? 'auto';

  useEffect(() => {
    const tick = () => {
      setLiveStoreStatus(
        resolveLiveStoreStatus({
          brandedStatus: storeStatusSetting,
          openingHours: activeLocation?.opening_hours,
          timezone: activeLocation?.timezone ?? null,
        }),
      );
    };
    tick();
    const id = window.setInterval(tick, 30_000);
    return () => window.clearInterval(id);
  }, [storeStatusSetting, activeLocation?.opening_hours, activeLocation?.timezone]);

  const heroEmblemMode = catalog?.tenant.branding?.hero_emblem_mode ?? 'none';
  const heroImageSrc =
    resolveMediaUrl(catalog?.tenant.branding?.hero_image_url) || '/book/hero.jpg';
  const heroImageIsDefault = heroImageSrc === '/book/hero.jpg';
  const heroEmblemUrl =
    heroEmblemMode === 'logo'
      ? resolveMediaUrl(brandLogo)
      : heroEmblemMode === 'custom'
        ? resolveMediaUrl(catalog?.tenant.branding?.hero_emblem_url)
        : null;
  const stepIndex = STEPS.findIndex((s) => s.key === step);
  const progressPct = ((stepIndex + 1) / STEPS.length) * 100;
  const statusMeta = storeStatusMeta(liveStoreStatus);

  if (showAiLandingGate) {
    return (
      <AiHairstyleLandingGate
        salonName={salonName}
        brandPrimary={brandPrimary}
        brandLogo={brandLogo}
        onStart={() => {
          router.push(`/book/${tenantSlug}/ai-look`);
        }}
        onSkip={() => {
          markAiHairstyleLandingSkipped(tenantSlug);
          setAiLandingSkipped(true);
        }}
      />
    );
  }

  return (
    <div
      className="min-h-full text-[var(--book-ink)]"
      style={
        brandPrimary
          ? ({
              ['--book-moss' as string]: brandPrimary,
              ['--book-moss-deep' as string]: brandPrimary,
            } as CSSProperties)
          : undefined
      }
    >
      {pricingGate ? (
        <PricingGateModal
          tier={pricingGate}
          tenantSlug={tenantSlug}
          onClose={() => {
            setPricingGate(null);
            setPricingTier('regular');
          }}
        />
      ) : null}
      {referGateOpen ? (
        <ReferFriendModal tenantSlug={tenantSlug} onClose={() => setReferGateOpen(false)} />
      ) : null}

      <div className="border-b border-[var(--book-line)] bg-white/90 backdrop-blur">
        <div className="mx-auto flex max-w-5xl flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
          <div className="flex items-center gap-3">
            {brandLogo ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img
                src={brandLogo}
                alt=""
                className="h-9 w-9 rounded-md object-cover"
              />
            ) : (
              <NeatMeetLogo size={36} variant="color" />
            )}
            <div>
              <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[var(--book-muted)]">
                NeatMeet OS
              </p>
              <p className="text-sm font-semibold leading-tight">Online booking</p>
            </div>
          </div>
          <div className="flex min-w-0 flex-1 flex-col gap-2 sm:items-end">
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 sm:justify-end">
              <span className="inline-flex items-center gap-1.5 text-sm font-medium text-[var(--book-ink)]">
                <span
                  className={`h-2 w-2 shrink-0 rounded-full ${statusMeta.beaconClass}`}
                  aria-hidden
                />
                {statusMeta.label}
              </span>
              {locationName ? (
                <span className="text-sm text-[var(--book-muted)]">{locationName}</span>
              ) : null}
              <button
                type="button"
                onClick={handleReferFriend}
                className="rounded-md border border-[var(--book-line)] bg-white px-3 py-1.5 text-sm font-semibold text-[var(--book-ink)] hover:bg-[var(--book-wash)]"
              >
                Refer a friend
              </button>
            </div>
            {(locationAddress || locationPhone) && (
              <p className="text-xs leading-relaxed text-[var(--book-muted)] sm:text-right">
                {locationAddress}
                {locationAddress && locationPhone ? ' · ' : null}
                {locationPhone ? (
                  <a
                    href={`tel:${locationPhone.replace(/\s+/g, '')}`}
                    className="font-medium text-[var(--book-ink)] underline-offset-2 hover:underline"
                  >
                    {locationPhone}
                  </a>
                ) : null}
              </p>
            )}
          </div>
        </div>
      </div>

      <header className="relative isolate min-h-[42vh] overflow-hidden sm:min-h-[48vh]">
        <div className="book-hero-media absolute inset-0">
          <Image
            src={heroImageSrc}
            alt=""
            fill
            priority
            sizes="100vw"
            unoptimized={!heroImageIsDefault}
            className="object-cover object-center"
          />
          <div className="absolute inset-0 bg-gradient-to-r from-[rgba(18,24,22,0.78)] via-[rgba(18,24,22,0.55)] to-[rgba(18,24,22,0.25)]" />
          <div className="absolute inset-0 bg-gradient-to-t from-[rgba(18,24,22,0.45)] to-transparent" />
        </div>
        <div className="relative mx-auto flex max-w-5xl flex-col justify-end gap-8 px-4 pb-12 pt-20 sm:flex-row sm:items-end sm:justify-between sm:px-6 sm:pb-14 sm:pt-28">
          <div className="min-w-0 flex-1">
            <p className="book-animate-in text-xs font-semibold uppercase tracking-[0.24em] text-white/70">
              Book your visit
            </p>
            <h1 className="book-animate-in book-animate-delay-1 book-display mt-3 max-w-2xl text-4xl font-bold leading-[1.05] text-white sm:text-6xl">
              {loading ? '…' : salonName}
            </h1>
            <p className="book-animate-in book-animate-delay-2 mt-4 max-w-lg text-base text-white/80 sm:text-lg">
              Professional services, clear pricing, and a seamless booking experience —
              reserved in minutes.
            </p>
          </div>
          {heroEmblemUrl ? (
            <div className="book-animate-in book-animate-delay-2 shrink-0 self-center sm:self-end">
              <div className="relative h-28 w-28 overflow-hidden rounded-full border-4 border-white/80 shadow-[0_12px_40px_rgba(0,0,0,0.35)] sm:h-36 sm:w-36">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src={heroEmblemUrl}
                  alt=""
                  className="h-full w-full object-cover"
                />
              </div>
            </div>
          ) : null}
        </div>
      </header>

      <div className="border-b border-[var(--book-line)] bg-white">
        <div className="mx-auto grid max-w-5xl gap-4 px-4 py-4 text-sm sm:grid-cols-3 sm:px-6">
          <div className="flex items-start gap-3">
            <span className="mt-0.5 h-2 w-2 shrink-0 rounded-full bg-[var(--book-moss)]" />
            <div>
              <p className="font-semibold">Verified availability</p>
              <p className="text-[var(--book-muted)]">Live slots from the salon schedule</p>
            </div>
          </div>
          <div className="flex items-start gap-3">
            <span className="mt-0.5 h-2 w-2 shrink-0 rounded-full bg-[var(--book-moss)]" />
            <div>
              <p className="font-semibold">Instant confirmation</p>
              <p className="text-[var(--book-muted)]">Your appointment is held immediately</p>
            </div>
          </div>
          <div className="flex items-start gap-3">
            <span className="mt-0.5 h-2 w-2 shrink-0 rounded-full bg-[var(--book-moss)]" />
            <div>
              <p className="font-semibold">Secure & private</p>
              <p className="text-[var(--book-muted)]">Details used only for this booking</p>
            </div>
          </div>
        </div>
        <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 border-t border-[var(--book-line)] px-4 py-3 sm:px-6">
          <p className="text-sm text-[var(--book-muted)]">
            Plans, packages, and loyalty — see which benefit fits how you visit.
          </p>
          <Link
            href={`/book/${tenantSlug}/memberships`}
            className="inline-flex items-center justify-center rounded-md bg-[var(--book-moss)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--book-moss-deep)]"
          >
            Compare memberships
          </Link>
        </div>
      </div>

      <main className="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 sm:py-10">
        {!loading && !loadError ? (
          <div className="mb-8">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
              <ol className="flex flex-wrap items-center gap-x-1 gap-y-2 text-sm">
                {STEPS.map((item, index) => {
                  const active = item.key === step;
                  const done = index < stepIndex;
                  return (
                    <li key={item.key} className="flex items-center gap-1">
                      {index > 0 ? (
                        <span className="mx-1 text-[var(--book-line)]" aria-hidden>
                          /
                        </span>
                      ) : null}
                      <span
                        className={
                          active
                            ? 'font-semibold text-[var(--book-moss)]'
                            : done
                              ? 'font-medium text-[var(--book-ink)]'
                              : 'text-[var(--book-muted)]'
                        }
                      >
                        <span className="mr-1 tabular-nums text-xs opacity-70">
                          {String(index + 1).padStart(2, '0')}
                        </span>
                        {item.label}
                      </span>
                    </li>
                  );
                })}
              </ol>
              <p className="text-xs font-medium uppercase tracking-[0.14em] text-[var(--book-muted)]">
                Step {stepIndex + 1} of {STEPS.length}
              </p>
            </div>
            <div className="h-1 overflow-hidden rounded-full bg-[var(--book-panel)]">
              <div
                className="book-progress-fill h-full rounded-full bg-[var(--book-moss)]"
                style={{ width: `${progressPct}%` }}
              />
            </div>
          </div>
        ) : null}

        {loading ? (
          <div className={panelClass()}>
            <p className="text-sm text-[var(--book-muted)]">Loading services…</p>
          </div>
        ) : loadError ? (
          <div className={`${panelClass()} border-red-200`}>
            <h2 className="book-display text-2xl font-bold">Unable to book</h2>
            <p className="mt-2 text-sm text-red-700">{loadError}</p>
            <p className="mt-2 text-sm text-[var(--book-muted)]">
              Check the salon link and that the API is running with a valid tenant slug.
            </p>
            <button
              type="button"
              className={`${primaryBtnClass()} mt-6`}
              onClick={() => void loadCatalog()}
            >
              Retry
            </button>
          </div>
        ) : (
          <>
            {step === 'service' ? (
              <section className="book-animate-in">
                <div className="max-w-2xl">
                  <h2 className="book-display text-3xl font-bold sm:text-4xl">Choose a service</h2>
                  <p className="mt-2 text-[var(--book-muted)]">
                    Select what you&apos;d like, then we&apos;ll show available times with your
                    preferred stylist.
                  </p>
                </div>
                {!catalog?.services.length ? (
                  <div className="mt-6 rounded-2xl border border-dashed border-[var(--book-line)] bg-white/70 p-8 text-center">
                    <p className="text-sm text-[var(--book-muted)]">
                      No services are available for online booking yet.
                    </p>
                  </div>
                ) : (
                  <ul className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {catalog.services.map((service, index) => (
                      <li
                        key={service.id}
                        className={`book-animate-in ${index === 1 ? 'book-animate-delay-1' : ''} ${index >= 2 ? 'book-animate-delay-2' : ''}`}
                      >
                        <button
                          type="button"
                          onClick={() => {
                            setServiceId(service.id);
                            setPricingTier('regular');
                            setStep('when');
                          }}
                          className="group flex h-full w-full flex-col overflow-hidden rounded-2xl border border-[var(--book-line)] bg-white text-left shadow-[var(--book-shadow)] transition hover:-translate-y-0.5 hover:border-[var(--book-moss)]"
                        >
                          <div className="relative h-36 w-full overflow-hidden bg-[var(--book-panel)] lg:h-32">
                            <Image
                              src={serviceImageSrc(service.category)}
                              alt=""
                              fill
                              sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 25vw"
                              className="object-cover transition duration-500 group-hover:scale-105"
                            />
                            <div className="absolute inset-0 bg-gradient-to-t from-black/35 to-transparent" />
                            <span className="absolute bottom-3 left-3 rounded bg-white/90 px-2 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-[var(--book-ink)]">
                              {categoryLabel(service.category)}
                            </span>
                          </div>
                          <div className="flex flex-1 flex-col p-4">
                            <span className="book-display text-lg font-bold group-hover:text-[var(--book-moss-deep)] lg:text-xl">
                              {service.name}
                            </span>
                            {service.description ? (
                              <span className="mt-2 line-clamp-3 text-sm text-[var(--book-muted)]">
                                {service.description}
                              </span>
                            ) : null}
                            <div className="mt-auto pt-3">
                              <span className="text-sm text-[var(--book-muted)]">
                                {service.duration_minutes} min
                                {service.deposit_required
                                  ? ` · deposit ${formatMoney(service.deposit_amount_cents)}`
                                  : ''}
                              </span>
                              <ServicePriceTiers service={service} />
                            </div>
                            <span className="mt-3 text-sm font-semibold text-[var(--book-moss)]">
                              Select service →
                            </span>
                          </div>
                        </button>
                      </li>
                    ))}
                  </ul>
                )}
              </section>
            ) : null}

            {step === 'when' && selectedService ? (
              <section className="book-animate-in space-y-5">
                <div className="overflow-hidden rounded-2xl border border-[var(--book-line)] bg-white shadow-[var(--book-shadow)] sm:grid sm:grid-cols-[220px_1fr]">
                  <div className="relative hidden h-full min-h-[160px] sm:block">
                    <Image
                      src={serviceImageSrc(selectedService.category)}
                      alt=""
                      fill
                      sizes="220px"
                      className="object-cover"
                    />
                  </div>
                  <div className="p-5 sm:p-6">
                    <button
                      type="button"
                      className="text-sm font-medium text-[var(--book-muted)] hover:text-[var(--book-ink)]"
                      onClick={() => setStep('service')}
                    >
                      ← Change service
                    </button>
                    <h2 className="book-display mt-2 text-2xl font-bold sm:text-3xl">
                      {selectedService.name}
                    </h2>
                    <p className="mt-1 text-sm text-[var(--book-muted)]">
                      {selectedService.duration_minutes} min
                    </p>
                    <div className="mt-2 max-w-sm">
                      <p className="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--book-muted)]">
                        Choose pricing
                      </p>
                      <ServicePriceTiers
                        service={selectedService}
                        selected={pricingTier}
                        onSelect={selectPricingTier}
                      />
                    </div>
                  </div>
                </div>

                <div className={panelClass()}>
                  <h3 className="text-sm font-semibold uppercase tracking-[0.14em] text-[var(--book-muted)]">
                    Preferences
                  </h3>
                  <div className="mt-4 grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm">
                      <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">
                        Location
                      </span>
                      <select
                        className={fieldClass()}
                        value={locationId}
                        onChange={(e) => setLocationId(e.target.value)}
                      >
                        {catalog?.locations.map((loc) => (
                          <option key={loc.id} value={loc.id}>
                            {loc.name}
                          </option>
                        ))}
                      </select>
                    </label>
                    <label className="block text-sm">
                      <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">
                        Stylist (optional)
                      </span>
                      <select
                        className={fieldClass()}
                        value={providerId}
                        onChange={(e) => setProviderId(e.target.value)}
                      >
                        <option value="">Any available</option>
                        {catalog?.providers.map((p) => (
                          <option key={p.id} value={p.id}>
                            {p.display_name}
                          </option>
                        ))}
                      </select>
                    </label>
                    <label className="block text-sm sm:col-span-2">
                      <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">Date</span>
                      <input
                        type="date"
                        className={fieldClass()}
                        value={date}
                        min={new Date().toISOString().slice(0, 10)}
                        onChange={(e) => setDate(e.target.value)}
                      />
                    </label>
                  </div>
                  <button
                    type="button"
                    className={`${secondaryBtnClass()} mt-4`}
                    onClick={() => void loadSlots()}
                  >
                    Refresh times
                  </button>
                </div>

                <div className={panelClass()}>
                  <h3 className="text-sm font-semibold uppercase tracking-[0.14em] text-[var(--book-muted)]">
                    Available times
                  </h3>
                  {slotsLoading ? (
                    <p className="mt-4 text-sm text-[var(--book-muted)]">Finding openings…</p>
                  ) : slotsError ? (
                    <p className="mt-4 text-sm text-red-700">{slotsError}</p>
                  ) : !slots.length ? (
                    <p className="mt-4 text-sm text-[var(--book-muted)]">
                      No open slots for this day. Try another date or stylist.
                    </p>
                  ) : (
                    <ul className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                      {slots.map((slot) => {
                        const key = `${slot.starts_at}-${slot.team_member_id}`;
                        const selected =
                          selectedSlot?.starts_at === slot.starts_at &&
                          selectedSlot.team_member_id === slot.team_member_id;
                        return (
                          <li key={key}>
                            <button
                              type="button"
                              onClick={() => setSelectedSlot(slot)}
                              className={`w-full rounded-xl border px-4 py-3 text-left text-sm transition ${
                                selected
                                  ? 'border-[var(--book-moss)] bg-[var(--book-moss)] text-white'
                                  : 'border-[var(--book-line)] bg-[var(--book-wash)] hover:border-[var(--book-moss)]'
                              }`}
                            >
                              <span className="block font-semibold">
                                {formatSlotTime(slot.starts_at)}
                              </span>
                              {slot.provider_name ? (
                                <span
                                  className={`mt-0.5 block text-xs ${selected ? 'text-white/75' : 'text-[var(--book-muted)]'}`}
                                >
                                  with {slot.provider_name}
                                </span>
                              ) : null}
                            </button>
                          </li>
                        );
                      })}
                    </ul>
                  )}
                  <button
                    type="button"
                    className={`${primaryBtnClass(!selectedSlot)} mt-5`}
                    disabled={!selectedSlot}
                    onClick={() => setStep('details')}
                  >
                    Continue
                  </button>
                </div>
              </section>
            ) : null}

            {step === 'details' && selectedService && selectedSlot ? (
              <section className="book-animate-in">
                <button
                  type="button"
                  className="text-sm font-medium text-[var(--book-muted)] hover:text-[var(--book-ink)]"
                  onClick={() => setStep('when')}
                >
                  ← Change time
                </button>
                <h2 className="book-display mt-2 text-3xl font-bold sm:text-4xl">Your details</h2>
                <p className="mt-2 text-sm text-[var(--book-muted)]">
                  {selectedService.name} · {formatSlotTime(selectedSlot.starts_at)}
                  {selectedSlot.provider_name ? ` · ${selectedSlot.provider_name}` : ''}
                </p>

                <div className={`mt-6 ${panelClass()}`}>
                  <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm">
                      <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">
                        First name
                      </span>
                      <input
                        className={fieldClass()}
                        value={firstName}
                        onChange={(e) => setFirstName(e.target.value)}
                        required
                      />
                    </label>
                    <label className="block text-sm">
                      <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">
                        Last name
                      </span>
                      <input
                        className={fieldClass()}
                        value={lastName}
                        onChange={(e) => setLastName(e.target.value)}
                        required
                      />
                    </label>
                    <label className="block text-sm sm:col-span-2">
                      <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">Email</span>
                      <input
                        type="email"
                        className={fieldClass()}
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        required
                      />
                    </label>
                    <label className="block text-sm sm:col-span-2">
                      <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">
                        Mobile / WhatsApp
                      </span>
                      <input
                        className={fieldClass()}
                        value={phone}
                        onChange={(e) => {
                          setPhone(e.target.value);
                          if (!e.target.value.trim()) setWhatsappOptIn(false);
                        }}
                        placeholder="+44…"
                        inputMode="tel"
                        autoComplete="tel"
                      />
                    </label>
                    <label className="flex items-start gap-3 text-sm sm:col-span-2">
                      <input
                        type="checkbox"
                        className="mt-1"
                        checked={whatsappOptIn}
                        disabled={!phone.trim()}
                        onChange={(e) => setWhatsappOptIn(e.target.checked)}
                      />
                      <span className="text-[var(--book-muted)]">
                        <span className="font-semibold text-[var(--book-ink)]">
                          Send booking updates on WhatsApp
                        </span>
                        <span className="mt-0.5 block text-xs leading-relaxed">
                          Confirmations, cancellations, and running-late reschedules. You can stop
                          anytime by messaging the salon. Requires a mobile number.
                        </span>
                      </span>
                    </label>
                    <label className="block text-sm sm:col-span-2">
                      <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">
                        Notes (optional)
                      </span>
                      <textarea
                        className={fieldClass()}
                        rows={3}
                        value={notes}
                        onChange={(e) => setNotes(e.target.value)}
                      />
                    </label>
                  </div>
                  {submitError ? <p className="mt-3 text-sm text-red-700">{submitError}</p> : null}
                  <button
                    type="button"
                    className={`${primaryBtnClass(
                      submitting ||
                        !firstName.trim() ||
                        !lastName.trim() ||
                        !email.trim() ||
                        (whatsappOptIn && !phone.trim()),
                    )} mt-5`}
                    disabled={
                      submitting ||
                      !firstName.trim() ||
                      !lastName.trim() ||
                      !email.trim() ||
                      (whatsappOptIn && !phone.trim())
                    }
                    onClick={() => void handleBook()}
                  >
                    {submitting ? 'Booking…' : 'Confirm booking'}
                  </button>
                </div>
              </section>
            ) : null}

            {step === 'done' && appointment ? (
              <section className="book-animate-in overflow-hidden rounded-2xl border border-[var(--book-line)] bg-white shadow-[var(--book-shadow)]">
                <div className="relative h-40 w-full sm:h-48">
                  <Image
                    src={heroImageSrc}
                    alt=""
                    fill
                    sizes="100vw"
                    unoptimized={!heroImageIsDefault}
                    className="object-cover object-center"
                  />
                  <div className="absolute inset-0 bg-[rgba(18,24,22,0.45)]" />
                  <p className="absolute bottom-4 left-5 text-xs font-semibold uppercase tracking-[0.2em] text-white/85">
                    Confirmed
                  </p>
                </div>
                <div className="p-8 text-center sm:p-10">
                  <h2 className="book-display text-3xl font-bold sm:text-4xl">You&apos;re booked</h2>
                  <p className="mx-auto mt-3 max-w-md text-sm text-[var(--book-muted)]">
                    Thanks — we&apos;ve reserved your appointment
                    {appointment.booking_reference
                      ? ` (ref ${appointment.booking_reference})`
                      : ''}
                    . A confirmation has been sent to your email or phone when available.
                  </p>
                  <dl className="mx-auto mt-8 max-w-sm space-y-3 text-left text-sm">
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
                  </dl>
                  <div className="mx-auto mt-8 flex max-w-md flex-col gap-3 sm:flex-row sm:justify-center">
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
                  {appointment.manage_path ||
                  (appointment.booking_reference && appointment.public_manage_token) ? (
                    <p className="mx-auto mt-6 max-w-md text-sm text-[var(--book-muted)]">
                      <Link
                        href={
                          appointment.manage_path ??
                          `/book/${tenantSlug}/manage?ref=${encodeURIComponent(appointment.booking_reference!)}&token=${encodeURIComponent(appointment.public_manage_token!)}`
                        }
                        className="font-semibold text-[var(--book-moss)] underline-offset-2 hover:underline"
                      >
                        Manage or cancel this booking
                      </Link>
                    </p>
                  ) : null}
                  <button
                    type="button"
                    className={`${secondaryBtnClass()} mt-8`}
                    onClick={resetFlow}
                  >
                    Book another visit
                  </button>
                </div>
              </section>
            ) : null}
          </>
        )}

        {/* Shop sits above reviews so click-and-collect stays visible. */}
        {!loadError ? (
          <BookingShopCarousel
            tenantSlug={tenantSlug}
            locationId={locationId || catalog?.locations[0]?.id || ''}
          />
        ) : null}

        {referNotice ? (
          <p className="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
            {referNotice}
          </p>
        ) : null}

        {!loadError ? <BookingReviewsSection tenantSlug={tenantSlug} /> : null}
        {!loadError ? <PublicLookbookSection tenantSlug={tenantSlug} /> : null}
        {!loadError ? <PublicGallerySection tenantSlug={tenantSlug} /> : null}

        <footer className="mt-14 border-t border-[var(--book-line)] pt-6">
          <SocialFooterIcons
            className="mb-4"
            facebookUrl={catalog?.tenant.branding?.social_facebook_url}
            instagramUrl={catalog?.tenant.branding?.social_instagram_url}
            tiktokUrl={catalog?.tenant.branding?.social_tiktok_url}
          />
          <div className="flex flex-col items-center justify-between gap-3 text-xs text-[var(--book-muted)] sm:flex-row">
            <p>
              © {new Date().getFullYear()} {salonName}. Booking powered by NeatMeet OS.
            </p>
            <p className="font-medium tracking-wide">Secure · Tenant-isolated · Instant confirm</p>
          </div>
        </footer>
      </main>

      {catalog && !loadError ? (
        <VoiceBookingConcierge
          tenantSlug={tenantSlug}
          catalog={catalog}
          locationId={locationId || catalog.locations[0]?.id || ''}
          salonName={salonName}
        />
      ) : null}
    </div>
  );
}
