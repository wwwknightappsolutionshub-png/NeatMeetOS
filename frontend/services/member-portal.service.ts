import { api } from '@/lib/api-client';
import { getTurnstileToken, withTurnstileToken } from '@/lib/turnstile';

export type PricingTier = 'regular' | 'membership' | 'loyalty';

export interface MemberPortalLocation {
  id: string;
  name: string;
  latitude: number | null;
  longitude: number | null;
  geofence_radius_meters: number | null;
}

export interface MemberPortalBootstrap {
  tenant: {
    name: string;
    slug: string;
    branding: {
      brand_display_name?: string | null;
      primary_color?: string | null;
      logo_url?: string | null;
      social_facebook_url?: string | null;
      social_instagram_url?: string | null;
      social_tiktok_url?: string | null;
    };
  };
  locations: MemberPortalLocation[];
  join_path: string;
  book_path: string;
  terms_url?: string;
  vapid_public_key?: string | null;
  push_enabled?: boolean;
}

export interface MemberBenefits {
  has_membership: boolean;
  loyalty_eligible: boolean;
}

export interface MemberVisitStatus {
  checked_in_today: boolean;
  last_visited_at: string | null;
  loyalty_points_balance: number | null;
  points?: number;
  already_checked_in_today?: boolean;
  prompt_next_visit?: boolean;
  next_visit_appointment_id?: string | null;
  open_visit?: MemberOpenVisit | null;
  visit?: MemberOpenVisit;
}

export interface MemberOpenVisit {
  id: string;
  client_id?: string;
  location_id?: string | null;
  checked_in_at?: string | null;
  checked_out_at?: string | null;
  source?: string | null;
  loyalty_points_awarded?: number;
  next_visit_appointment_id?: string | null;
}

export interface MemberSession {
  token: string;
  expires_at: string;
  client: {
    id: string;
    first_name: string | null;
    last_name: string | null;
    display_name?: string | null;
    email: string | null;
    phone: string | null;
  };
  benefits: MemberBenefits;
  checked_in_today?: boolean;
  open_visit?: MemberOpenVisit | null;
  last_visited_at?: string | null;
  loyalty_points_balance?: number | null;
}

export interface MemberDashboard {
  client: MemberSession['client'];
  benefits: MemberBenefits;
  checked_in_today: boolean;
  open_visit?: MemberOpenVisit | null;
  last_visited_at: string | null;
  loyalty_points_balance: number;
  wallet_balance_cents: number;
  memberships: Array<{
    id: string;
    status: string;
    plan_name: string | null;
    current_period_ends_at: string | null;
    next_billing_date: string | null;
  }>;
  packages: Array<{
    id: string;
    name: string | null;
    quantity_remaining: number;
    quantity_total: number;
    expires_at: string | null;
    source: string;
  }>;
  upcoming_appointments: Array<{
    id: string;
    starts_at: string | null;
    ends_at: string | null;
    status: string;
    booking_reference: string | null;
    location_name: string | null;
    provider_name: string | null;
    services: string[];
  }>;
  offers: MemberOffers;
}

export interface MemberOffers {
  plans: Array<{
    id: string;
    name: string;
    description: string | null;
    price_cents: number;
    billing_frequency: string | null;
    joining_fee_cents: number;
  }>;
  packages: Array<{
    id: string;
    name: string;
    description: string | null;
    price_cents: number;
    included_quantity: number;
  }>;
}

export interface MemberVisitRow {
  id: string;
  checked_in_at: string | null;
  checked_out_at?: string | null;
  source: string | null;
  loyalty_points_awarded: number;
  location: { id: string; name: string } | null;
}

export interface MemberLoyaltyEntry {
  id: string;
  entry_type: string;
  direction: string;
  points: number;
  effective_at: string | null;
  notes: string | null;
}

export interface MemberGift {
  id: string;
  code: string;
  status: string;
  quantity: number;
  package_name: string | null;
  recipient_name: string | null;
  recipient_email: string | null;
  expires_at: string | null;
  claimed_at: string | null;
  from_client_id: string;
  claimed_by_client_id: string | null;
}

const storageKey = (slug: string) => `neatmeet_member_${slug}`;

export function loadMemberSession(tenantSlug: string): MemberSession | null {
  if (typeof window === 'undefined') return null;
  try {
    const raw = localStorage.getItem(storageKey(tenantSlug));
    if (!raw) return null;
    return JSON.parse(raw) as MemberSession;
  } catch {
    return null;
  }
}

export function saveMemberSession(tenantSlug: string, session: MemberSession): void {
  localStorage.setItem(storageKey(tenantSlug), JSON.stringify(session));
}

export function clearMemberSession(tenantSlug: string): void {
  localStorage.removeItem(storageKey(tenantSlug));
}

function publicOpts(tenantSlug: string, init?: RequestInit) {
  return {
    auth: false as const,
    tenant: false as const,
    headers: { 'X-Tenant-Slug': tenantSlug },
    ...init,
  };
}

function memberAuthHeaders(tenantSlug: string, token: string): HeadersInit {
  return {
    'X-Tenant-Slug': tenantSlug,
    Authorization: `Bearer ${token}`,
  };
}

function memberGet<T>(tenantSlug: string, token: string, path: string): Promise<T> {
  return api<T>(path, {
    ...publicOpts(tenantSlug, {
      headers: memberAuthHeaders(tenantSlug, token),
    }),
  });
}

function memberMutate<T>(
  tenantSlug: string,
  token: string,
  path: string,
  method: string,
  body?: unknown,
): Promise<T> {
  return api<T>(path, {
    ...publicOpts(tenantSlug, {
      method,
      headers: memberAuthHeaders(tenantSlug, token),
      body: body !== undefined ? JSON.stringify(body) : undefined,
    }),
  });
}

export async function fetchMemberBootstrap(tenantSlug: string): Promise<MemberPortalBootstrap> {
  return api<MemberPortalBootstrap>('/member/bootstrap', publicOpts(tenantSlug));
}

export async function memberRequestOtp(
  tenantSlug: string,
  email: string,
  phone: string,
): Promise<{ sent: boolean; expires_in_seconds: number; masked_phone: string }> {
  const turnstile_token = await getTurnstileToken();
  return api('/member/login/request-otp', {
    ...publicOpts(tenantSlug, {
      method: 'POST',
      body: JSON.stringify(withTurnstileToken({ email, phone }, turnstile_token)),
    }),
  });
}

export async function memberLogin(
  tenantSlug: string,
  email: string,
  phone: string,
  otp: string,
): Promise<MemberSession> {
  const turnstile_token = await getTurnstileToken();
  const data = await api<MemberSession>('/member/login', {
    ...publicOpts(tenantSlug, {
      method: 'POST',
      body: JSON.stringify(withTurnstileToken({ email, phone, otp }, turnstile_token)),
    }),
  });
  saveMemberSession(tenantSlug, data);
  return data;
}

export async function fetchMemberMe(
  tenantSlug: string,
  token: string,
): Promise<{
  client: MemberSession['client'];
  benefits: MemberBenefits;
  checked_in_today?: boolean;
  open_visit?: MemberOpenVisit | null;
  last_visited_at?: string | null;
  loyalty_points_balance?: number | null;
}> {
  return memberGet(tenantSlug, token, '/member/me');
}

export async function fetchMemberDashboard(
  tenantSlug: string,
  token: string,
): Promise<MemberDashboard> {
  return memberGet(tenantSlug, token, '/member/dashboard');
}

export async function fetchMemberVisits(
  tenantSlug: string,
  token: string,
): Promise<MemberVisitRow[]> {
  return memberGet(tenantSlug, token, '/member/visits');
}

export async function fetchMemberLoyalty(
  tenantSlug: string,
  token: string,
): Promise<{ balance: number; entries: MemberLoyaltyEntry[] }> {
  return memberGet(tenantSlug, token, '/member/loyalty');
}

export async function fetchMemberOffers(
  tenantSlug: string,
  token: string,
): Promise<MemberOffers> {
  return memberGet(tenantSlug, token, '/member/offers');
}

export async function memberPurchase(
  tenantSlug: string,
  token: string,
  offerType: 'plan' | 'package',
  offerId: string,
): Promise<{ amount_cents: number; offer_type: string; status: string }> {
  return memberMutate(tenantSlug, token, '/member/purchases', 'POST', {
    offer_type: offerType,
    offer_id: offerId,
  });
}

export async function fetchMemberGifts(
  tenantSlug: string,
  token: string,
): Promise<MemberGift[]> {
  return memberGet(tenantSlug, token, '/member/gifts');
}

export async function memberCreateGift(
  tenantSlug: string,
  token: string,
  payload: {
    client_package_id: string;
    quantity?: number;
    recipient_name?: string;
    recipient_email?: string;
  },
): Promise<MemberGift> {
  return memberMutate(tenantSlug, token, '/member/gifts', 'POST', payload);
}

export async function memberClaimGift(
  tenantSlug: string,
  token: string,
  code: string,
): Promise<MemberGift> {
  return memberMutate(tenantSlug, token, '/member/gifts/claim', 'POST', { code });
}

export async function memberSubscribePush(
  tenantSlug: string,
  token: string,
  subscription: PushSubscriptionJSON,
): Promise<{ id: string; subscribed: boolean }> {
  return memberMutate(tenantSlug, token, '/member/push-subscriptions', 'POST', {
    endpoint: subscription.endpoint,
    keys: subscription.keys,
  });
}

export async function memberUnsubscribePush(
  tenantSlug: string,
  token: string,
  endpoint: string,
): Promise<void> {
  await memberMutate(tenantSlug, token, '/member/push-subscriptions', 'DELETE', { endpoint });
}

export interface MemberNotice {
  id: string;
  type: string;
  title: string;
  body: string;
  href?: string | null;
  data?: Record<string, unknown> | null;
  read_at?: string | null;
  created_at?: string | null;
}

export async function memberFetchNotices(
  tenantSlug: string,
  token: string,
): Promise<{ items: MemberNotice[]; unread_count: number }> {
  return memberGet(tenantSlug, token, '/member/notices');
}

export async function memberMarkNoticeRead(
  tenantSlug: string,
  token: string,
  noticeId: string,
): Promise<MemberNotice> {
  return memberMutate(tenantSlug, token, `/member/notices/${noticeId}/read`, 'POST');
}

export interface MemberThreadMessage {
  id: string;
  client_id: string;
  author_user_id: string | null;
  direction: 'inbound' | 'outbound' | string;
  channel: string;
  subject: string | null;
  body: string;
  whatsapp_deeplink: string | null;
  metadata: Record<string, unknown> | null;
  read_at: string | null;
  created_at: string | null;
}

export interface MemberMessagesPayload {
  notices: MemberNotice[];
  unread_notices: number;
  thread: MemberThreadMessage[];
  unread_thread: number;
  unread_total: number;
}

export async function memberFetchMessages(
  tenantSlug: string,
  token: string,
): Promise<MemberMessagesPayload> {
  return memberGet(tenantSlug, token, '/member/messages');
}

export async function memberSendThreadMessage(
  tenantSlug: string,
  token: string,
  body: string,
): Promise<MemberThreadMessage> {
  return memberMutate(tenantSlug, token, '/member/messages/threads', 'POST', { body });
}

export async function memberMarkThreadRead(
  tenantSlug: string,
  token: string,
): Promise<{ updated: number }> {
  return memberMutate(tenantSlug, token, '/member/messages/threads/read', 'POST');
}

export async function memberCheckIn(
  tenantSlug: string,
  token: string,
  locationId?: string,
): Promise<MemberVisitStatus> {
  return memberMutate(tenantSlug, token, '/member/check-in', 'POST', locationId ? { location_id: locationId } : {});
}

export async function memberCheckOut(
  tenantSlug: string,
  token: string,
): Promise<MemberVisitStatus> {
  return memberMutate(tenantSlug, token, '/member/check-out', 'POST', {});
}

export async function memberVisitStatus(
  tenantSlug: string,
  token: string,
): Promise<MemberVisitStatus> {
  return memberGet(tenantSlug, token, '/member/visit-status');
}

export async function memberLogout(tenantSlug: string, token: string): Promise<void> {
  try {
    await memberMutate(tenantSlug, token, '/member/logout', 'POST');
  } finally {
    clearMemberSession(tenantSlug);
  }
}

export function memberLoginUrl(
  tenantSlug: string,
  opts?: { next?: string; tier?: PricingTier },
): string {
  const q = new URLSearchParams();
  if (opts?.next) q.set('next', opts.next);
  if (opts?.tier) q.set('tier', opts.tier);
  const qs = q.toString();
  return `/member/${tenantSlug}${qs ? `?${qs}` : ''}`;
}

export function crmJoinUrl(tenantSlug: string, ref?: string): string {
  const q = ref ? `?ref=${encodeURIComponent(ref)}` : '';
  return `/book/${tenantSlug}${q}`;
}

export interface MemberReferralPayload {
  enabled: boolean;
  code: string;
  heading: string;
  message: string;
  join_url: string;
  book_url: string;
  whatsapp_url: string;
  referrer_points: number;
  referred_points: number;
  max_email_invites_per_send: number;
  stats: {
    conversions: number;
    pending_referred_bonus: number;
    emails_sent: number;
  };
}

export async function fetchReferral(
  tenantSlug: string,
  token: string,
): Promise<MemberReferralPayload> {
  return memberGet(tenantSlug, token, '/member/referral');
}

export async function sendReferralEmails(
  tenantSlug: string,
  token: string,
  emails: string[],
): Promise<{
  sent: number;
  skipped: number;
  results: Array<{ email: string; status: string; error: string | null }>;
}> {
  return memberMutate(tenantSlug, token, '/member/referral/email-invites', 'POST', { emails });
}

export function formatMoney(cents: number): string {
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(
    cents / 100,
  );
}

export async function registerMemberServiceWorker(): Promise<ServiceWorkerRegistration | null> {
  if (typeof window === 'undefined' || !('serviceWorker' in navigator)) return null;
  try {
    // Older builds registered member-sw at scope `/`, which intercepted the whole
    // site and surfaced offline 503s (including from email deep links). Clean those up.
    const registrations = await navigator.serviceWorker.getRegistrations();
    await Promise.all(
      registrations.map(async (registration) => {
        const scriptUrl = registration.active?.scriptURL
          ?? registration.waiting?.scriptURL
          ?? registration.installing?.scriptURL
          ?? '';
        if (!scriptUrl.includes('member-sw.js')) return;
        const scopePath = new URL(registration.scope).pathname;
        if (scopePath === '/' || scopePath === '') {
          await registration.unregister();
        }
      }),
    );

    return await navigator.serviceWorker.register('/member-sw.js', { scope: '/member/' });
  } catch {
    return null;
  }
}
