export const MEMBERSHIP_PLAN_STATUSES = ['active', 'inactive', 'archived'] as const;

export const MEMBERSHIP_BILLING_FREQUENCIES = ['weekly', 'monthly', 'quarterly', 'yearly'] as const;

export const CLIENT_MEMBERSHIP_STATUSES = ['trialing', 'active', 'paused', 'past_due', 'cancelled', 'expired'] as const;

export const WALLET_ENTRY_TYPES = [
  'manual_credit',
  'manual_debit',
  'membership_credit',
  'package_credit',
  'refund_credit',
  'pos_redemption',
  'adjustment',
] as const;

export const LOYALTY_ENTRY_TYPES = [
  'manual_award',
  'manual_deduction',
  'membership_bonus',
  'promotion',
  'pos_earn',
  'pos_redeem',
  'adjustment',
] as const;

export const CLIENT_PACKAGE_STATUSES = ['active', 'expired', 'depleted', 'cancelled'] as const;

export const PACKAGE_REDEMPTION_TYPES = [
  'manual_redeem',
  'manual_restore',
  'pos_redeem',
  'booking_redeem',
  'refund_restore',
] as const;

export type MembershipPlanStatus = (typeof MEMBERSHIP_PLAN_STATUSES)[number];

export type MembershipBillingFrequency = (typeof MEMBERSHIP_BILLING_FREQUENCIES)[number];

export type ClientMembershipStatus = (typeof CLIENT_MEMBERSHIP_STATUSES)[number];

export interface MembershipPlan {
  id: string;
  name: string;
  description?: string | null;
  status: MembershipPlanStatus | string;
  billing_frequency?: MembershipBillingFrequency | string | null;
  price_cents: number;
  joining_fee_cents?: number;
  included_wallet_credit_cents?: number;
  included_loyalty_points?: number;
  included_entitlement_quantity?: number;
  auto_renew?: boolean;
  is_public?: boolean;
  applies_to_all_locations?: boolean;
  location_ids?: string[];
}

export interface PackageProduct {
  id: string;
  name: string;
  description?: string | null;
  status: string;
  price_cents: number;
  included_quantity: number;
  expiry_days?: number | null;
  is_public?: boolean;
  service_restrictions?: Array<{
    booking_service_id: string;
    service_name?: string;
    quantity_per_redemption?: number | null;
  }>;
}

export interface ClientMembership {
  id: string;
  client_id: string;
  client_name?: string | null;
  membership_plan_id: string;
  plan_name?: string | null;
  status: ClientMembershipStatus | string;
  started_at?: string;
  current_period_ends_at?: string | null;
  next_billing_date?: string | null;
  price_cents_snapshot: number;
  included_wallet_credit_cents_snapshot?: number;
  included_loyalty_points_snapshot?: number;
  notes?: string | null;
}

export interface WalletEntry {
  id: string;
  client_id: string;
  client_name?: string | null;
  entry_type: string;
  direction: string;
  amount_cents: number;
  notes?: string | null;
  created_at?: string;
}

export interface LoyaltyEntry {
  id: string;
  client_id: string;
  client_name?: string | null;
  entry_type: string;
  direction: string;
  points: number;
  notes?: string | null;
  created_at?: string;
}

export interface ClientPackage {
  id: string;
  client_id: string;
  client_name?: string | null;
  package_product_id: string;
  package_name?: string | null;
  status: string;
  quantity_total: number;
  quantity_remaining: number;
  notes?: string | null;
}

export interface MembershipSummary {
  active_subscriptions_count: number;
  mrr_estimate_cents: number;
  wallet_liability_cents: number;
  outstanding_package_balances_count: number;
  loyalty_points_issued_total: number;
}

export function formatMoneyCents(cents: number, currency = 'GBP'): string {
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency }).format((cents ?? 0) / 100);
}
