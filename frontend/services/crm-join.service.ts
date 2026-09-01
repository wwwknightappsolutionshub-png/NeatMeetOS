import { api } from '@/lib/api-client';
import { getTurnstileToken, withTurnstileToken } from '@/lib/turnstile';

export interface CrmJoinMembershipOffer {
  id: string;
  name: string;
  description: string | null;
  billing_frequency: string | null;
  price_cents: number;
  joining_fee_cents: number | null;
  included_wallet_credit_cents: number | null;
  included_loyalty_points: number | null;
  included_entitlement_quantity: number | null;
}

export interface CrmJoinPackageOffer {
  id: string;
  name: string;
  description: string | null;
  price_cents: number;
  included_quantity: number;
  expiry_days: number | null;
}

export interface CrmJoinLoyaltyOffer {
  enabled: boolean;
  headline: string;
  description: string;
  points_per_redemption_block: number;
  value_cents_per_block: number;
}

export interface CrmJoinBootstrap {
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
  locations: { id: string; name: string }[];
  terms_url: string;
  offers: {
    memberships: CrmJoinMembershipOffer[];
    packages: CrmJoinPackageOffer[];
    loyalty: CrmJoinLoyaltyOffer | null;
  };
}

export interface CrmJoinResult {
  client_id: string;
  created: boolean;
  message: string;
  member_path?: string;
  lucky_position: number;
  lucky_cap: number;
  total_customer_count: number;
  lucky_eligible: boolean;
}

function publicOpts(tenantSlug: string, init?: RequestInit) {
  return {
    auth: false as const,
    tenant: false as const,
    headers: { 'X-Tenant-Slug': tenantSlug },
    ...init,
  };
}

export async function fetchCrmJoinBootstrap(
  tenantSlug: string,
  locationId?: string,
): Promise<CrmJoinBootstrap> {
  const q = locationId ? `?location_id=${encodeURIComponent(locationId)}` : '';
  return api<CrmJoinBootstrap>(`/join/bootstrap${q}`, publicOpts(tenantSlug));
}

export async function submitCrmJoin(
  tenantSlug: string,
  payload: {
    preferred_name: string;
    whatsapp_number: string;
    email: string;
    next_visit_date: string;
    accept_terms: boolean;
    location_id?: string;
    special_date?: string;
    special_event_label?: string;
    referral_code?: string;
    date_of_birth?: string;
  },
): Promise<CrmJoinResult> {
  const turnstile_token = await getTurnstileToken();
  return api<CrmJoinResult>('/join/clients', {
    ...publicOpts(tenantSlug, {
      method: 'POST',
      body: JSON.stringify(withTurnstileToken({ ...payload }, turnstile_token)),
    }),
  });
}
