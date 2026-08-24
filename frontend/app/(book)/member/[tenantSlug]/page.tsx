'use client';

import Link from 'next/link';
import { Suspense, useCallback, useEffect, useMemo, useRef, useState, type CSSProperties } from 'react';
import { useParams, useRouter, useSearchParams } from 'next/navigation';
import { SocialFooterIcons } from '@/components/public/SocialFooterIcons';
import type { Appointment, OnlineBookingCatalog } from '@/lib/booking-types';
import { fetchOnlineCatalog } from '@/services/online-booking.service';
import {
  fetchMemberNextVisit,
  scheduleMemberNextVisit,
} from '@/services/next-visit.service';
import {
  clearMemberSession,
  fetchMemberBootstrap,
  fetchMemberDashboard,
  fetchMemberGifts,
  fetchMemberLoyalty,
  fetchMemberVisits,
  formatMoney,
  fetchReferral,
  loadMemberSession,
  memberCheckIn,
  memberCheckOut,
  memberClaimGift,
  memberCreateGift,
  memberLogin,
  memberRequestOtp,
  memberLogout,
  memberFetchNotices,
  memberMarkNoticeRead,
  memberPurchase,
  memberSubscribePush,
  registerMemberServiceWorker,
  saveMemberSession,
  sendReferralEmails,
  type MemberDashboard,
  type MemberGift,
  type MemberLoyaltyEntry,
  type MemberNotice,
  type MemberPortalBootstrap,
  type MemberPortalLocation,
  type MemberReferralPayload,
  type MemberSession,
  type MemberVisitRow,
} from '@/services/member-portal.service';

type Tab = 'home' | 'visits' | 'points' | 'membership' | 'shop' | 'gifts' | 'inbox' | 'refer';

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

function urlBase64ToUint8Array(base64String: string): BufferSource {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = window.atob(base64);
  const output = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; i += 1) {
    output[i] = rawData.charCodeAt(i);
  }
  return output;
}

function haversineMeters(lat1: number, lon1: number, lat2: number, lon2: number): number {
  const toRad = (deg: number) => (deg * Math.PI) / 180;
  const R = 6371000;
  const dLat = toRad(lat2 - lat1);
  const dLon = toRad(lon2 - lon1);
  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) * Math.sin(dLon / 2);
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function todayKey(): string {
  return new Date().toISOString().slice(0, 10);
}

function notifyOnce(key: string, title: string, body: string): void {
  if (typeof window === 'undefined' || typeof Notification === 'undefined') return;
  if (sessionStorage.getItem(key)) return;
  if (Notification.permission !== 'granted') return;
  try {
    new Notification(title, { body, icon: '/member-icons/icon-192.svg' });
    sessionStorage.setItem(key, '1');
  } catch {
    // Ignore
  }
}

function MemberPortalInner() {
  const params = useParams<{ tenantSlug: string }>();
  const search = useSearchParams();
  const router = useRouter();
  const tenantSlug = params.tenantSlug;
  const nextPath = search.get('next') || '';
  const tierHint = search.get('tier') || '';

  const [bootstrap, setBootstrap] = useState<MemberPortalBootstrap | null>(null);
  const [session, setSession] = useState<MemberSession | null>(null);
  const [dashboard, setDashboard] = useState<MemberDashboard | null>(null);
  const [visits, setVisits] = useState<MemberVisitRow[]>([]);
  const [loyaltyEntries, setLoyaltyEntries] = useState<MemberLoyaltyEntry[]>([]);
  const [gifts, setGifts] = useState<MemberGift[]>([]);
  const [notices, setNotices] = useState<MemberNotice[]>([]);
  const [unreadNotices, setUnreadNotices] = useState(0);
  const [tab, setTab] = useState<Tab>('home');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [otp, setOtp] = useState('');
  const [otpSent, setOtpSent] = useState(false);
  const [maskedPhone, setMaskedPhone] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [checkingIn, setCheckingIn] = useState(false);
  const [checkingOut, setCheckingOut] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [notRegistered, setNotRegistered] = useState(false);
  const [installPrompt, setInstallPrompt] = useState<{ prompt: () => Promise<void> } | null>(null);
  const [giftPackageId, setGiftPackageId] = useState('');
  const [giftQty, setGiftQty] = useState('1');
  const [giftName, setGiftName] = useState('');
  const [claimCode, setClaimCode] = useState('');
  const [referral, setReferral] = useState<MemberReferralPayload | null>(null);
  const [inviteEmails, setInviteEmails] = useState('');
  const [copyNotice, setCopyNotice] = useState<string | null>(null);
  const [promptNextVisit, setPromptNextVisit] = useState(false);
  const [scheduleVisitId, setScheduleVisitId] = useState<string | null>(null);
  const [scheduleOpen, setScheduleOpen] = useState(false);
  const [nextVisits, setNextVisits] = useState<Appointment[]>([]);
  const [scheduleCatalog, setScheduleCatalog] = useState<OnlineBookingCatalog | null>(null);
  const [scheduleStartsAt, setScheduleStartsAt] = useState('');
  const [scheduleLocationId, setScheduleLocationId] = useState('');
  const [scheduleTeamMemberId, setScheduleTeamMemberId] = useState('');
  const [scheduleServiceId, setScheduleServiceId] = useState('');
  const [scheduleNotes, setScheduleNotes] = useState('');
  const [scheduling, setScheduling] = useState(false);

  const insideRef = useRef(false);
  const checkedInTodayRef = useRef(false);
  const permissionAskedRef = useRef(false);

  const accent = bootstrap?.tenant.branding?.primary_color || '#2f5a45';
  const salonName =
    bootstrap?.tenant.branding?.brand_display_name || bootstrap?.tenant.name || tenantSlug;

  const refreshDashboard = useCallback(
    async (token: string) => {
      const [dash, visitRows, loyalty, giftRows, noticePayload, referralPayload, nextVisitRows] =
        await Promise.all([
          fetchMemberDashboard(tenantSlug, token),
          fetchMemberVisits(tenantSlug, token),
          fetchMemberLoyalty(tenantSlug, token),
          fetchMemberGifts(tenantSlug, token),
          memberFetchNotices(tenantSlug, token),
          fetchReferral(tenantSlug, token),
          fetchMemberNextVisit(tenantSlug, token).catch(() => [] as Appointment[]),
        ]);
      setDashboard(dash);
      setVisits(visitRows);
      setLoyaltyEntries(loyalty.entries);
      setGifts(giftRows);
      setNotices(noticePayload.items);
      setUnreadNotices(noticePayload.unread_count);
      setReferral(referralPayload);
      setNextVisits(nextVisitRows);
      checkedInTodayRef.current = Boolean(dash.checked_in_today);
      setSession((prev) =>
        prev
          ? {
              ...prev,
              client: dash.client,
              benefits: dash.benefits,
              checked_in_today: dash.checked_in_today,
              open_visit: dash.open_visit ?? null,
              last_visited_at: dash.last_visited_at,
              loyalty_points_balance: dash.loyalty_points_balance,
            }
          : prev,
      );
    },
    [tenantSlug],
  );

  const refreshSession = useCallback(async () => {
    const stored = loadMemberSession(tenantSlug);
    if (!stored?.token) {
      setSession(null);
      setDashboard(null);
      return;
    }
    try {
      setSession(stored);
      await refreshDashboard(stored.token);
    } catch {
      clearMemberSession(tenantSlug);
      setSession(null);
      setDashboard(null);
    }
  }, [tenantSlug, refreshDashboard]);

  useEffect(() => {
    void (async () => {
      setLoading(true);
      try {
        const data = await fetchMemberBootstrap(tenantSlug);
        setBootstrap(data);
        await registerMemberServiceWorker();
        await refreshSession();
      } catch (e) {
        setError(e instanceof Error ? e.message : 'Unable to load membership app');
      } finally {
        setLoading(false);
      }
    })();
  }, [tenantSlug, refreshSession]);

  useEffect(() => {
    const onBeforeInstall = (e: Event) => {
      e.preventDefault();
      const evt = e as Event & { prompt: () => Promise<void> };
      setInstallPrompt({ prompt: () => evt.prompt() });
    };
    window.addEventListener('beforeinstallprompt', onBeforeInstall);
    return () => window.removeEventListener('beforeinstallprompt', onBeforeInstall);
  }, []);

  useEffect(() => {
    if (!session?.token || typeof window === 'undefined') return;
    if (!('geolocation' in navigator) || typeof Notification === 'undefined') return;

    const locations = (bootstrap?.locations ?? []).filter(
      (loc): loc is MemberPortalLocation & { latitude: number; longitude: number } =>
        loc.latitude != null && loc.longitude != null,
    );
    if (locations.length === 0) return;

    let cancelled = false;
    let watchId: number | null = null;

    void (async () => {
      if (!permissionAskedRef.current && Notification.permission === 'default') {
        permissionAskedRef.current = true;
        try {
          await Notification.requestPermission();
        } catch {
          // ignore
        }
      }
      if (cancelled) return;

      if (Notification.permission === 'granted' && 'serviceWorker' in navigator && 'PushManager' in window) {
        try {
          const vapidKey = bootstrap?.vapid_public_key;
          if (!vapidKey) {
            return;
          }
          const reg = await navigator.serviceWorker.ready;
          let sub = await reg.pushManager.getSubscription();
          if (!sub) {
            sub = await reg.pushManager.subscribe({
              userVisibleOnly: true,
              applicationServerKey: urlBase64ToUint8Array(vapidKey),
            }).catch(() => null);
          }
          if (sub) {
            await memberSubscribePush(tenantSlug, session.token, sub.toJSON());
          }
        } catch {
          // Push subscribe may fail without valid VAPID — geofence notifications still work.
        }
      }

      watchId = navigator.geolocation.watchPosition(
        (pos) => {
          const { latitude, longitude } = pos.coords;
          let nearestInside: (typeof locations)[number] | null = null;
          for (const loc of locations) {
            const radius = loc.geofence_radius_meters ?? 100;
            const distance = haversineMeters(latitude, longitude, loc.latitude, loc.longitude);
            if (distance <= radius) {
              nearestInside = loc;
              break;
            }
          }

          const day = todayKey();
          const wasInside = insideRef.current;

          if (nearestInside && !wasInside) {
            insideRef.current = true;
            notifyOnce(
              `nm_geo_enter_${tenantSlug}_${day}`,
              salonName,
              `Welcome to "${salonName}", remember to login, checkin and claim your loyalty point before leaving`,
            );
          } else if (!nearestInside && wasInside) {
            insideRef.current = false;
            if (!checkedInTodayRef.current) {
              const firstName = session.client.first_name || 'there';
              notifyOnce(
                `nm_geo_leave_${tenantSlug}_${day}`,
                salonName,
                `Hello ${firstName}, seems you forgot to check-in, message the "${salonName}" now to check-in for you`,
              );
            }
          }
        },
        () => {},
        { enableHighAccuracy: true, maximumAge: 15000, timeout: 20000 },
      );
    })();

    return () => {
      cancelled = true;
      if (watchId != null) navigator.geolocation.clearWatch(watchId);
    };
  }, [session?.token, session?.client.first_name, bootstrap?.locations, bootstrap?.vapid_public_key, tenantSlug, salonName]);

  const installHint = useMemo(() => {
    if (typeof navigator === 'undefined') return 'Add this page to your home screen for quick access.';
    const ua = navigator.userAgent;
    if (/iPhone|iPad|iPod/i.test(ua)) {
      return 'On iPhone: tap Share → Add to Home Screen to install this app.';
    }
    if (/Android/i.test(ua)) {
      return 'On Android: open the browser menu → Install app / Add to Home screen.';
    }
    return 'Use Install / Add to Home Screen to keep this app handy.';
  }, []);

  async function handleRequestOtp(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    setError(null);
    setNotRegistered(false);
    try {
      const result = await memberRequestOtp(tenantSlug, email.trim(), phone.trim());
      setOtpSent(true);
      setMaskedPhone(result.masked_phone);
      setNotice(`We sent a code to WhatsApp ${result.masked_phone}.`);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Could not send OTP';
      if (/sign up|not found|No membership|join our membership/i.test(message)) {
        setNotRegistered(true);
      }
      setError(message);
    } finally {
      setSubmitting(false);
    }
  }

  async function handleLogin(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    setError(null);
    setNotRegistered(false);
    try {
      const result = await memberLogin(tenantSlug, email.trim(), phone.trim(), otp.trim());
      setSession(result);
      await refreshDashboard(result.token);
      if (nextPath.startsWith('/')) {
        router.push(nextPath);
      }
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Login failed';
      if (/sign up|not found|No membership|join our membership/i.test(message)) {
        setNotRegistered(true);
      }
      setError(message);
    } finally {
      setSubmitting(false);
    }
  }

  async function handleCheckIn() {
    if (!session?.token) return;
    setCheckingIn(true);
    setNotice(null);
    setError(null);
    try {
      const status = await memberCheckIn(tenantSlug, session.token);
      const next: MemberSession = {
        ...session,
        checked_in_today: true,
        open_visit: status.open_visit ?? status.visit ?? null,
        last_visited_at: status.last_visited_at,
        loyalty_points_balance: status.loyalty_points_balance ?? session.loyalty_points_balance,
      };
      saveMemberSession(tenantSlug, next);
      setSession(next);
      checkedInTodayRef.current = true;
      setNotice(
        status.already_checked_in_today
          ? 'You are already checked in.'
          : `Checked in! +${status.points ?? 0} loyalty points.`,
      );
      if (status.prompt_next_visit && status.visit?.id) {
        setPromptNextVisit(true);
        setScheduleVisitId(status.visit.id);
      }
      await refreshDashboard(session.token);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Check-in failed');
    } finally {
      setCheckingIn(false);
    }
  }

  async function handleCheckOut() {
    if (!session?.token) return;
    setCheckingOut(true);
    setNotice(null);
    setError(null);
    try {
      await memberCheckOut(tenantSlug, session.token);
      setNotice('Checked out. See you next time.');
      await refreshDashboard(session.token);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Check-out failed');
    } finally {
      setCheckingOut(false);
    }
  }

  async function openSchedulePanel() {
    setScheduleOpen(true);
    setError(null);
    try {
      const catalog = await fetchOnlineCatalog(tenantSlug);
      setScheduleCatalog(catalog);
      if (!scheduleLocationId && catalog.locations[0]) {
        setScheduleLocationId(catalog.locations[0].id);
      }
      if (!scheduleTeamMemberId && catalog.providers[0]) {
        setScheduleTeamMemberId(catalog.providers[0].id);
      }
      if (!scheduleServiceId && catalog.services[0]) {
        setScheduleServiceId(catalog.services[0].id);
      }
      if (!scheduleStartsAt) {
        const d = new Date();
        d.setDate(d.getDate() + 7);
        d.setMinutes(0, 0, 0);
        const pad = (n: number) => String(n).padStart(2, '0');
        setScheduleStartsAt(
          `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`,
        );
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not load booking options');
    }
  }

  async function handleScheduleNextVisit(e: React.FormEvent) {
    e.preventDefault();
    if (!session?.token || !scheduleVisitId) return;
    if (!scheduleStartsAt || !scheduleLocationId || !scheduleTeamMemberId || !scheduleServiceId) {
      setError('Please pick a date/time, location, stylist, and service.');
      return;
    }
    setScheduling(true);
    setError(null);
    try {
      const local = scheduleStartsAt.trim();
      const startsAt =
        local.length === 16
          ? `${local.replace('T', ' ')}:00`
          : local.includes('T')
            ? local.replace('T', ' ')
            : local;
      await scheduleMemberNextVisit(tenantSlug, session.token, {
        visit_id: scheduleVisitId,
        starts_at: startsAt,
        location_id: scheduleLocationId,
        team_member_id: scheduleTeamMemberId,
        services: [{ booking_service_id: scheduleServiceId }],
        client_notes: scheduleNotes.trim() || undefined,
      });
      setPromptNextVisit(false);
      setScheduleOpen(false);
      setScheduleVisitId(null);
      setNotice('Next visit scheduled.');
      await refreshDashboard(session.token);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not schedule next visit');
    } finally {
      setScheduling(false);
    }
  }

  async function handlePurchase(offerType: 'plan' | 'package', offerId: string, label: string) {
    if (!session?.token) return;
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      const result = await memberPurchase(tenantSlug, session.token, offerType, offerId);
      setNotice(`Purchased ${label} for ${formatMoney(result.amount_cents)}.`);
      await refreshDashboard(session.token);
      setTab('membership');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Purchase failed');
    } finally {
      setBusy(false);
    }
  }

  async function handleCreateGift(e: React.FormEvent) {
    e.preventDefault();
    if (!session?.token || !giftPackageId) return;
    setBusy(true);
    setError(null);
    try {
      const gift = await memberCreateGift(tenantSlug, session.token, {
        client_package_id: giftPackageId,
        quantity: Number(giftQty) || 1,
        recipient_name: giftName || undefined,
      });
      setNotice(`Gift code created: ${gift.code}`);
      setGiftName('');
      await refreshDashboard(session.token);
      const giftRows = await fetchMemberGifts(tenantSlug, session.token);
      setGifts(giftRows);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not create gift');
    } finally {
      setBusy(false);
    }
  }

  async function handleClaimGift(e: React.FormEvent) {
    e.preventDefault();
    if (!session?.token || !claimCode.trim()) return;
    setBusy(true);
    setError(null);
    try {
      const gift = await memberClaimGift(tenantSlug, session.token, claimCode.trim());
      setNotice(`Claimed ${gift.package_name ?? 'package'} (${gift.quantity} visits).`);
      setClaimCode('');
      await refreshDashboard(session.token);
      const giftRows = await fetchMemberGifts(tenantSlug, session.token);
      setGifts(giftRows);
      setTab('membership');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not claim gift');
    } finally {
      setBusy(false);
    }
  }

  async function handleCopyReferralLink() {
    if (!referral?.join_url) return;
    try {
      await navigator.clipboard.writeText(referral.join_url);
      setCopyNotice('Join link copied');
      window.setTimeout(() => setCopyNotice(null), 2500);
    } catch {
      setCopyNotice('Could not copy — select the link manually');
    }
  }

  async function handleSendReferralEmails(e: React.FormEvent) {
    e.preventDefault();
    if (!session?.token) return;
    const emails = inviteEmails
      .split(/[\s,;]+/)
      .map((v) => v.trim())
      .filter(Boolean);
    if (emails.length === 0) {
      setError('Enter at least one email address');
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const result = await sendReferralEmails(tenantSlug, session.token, emails);
      setNotice(`Sent ${result.sent} invite(s)${result.skipped ? `, skipped ${result.skipped}` : ''}.`);
      setInviteEmails('');
      const payload = await fetchReferral(tenantSlug, session.token);
      setReferral(payload);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not send invites');
    } finally {
      setBusy(false);
    }
  }

  const tabs: Array<{ id: Tab; label: string }> = [
    { id: 'home', label: 'Home' },
    { id: 'inbox', label: unreadNotices > 0 ? `Inbox (${unreadNotices})` : 'Inbox' },
    { id: 'visits', label: 'Visits' },
    { id: 'points', label: 'Points' },
    { id: 'membership', label: 'Plans' },
    { id: 'shop', label: 'Shop' },
    { id: 'gifts', label: 'Gifts' },
    { id: 'refer', label: 'Refer' },
  ];

  return (
    <div className="book-portal min-h-screen" style={{ ['--book-moss' as string]: accent } as CSSProperties}>
      <main className="mx-auto flex min-h-screen max-w-lg flex-col px-4 py-8 sm:px-6">
        <div className="rounded-2xl border border-[var(--book-line)] bg-white p-5 shadow-[var(--book-shadow)] sm:p-7">
          <p className="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--book-muted)]">
            Membership app
          </p>
          <h1 className="book-display mt-2 text-3xl font-bold text-[var(--book-ink)]">{salonName}</h1>

          {loading ? <p className="mt-8 text-sm text-[var(--book-muted)]">Loading…</p> : null}

          {!loading && session && dashboard ? (
            <div className="mt-6 space-y-5">
              <div className="rounded-xl bg-[var(--book-wash)] px-4 py-4">
                <p className="font-semibold text-[var(--book-ink)]">
                  Hi {dashboard.client.first_name || 'there'}
                </p>
                <p className="mt-1 text-sm text-[var(--book-muted)]">
                  {dashboard.loyalty_points_balance} pts · Wallet{' '}
                  {formatMoney(dashboard.wallet_balance_cents)}
                </p>
                <p className="mt-1 text-sm text-[var(--book-ink)]">
                  Today:{' '}
                  {dashboard.open_visit
                    ? 'On site (checked in)'
                    : dashboard.checked_in_today
                      ? 'Visited earlier'
                      : 'Not checked in yet'}
                </p>
              </div>

              <div className="flex gap-1 overflow-x-auto pb-1">
                {tabs.map((t) => (
                  <button
                    key={t.id}
                    type="button"
                    onClick={() => setTab(t.id)}
                    className={[
                      'shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold',
                      tab === t.id
                        ? 'bg-[var(--book-moss)] text-white'
                        : 'bg-[var(--book-wash)] text-[var(--book-muted)]',
                    ].join(' ')}
                  >
                    {t.label}
                  </button>
                ))}
              </div>

              {error ? (
                <p className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                  {error}
                </p>
              ) : null}
              {notice ? (
                <p className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                  {notice}
                </p>
              ) : null}

              {promptNextVisit ? (
                <div className="rounded-lg border border-[#2f5a45]/30 bg-[#f4faf6] px-3 py-3 text-sm">
                  <p className="font-semibold text-[var(--book-ink)]">Schedule next visit</p>
                  <p className="mt-1 text-[var(--book-muted)]">
                    Lock in your next appointment while you&apos;re here.
                  </p>
                  <button
                    type="button"
                    className="mt-3 inline-flex rounded-md bg-[var(--book-moss)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--book-moss-deep)]"
                    onClick={() => void openSchedulePanel()}
                  >
                    Schedule now
                  </button>
                </div>
              ) : null}

              {tab === 'home' ? (
                <div className="space-y-4">
                  {dashboard.open_visit ? (
                    <button
                      type="button"
                      className={primaryBtnClass(checkingOut)}
                      disabled={checkingOut}
                      onClick={() => void handleCheckOut()}
                    >
                      {checkingOut ? 'Checking out…' : 'Clock out / Check out'}
                    </button>
                  ) : dashboard.checked_in_today ? (
                    <button type="button" className={primaryBtnClass(true)} disabled>
                      Checked in earlier today
                    </button>
                  ) : (
                    <button
                      type="button"
                      className={primaryBtnClass(checkingIn)}
                      disabled={checkingIn}
                      onClick={() => void handleCheckIn()}
                    >
                      {checkingIn ? 'Checking in…' : 'Clock in / Check in visit'}
                    </button>
                  )}

                  {nextVisits.length > 0 ? (
                    <div className="space-y-2">
                      <p className="text-sm font-semibold text-[var(--book-ink)]">Your next visit</p>
                      {nextVisits.map((a) => (
                        <div
                          key={a.id}
                          className="rounded-lg border border-[#2f5a45]/25 bg-[#f4faf6] px-3 py-2 text-sm"
                        >
                          <p className="font-medium text-[var(--book-ink)]">
                            {a.starts_at ? new Date(a.starts_at).toLocaleString() : 'TBC'}
                          </p>
                          <p className="text-[var(--book-muted)]">
                            {(a.services || []).map((s) => s.service_name).join(', ') ||
                              'Next visit'}
                            {a.location?.name ? ` · ${a.location.name}` : ''}
                            {a.team_member?.display_name
                              ? ` · ${a.team_member.display_name}`
                              : ''}
                          </p>
                        </div>
                      ))}
                    </div>
                  ) : null}

                  {dashboard.upcoming_appointments.length > 0 ? (
                    <div className="space-y-2">
                      <p className="text-sm font-semibold text-[var(--book-ink)]">Upcoming</p>
                      {dashboard.upcoming_appointments.map((a) => (
                        <div
                          key={a.id}
                          className="rounded-lg border border-[var(--book-line)] px-3 py-2 text-sm"
                        >
                          <p className="font-medium text-[var(--book-ink)]">
                            {a.starts_at ? new Date(a.starts_at).toLocaleString() : 'TBC'}
                          </p>
                          <p className="text-[var(--book-muted)]">
                            {(a.services || []).join(', ') || 'Appointment'}
                            {a.location_name ? ` · ${a.location_name}` : ''}
                          </p>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <p className="text-sm text-[var(--book-muted)]">No upcoming appointments.</p>
                  )}

                  <div className="rounded-xl border border-[var(--book-line)] px-4 py-3 text-sm text-[var(--book-muted)]">
                    <p className="font-semibold text-[var(--book-ink)]">Install this app</p>
                    <p className="mt-1">{installHint}</p>
                    {installPrompt ? (
                      <button
                        type="button"
                        className="mt-3 w-full rounded-md border border-[var(--book-line)] px-3 py-2 text-sm font-semibold text-[var(--book-ink)]"
                        onClick={() => void installPrompt.prompt()}
                      >
                        Install now
                      </button>
                    ) : null}
                  </div>

                  <Link href={bootstrap?.book_path || `/book/${tenantSlug}`} className={primaryBtnClass()}>
                    Book with member pricing
                  </Link>
                </div>
              ) : null}

              {tab === 'inbox' ? (
                <div className="space-y-2">
                  {notices.length === 0 ? (
                    <p className="text-sm text-[var(--book-muted)]">
                      No messages yet. Offers and reminders from the salon will appear here.
                    </p>
                  ) : (
                    notices.map((n) => (
                      <button
                        key={n.id}
                        type="button"
                        className={`w-full rounded-lg border px-3 py-2 text-left text-sm transition ${
                          n.read_at
                            ? 'border-[var(--book-line)] bg-white'
                            : 'border-[var(--book-moss)] bg-[var(--book-wash)]'
                        }`}
                        onClick={async () => {
                          if (!session?.token || n.read_at) return;
                          try {
                            await memberMarkNoticeRead(tenantSlug, session.token, n.id);
                            await refreshDashboard(session.token);
                          } catch (err) {
                            setError(err instanceof Error ? err.message : 'Could not mark read');
                          }
                        }}
                      >
                        <p className="font-medium text-[var(--book-ink)]">{n.title}</p>
                        <p className="mt-1 whitespace-pre-wrap text-[var(--book-muted)]">{n.body}</p>
                        {n.created_at ? (
                          <p className="mt-1 text-xs text-[var(--book-muted)]">
                            {new Date(n.created_at).toLocaleString()}
                            {!n.read_at ? ' · Unread' : ''}
                          </p>
                        ) : null}
                        {n.href ? (
                          <a
                            href={n.href}
                            className="mt-2 inline-block text-xs font-semibold text-[var(--book-moss)] underline-offset-2 hover:underline"
                            onClick={(e) => e.stopPropagation()}
                          >
                            Open link
                          </a>
                        ) : null}
                      </button>
                    ))
                  )}
                </div>
              ) : null}

              {tab === 'visits' ? (
                <div className="space-y-2">
                  {visits.length === 0 ? (
                    <p className="text-sm text-[var(--book-muted)]">No visits yet. Check in on your next appointment.</p>
                  ) : (
                    visits.map((v) => (
                      <div key={v.id} className="rounded-lg border border-[var(--book-line)] px-3 py-2 text-sm">
                        <p className="font-medium text-[var(--book-ink)]">
                          {v.checked_in_at ? new Date(v.checked_in_at).toLocaleString() : '—'}
                          {v.checked_out_at
                            ? ` → ${new Date(v.checked_out_at).toLocaleString()}`
                            : ' (open)'}
                        </p>
                        <p className="text-[var(--book-muted)]">
                          {v.location?.name || 'Salon'} · +{v.loyalty_points_awarded} pts
                        </p>
                      </div>
                    ))
                  )}
                </div>
              ) : null}

              {tab === 'points' ? (
                <div className="space-y-2">
                  <p className="text-sm font-semibold text-[var(--book-ink)]">
                    Balance: {dashboard.loyalty_points_balance} points
                  </p>
                  {loyaltyEntries.length === 0 ? (
                    <p className="text-sm text-[var(--book-muted)]">No points activity yet.</p>
                  ) : (
                    loyaltyEntries.map((e) => (
                      <div key={e.id} className="rounded-lg border border-[var(--book-line)] px-3 py-2 text-sm">
                        <p className="font-medium text-[var(--book-ink)]">
                          {e.direction === 'debit' ? '−' : '+'}
                          {e.points} · {e.entry_type.replace(/_/g, ' ')}
                        </p>
                        <p className="text-[var(--book-muted)]">
                          {e.effective_at ? new Date(e.effective_at).toLocaleString() : ''}
                          {e.notes ? ` · ${e.notes}` : ''}
                        </p>
                      </div>
                    ))
                  )}
                </div>
              ) : null}

              {tab === 'membership' ? (
                <div className="space-y-4">
                  <div className="rounded-xl border border-[var(--book-line)] bg-[var(--book-wash)] p-3 text-xs leading-relaxed text-[var(--book-muted)]">
                    <p className="font-semibold text-[var(--book-ink)]">Quick guide</p>
                    <ul className="mt-1 list-disc space-y-0.5 pl-4">
                      <li>
                        <span className="font-medium text-[var(--book-ink)]">Plan</span> — ongoing membership
                      </li>
                      <li>
                        <span className="font-medium text-[var(--book-ink)]">Package</span> — prepaid visits
                      </li>
                      <li>
                        <span className="font-medium text-[var(--book-ink)]">Loyalty</span> — free points (Points tab)
                      </li>
                    </ul>
                    <a
                      href={`/book/${tenantSlug}/memberships`}
                      className="mt-2 inline-block font-semibold text-[var(--book-moss)] underline-offset-2 hover:underline"
                    >
                      Full comparison
                    </a>
                  </div>
                  <div>
                    <p className="text-sm font-semibold text-[var(--book-ink)]">Your memberships</p>
                    {dashboard.memberships.length === 0 ? (
                      <p className="mt-1 text-sm text-[var(--book-muted)]">No active membership yet.</p>
                    ) : (
                      <ul className="mt-2 space-y-2">
                        {dashboard.memberships.map((m) => (
                          <li key={m.id} className="rounded-lg border border-[var(--book-line)] px-3 py-2 text-sm">
                            <p className="font-medium">{m.plan_name}</p>
                            <p className="text-[var(--book-muted)]">
                              {m.status}
                              {m.current_period_ends_at
                                ? ` · renews ${new Date(m.current_period_ends_at).toLocaleDateString()}`
                                : ''}
                            </p>
                          </li>
                        ))}
                      </ul>
                    )}
                  </div>
                  <div>
                    <p className="text-sm font-semibold text-[var(--book-ink)]">Your packages</p>
                    {dashboard.packages.length === 0 ? (
                      <p className="mt-1 text-sm text-[var(--book-muted)]">No package balance.</p>
                    ) : (
                      <ul className="mt-2 space-y-2">
                        {dashboard.packages.map((p) => (
                          <li key={p.id} className="rounded-lg border border-[var(--book-line)] px-3 py-2 text-sm">
                            <p className="font-medium">{p.name}</p>
                            <p className="text-[var(--book-muted)]">
                              {p.quantity_remaining} / {p.quantity_total} remaining
                            </p>
                          </li>
                        ))}
                      </ul>
                    )}
                  </div>
                </div>
              ) : null}

              {tab === 'shop' ? (
                <div className="space-y-4">
                  <div className="rounded-xl border border-[var(--book-line)] bg-[var(--book-wash)] p-3 text-xs leading-relaxed text-[var(--book-muted)]">
                    <p>
                      <span className="font-semibold text-[var(--book-ink)]">Plans</span> renew over time.{' '}
                      <span className="font-semibold text-[var(--book-ink)]">Packages</span> are visit bundles.
                      Loyalty points are free — they are not sold here.
                    </p>
                    <a
                      href={`/book/${tenantSlug}/memberships`}
                      className="mt-2 inline-block font-semibold text-[var(--book-moss)] underline-offset-2 hover:underline"
                    >
                      Compare options
                    </a>
                  </div>
                  <p className="text-xs text-[var(--book-muted)]">
                    Purchases settle via payment-link simulation (same pattern as Payments until live card capture ships).
                  </p>
                  <div>
                    <p className="text-sm font-semibold text-[var(--book-ink)]">Memberships</p>
                    <ul className="mt-2 space-y-2">
                      {dashboard.offers.plans.map((plan) => (
                        <li key={plan.id} className="rounded-lg border border-[var(--book-line)] px-3 py-3 text-sm">
                          <p className="font-medium">{plan.name}</p>
                          <p className="text-[var(--book-muted)]">
                            {formatMoney(plan.price_cents)}
                            {plan.joining_fee_cents > 0
                              ? ` + ${formatMoney(plan.joining_fee_cents)} joining`
                              : ''}
                          </p>
                          <button
                            type="button"
                            className="mt-2 w-full rounded-md bg-[var(--book-moss)] px-3 py-2 text-xs font-semibold text-white disabled:opacity-50"
                            disabled={busy}
                            onClick={() => void handlePurchase('plan', plan.id, plan.name)}
                          >
                            Buy / renew
                          </button>
                        </li>
                      ))}
                      {dashboard.offers.plans.length === 0 ? (
                        <p className="text-sm text-[var(--book-muted)]">No public plans yet.</p>
                      ) : null}
                    </ul>
                  </div>
                  <div>
                    <p className="text-sm font-semibold text-[var(--book-ink)]">Packages</p>
                    <ul className="mt-2 space-y-2">
                      {dashboard.offers.packages.map((pkg) => (
                        <li key={pkg.id} className="rounded-lg border border-[var(--book-line)] px-3 py-3 text-sm">
                          <p className="font-medium">{pkg.name}</p>
                          <p className="text-[var(--book-muted)]">
                            {formatMoney(pkg.price_cents)} · {pkg.included_quantity} visits
                          </p>
                          <button
                            type="button"
                            className="mt-2 w-full rounded-md bg-[var(--book-moss)] px-3 py-2 text-xs font-semibold text-white disabled:opacity-50"
                            disabled={busy}
                            onClick={() => void handlePurchase('package', pkg.id, pkg.name)}
                          >
                            Buy package
                          </button>
                        </li>
                      ))}
                      {dashboard.offers.packages.length === 0 ? (
                        <p className="text-sm text-[var(--book-muted)]">No public packages yet.</p>
                      ) : null}
                    </ul>
                  </div>
                </div>
              ) : null}

              {tab === 'gifts' ? (
                <div className="space-y-5">
                  <form onSubmit={(e) => void handleCreateGift(e)} className="space-y-3">
                    <p className="text-sm font-semibold text-[var(--book-ink)]">Gift from your package</p>
                    <select
                      className={fieldClass()}
                      value={giftPackageId}
                      onChange={(e) => setGiftPackageId(e.target.value)}
                      required
                    >
                      <option value="">Select package…</option>
                      {dashboard.packages.map((p) => (
                        <option key={p.id} value={p.id}>
                          {p.name} ({p.quantity_remaining} left)
                        </option>
                      ))}
                    </select>
                    <input
                      className={fieldClass()}
                      type="number"
                      min="1"
                      step="1"
                      value={giftQty}
                      onChange={(e) => setGiftQty(e.target.value)}
                      placeholder="Quantity"
                    />
                    <input
                      className={fieldClass()}
                      value={giftName}
                      onChange={(e) => setGiftName(e.target.value)}
                      placeholder="Recipient name (optional)"
                    />
                    <button type="submit" className={primaryBtnClass(busy)} disabled={busy}>
                      Create gift code
                    </button>
                  </form>

                  <form onSubmit={(e) => void handleClaimGift(e)} className="space-y-3">
                    <p className="text-sm font-semibold text-[var(--book-ink)]">Claim a gift</p>
                    <input
                      className={fieldClass()}
                      value={claimCode}
                      onChange={(e) => setClaimCode(e.target.value)}
                      placeholder="GIFT-XXXXXXXX"
                      required
                    />
                    <button type="submit" className={primaryBtnClass(busy)} disabled={busy}>
                      Claim gift
                    </button>
                  </form>

                  <div className="space-y-2">
                    <p className="text-sm font-semibold text-[var(--book-ink)]">Your gift codes</p>
                    {gifts.length === 0 ? (
                      <p className="text-sm text-[var(--book-muted)]">No gifts yet.</p>
                    ) : (
                      gifts.map((g) => (
                        <div key={g.id} className="rounded-lg border border-[var(--book-line)] px-3 py-2 text-sm">
                          <p className="font-mono font-semibold text-[var(--book-ink)]">{g.code}</p>
                          <p className="text-[var(--book-muted)]">
                            {g.package_name} · {g.quantity} · {g.status}
                          </p>
                        </div>
                      ))
                    )}
                  </div>
                </div>
              ) : null}

              {tab === 'refer' ? (
                <div className="space-y-5">
                  {!referral || !referral.enabled ? (
                    <p className="text-sm text-[var(--book-muted)]">
                      Referral invites are not available right now.
                    </p>
                  ) : (
                    <>
                      <div className="rounded-xl bg-[var(--book-wash)] px-4 py-4">
                        <p className="text-xs font-semibold uppercase tracking-[0.14em] text-[var(--book-moss)]">
                          {referral.heading}
                        </p>
                        <p className="mt-2 text-sm leading-relaxed text-[var(--book-ink)]">
                          {referral.message}
                        </p>
                        <p className="mt-3 text-xs text-[var(--book-muted)]">
                          You earn {referral.referrer_points} pts when a friend joins. They earn{' '}
                          {referral.referred_points} pts on their first plan or package purchase.
                        </p>
                      </div>

                      <div className="space-y-2">
                        <p className="text-sm font-semibold text-[var(--book-ink)]">Your invite code</p>
                        <p className="font-mono text-lg font-semibold tracking-wide text-[var(--book-moss)]">
                          {referral.code}
                        </p>
                        <p className="break-all text-xs text-[var(--book-muted)]">{referral.join_url}</p>
                        <div className="flex flex-col gap-2 sm:flex-row">
                          <button
                            type="button"
                            className={primaryBtnClass(false)}
                            onClick={() => void handleCopyReferralLink()}
                          >
                            Copy join link
                          </button>
                          <a
                            href={referral.whatsapp_url}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex w-full items-center justify-center rounded-md border border-[var(--book-line)] bg-white px-5 py-2.5 text-sm font-semibold text-[var(--book-ink)]"
                          >
                            Share on WhatsApp
                          </a>
                        </div>
                        {copyNotice ? (
                          <p className="text-xs text-emerald-700">{copyNotice}</p>
                        ) : null}
                        <p className="text-xs text-[var(--book-muted)]">
                          Opens WhatsApp with your message — we never access your contacts.
                        </p>
                      </div>

                      <form onSubmit={(e) => void handleSendReferralEmails(e)} className="space-y-3">
                        <p className="text-sm font-semibold text-[var(--book-ink)]">
                          Email invites (up to {referral.max_email_invites_per_send})
                        </p>
                        <textarea
                          className={fieldClass()}
                          rows={4}
                          value={inviteEmails}
                          onChange={(e) => setInviteEmails(e.target.value)}
                          placeholder="friend1@email.com, friend2@email.com"
                        />
                        <button type="submit" className={primaryBtnClass(busy)} disabled={busy}>
                          Send email invites
                        </button>
                      </form>

                      <div className="rounded-lg border border-[var(--book-line)] px-3 py-3 text-sm text-[var(--book-muted)]">
                        <p>
                          Friends joined:{' '}
                          <span className="font-semibold text-[var(--book-ink)]">
                            {referral.stats.conversions}
                          </span>
                        </p>
                        <p>
                          Emails sent:{' '}
                          <span className="font-semibold text-[var(--book-ink)]">
                            {referral.stats.emails_sent}
                          </span>
                        </p>
                      </div>
                    </>
                  )}
                </div>
              ) : null}

              <button
                type="button"
                className="w-full text-sm font-semibold text-[var(--book-muted)]"
                onClick={() =>
                  void memberLogout(tenantSlug, session.token).then(() => {
                    setSession(null);
                    setDashboard(null);
                  })
                }
              >
                Log out
              </button>
            </div>
          ) : null}

          {!loading && !session ? (
            <form
              onSubmit={(e) => void (otpSent ? handleLogin(e) : handleRequestOtp(e))}
              className="mt-8 grid gap-4"
            >
              <p className="text-sm text-[var(--book-muted)]">
                Join Our Membership Family first, then log in with your email and WhatsApp number.
                We send a one-time code to WhatsApp.
              </p>
              {tierHint ? (
                <p className="text-sm font-medium text-[var(--book-moss)]">
                  Continue to use {tierHint} pricing after login.
                </p>
              ) : null}
              <label className="block text-sm">
                <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">Email</span>
                <input
                  className={fieldClass()}
                  type="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  autoComplete="email"
                  disabled={otpSent}
                />
              </label>
              <label className="block text-sm">
                <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">WhatsApp number</span>
                <input
                  className={fieldClass()}
                  type="tel"
                  required
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="+44…"
                  autoComplete="tel"
                  disabled={otpSent}
                />
              </label>
              {otpSent ? (
                <label className="block text-sm">
                  <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">
                    WhatsApp OTP {maskedPhone ? `(sent to ${maskedPhone})` : ''}
                  </span>
                  <input
                    className={fieldClass()}
                    inputMode="numeric"
                    pattern="[0-9]{6}"
                    maxLength={6}
                    required
                    value={otp}
                    onChange={(e) => setOtp(e.target.value)}
                    autoComplete="one-time-code"
                    placeholder="6-digit code"
                  />
                </label>
              ) : null}
              {error ? (
                <p className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                  {error}
                </p>
              ) : null}
              {notice && !session ? (
                <p className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                  {notice}
                </p>
              ) : null}
              {notRegistered ? (
                <Link
                  href={bootstrap?.join_path || `/join/${tenantSlug}`}
                  className="text-center text-sm font-semibold text-[var(--book-moss)]"
                >
                  Not registered yet? Join Our Membership Family →
                </Link>
              ) : null}
              <button type="submit" className={primaryBtnClass(submitting)} disabled={submitting}>
                {submitting
                  ? otpSent
                    ? 'Signing in…'
                    : 'Sending code…'
                  : otpSent
                    ? 'Verify OTP & log in'
                    : 'Send WhatsApp OTP'}
              </button>
              {otpSent ? (
                <button
                  type="button"
                  className="text-sm font-semibold text-[var(--book-moss)]"
                  onClick={() => {
                    setOtpSent(false);
                    setOtp('');
                    setNotice(null);
                  }}
                >
                  Use a different email / number
                </button>
              ) : null}
              <div className="rounded-xl border border-[var(--book-line)] px-4 py-3 text-sm text-[var(--book-muted)]">
                <p className="font-semibold text-[var(--book-ink)]">Install this app</p>
                <p className="mt-1">{installHint}</p>
              </div>
              <Link
                href={bootstrap?.join_path || `/join/${tenantSlug}`}
                className="text-center text-sm text-[var(--book-muted)] underline"
              >
                New here? Join Our Membership Family
              </Link>
            </form>
          ) : null}
        </div>

        {scheduleOpen ? (
          <div
            className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-label="Schedule next visit"
          >
            <div className="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl border border-[var(--book-line)] bg-white p-5 shadow-xl">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <h2 className="text-lg font-semibold text-[var(--book-ink)]">Schedule next visit</h2>
                  <p className="mt-1 text-sm text-[var(--book-muted)]">
                    Pick a time that works for you.
                  </p>
                </div>
                <button
                  type="button"
                  className="text-sm font-semibold text-[var(--book-muted)]"
                  onClick={() => setScheduleOpen(false)}
                >
                  Close
                </button>
              </div>
              <form onSubmit={(e) => void handleScheduleNextVisit(e)} className="mt-4 grid gap-3">
                <label className="block text-sm">
                  <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">Date & time</span>
                  <input
                    className={fieldClass()}
                    type="datetime-local"
                    required
                    value={scheduleStartsAt}
                    onChange={(e) => setScheduleStartsAt(e.target.value)}
                  />
                </label>
                <label className="block text-sm">
                  <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">Location</span>
                  <select
                    className={fieldClass()}
                    required
                    value={scheduleLocationId}
                    onChange={(e) => setScheduleLocationId(e.target.value)}
                  >
                    <option value="">Select…</option>
                    {(scheduleCatalog?.locations ?? bootstrap?.locations ?? []).map((loc) => (
                      <option key={loc.id} value={loc.id}>
                        {loc.name}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="block text-sm">
                  <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">Team member</span>
                  <select
                    className={fieldClass()}
                    required
                    value={scheduleTeamMemberId}
                    onChange={(e) => setScheduleTeamMemberId(e.target.value)}
                  >
                    <option value="">Select…</option>
                    {(scheduleCatalog?.providers ?? []).map((p) => (
                      <option key={p.id} value={p.id}>
                        {p.display_name}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="block text-sm">
                  <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">Service</span>
                  <select
                    className={fieldClass()}
                    required
                    value={scheduleServiceId}
                    onChange={(e) => setScheduleServiceId(e.target.value)}
                  >
                    <option value="">Select…</option>
                    {(scheduleCatalog?.services ?? []).map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.name}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="block text-sm">
                  <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">Notes (optional)</span>
                  <textarea
                    className={fieldClass()}
                    rows={2}
                    value={scheduleNotes}
                    onChange={(e) => setScheduleNotes(e.target.value)}
                  />
                </label>
                <button type="submit" className={primaryBtnClass(scheduling)} disabled={scheduling}>
                  {scheduling ? 'Scheduling…' : 'Confirm next visit'}
                </button>
              </form>
            </div>
          </div>
        ) : null}

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

export default function MemberPortalPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-screen items-center justify-center text-sm text-zinc-500">
          Loading…
        </div>
      }
    >
      <MemberPortalInner />
    </Suspense>
  );
}
