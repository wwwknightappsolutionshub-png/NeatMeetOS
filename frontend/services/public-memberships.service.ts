import { api } from '@/lib/api-client';

export interface MembershipEducationCard {
  key: string;
  title: string;
  summary: string;
  best_for: string;
  how_it_works: string;
}

export interface MembershipComparisonRow {
  aspect: string;
  plan: string;
  package: string;
  loyalty: string;
}

export interface PublicMembershipPlanOffer {
  id: string;
  name: string;
  description: string | null;
  price_cents: number;
  billing_frequency: string;
  joining_fee_cents: number;
  included_wallet_credit_cents: number;
  included_loyalty_points: number;
  best_for: string;
}

export interface PublicMembershipPackageOffer {
  id: string;
  name: string;
  description: string | null;
  price_cents: number;
  included_quantity: number;
  expiry_days: number | null;
  best_for: string;
}

export interface PublicMembershipLanding {
  tenant: {
    name: string;
    slug: string;
    branding: {
      brand_display_name?: string | null;
      primary_color?: string | null;
      logo_url?: string | null;
    };
  };
  paths: {
    book: string;
    join: string;
    member: string;
  };
  education: MembershipEducationCard[];
  comparison: MembershipComparisonRow[];
  offers: {
    plans: PublicMembershipPlanOffer[];
    packages: PublicMembershipPackageOffer[];
  };
  loyalty: {
    redemption_enabled: boolean;
    points_per_redemption_block: number;
    value_cents_per_block: number;
    crm_join_signup_points: number;
  };
}

function publicOpts(tenantSlug: string) {
  return {
    auth: false as const,
    tenant: false as const,
    headers: { 'X-Tenant-Slug': tenantSlug },
  };
}

export async function fetchPublicMembershipLanding(
  tenantSlug: string,
): Promise<PublicMembershipLanding> {
  return api<PublicMembershipLanding>('/book/memberships', publicOpts(tenantSlug));
}

export function formatMoneyCents(cents: number): string {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'GBP' }).format(cents / 100);
}
