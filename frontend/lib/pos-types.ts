export const CHECKOUT_STATUSES = [
  'draft',
  'open',
  'completed',
  'voided',
  'partially_refunded',
  'fully_refunded',
] as const;

export type CheckoutStatus = (typeof CHECKOUT_STATUSES)[number];

export const CHECKOUT_SOURCES = ['manual', 'appointment_import', 'mixed'] as const;

export type CheckoutSource = (typeof CHECKOUT_SOURCES)[number];

export const POS_LINE_TYPES = [
  'appointment_service',
  'retail_product',
  'deposit_credit',
  'discount',
  'tip',
  'tax',
  'gift_card_sale',
] as const;

export type PosLineType = (typeof POS_LINE_TYPES)[number];

export const POS_TENDER_TYPES = ['cash', 'card_manual', 'payment_link'] as const;

export const CHECKOUT_LINE_RETURN_STATUSES = ['not_returned', 'partially_returned', 'returned'] as const;

export const DISCOUNT_TYPES = ['manual_amount', 'manual_percent', 'promo', 'manager_override'] as const;

export const RECEIPT_DELIVERY_METHODS = ['print', 'email', 'sms', 'manual'] as const;

export const GIFT_CARD_STATUSES = ['active', 'redeemed', 'expired', 'voided'] as const;

export type PosTenderType = (typeof POS_TENDER_TYPES)[number];

export interface CheckoutClientSummary {
  id: string;
  first_name: string;
  last_name: string;
  display_name: string;
}

export interface CheckoutLine {
  id: string;
  line_type: PosLineType | string;
  description: string;
  quantity: number;
  unit_price_cents: number;
  discount_cents: number;
  discount_type?: string | null;
  discount_reason?: string | null;
  returned_quantity?: number;
  returned_subtotal_cents?: number;
  return_status?: string;
  line_total_cents: number;
  reference_type?: string | null;
  reference_id?: string | null;
  pricing_snapshot?: Record<string, unknown>;
  sort_order?: number;
  membership_application_type?: string | null;
  client_package_id?: string | null;
  client_package_redemption_id?: string | null;
  covered_quantity?: number | null;
  covered_amount_cents?: number;
}

export interface CheckoutAppointmentLink {
  id: string;
  role: string;
  imported_subtotal_cents?: number | null;
  booking_reference?: string | null;
  status?: string | null;
  billing_settlement_status?: string | null;
}

export interface DepositCreditAvailability {
  deposit_record_id: string;
  appointment_id: string;
  available_cents: number;
  collected_cents: number;
}

export interface Checkout {
  id: string;
  checkout_number: string;
  status: CheckoutStatus;
  source?: CheckoutSource | null;
  currency: string;
  client?: CheckoutClientSummary | null;
  location?: { id: string; name: string } | null;
  cashier?: { id: string; display_name: string } | null;
  linked_appointments?: CheckoutAppointmentLink[];
  lines?: CheckoutLine[];
  subtotal_cents: number;
  discount_cents: number;
  tax_cents: number;
  tip_cents: number;
  deposit_credit_cents: number;
  total_cents: number;
  amount_paid_cents: number;
  amount_due_cents: number;
  refunded_total_cents?: number;
  gift_card_redemption_cents?: number;
  wallet_credit_cents?: number;
  loyalty_discount_cents?: number;
  loyalty_points_redeemed?: number;
  package_covered_cents?: number;
  reopened_at?: string | null;
  reopen_reason?: string | null;
  receipt_last_sent_at?: string | null;
  available_deposit_credit?: DepositCreditAvailability[];
  notes?: string | null;
  completed_at?: string | null;
  voided_at?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface CheckoutListItem {
  id: string;
  checkout_number: string;
  status: CheckoutStatus;
  source?: CheckoutSource | null;
  client_name?: string | null;
  location_name?: string | null;
  cashier_name?: string | null;
  total_cents: number;
  amount_due_cents: number;
  completed_at?: string | null;
  created_at?: string;
}

export interface CheckoutRefund {
  id: string;
  amount_cents: number;
  reason?: string | null;
  notes?: string | null;
  source?: string | null;
  status: string;
  created_at?: string;
}

export interface CheckoutReceipt {
  id: string;
  receipt_number: string;
  delivery_method?: string | null;
  delivery_status: string;
  delivery_target?: string | null;
  sent_at?: string | null;
  created_at?: string;
}

export interface EligibleAppointment {
  id: string;
  booking_reference: string;
  client_name?: string | null;
  status: string;
  starts_at?: string;
  service_count: number;
  import_subtotal_cents: number;
  checkout_eligible: boolean;
  ineligibility_reason?: string | null;
  deposit_available_cents?: number | null;
}

export interface PosCatalogService {
  id: string;
  name: string;
  duration_minutes: number;
  price_cents?: number | null;
  deposit_required?: boolean;
}

export interface PosCatalogRetailItem {
  id: string;
  name: string;
  sku?: string | null;
  retail_price_cents?: number | null;
}

export function formatMoneyCents(cents: number, currency = 'GBP'): string {
  const amount = (cents ?? 0) / 100;
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency }).format(amount);
}

export function isCheckoutEditable(status: CheckoutStatus | string): boolean {
  return status === 'draft' || status === 'open';
}

export function isCheckoutTerminal(status: CheckoutStatus | string): boolean {
  return status === 'completed' || status === 'voided' || status === 'fully_refunded';
}

export function checkoutStatusLabel(status: CheckoutStatus | string): string {
  return status.replace(/_/g, ' ');
}

export interface CheckoutMembershipOptions {
  checkout_id: string;
  client_id: string | null;
  wallet_balance_cents: number;
  wallet_credit_applied_cents: number;
  loyalty_points_balance: number;
  loyalty_redeemable_value_cents: number;
  loyalty_points_redeemed: number;
  loyalty_discount_cents: number;
  package_covered_cents: number;
  loyalty_redemption_rule: {
    is_enabled: boolean;
    points_per_block: number;
    value_cents_per_block: number;
  };
  service_lines: Array<{
    line_id: string;
    description: string;
    line_total_cents: number;
    booking_service_id?: string | null;
    applied_package?: {
      client_package_id: string;
      covered_amount_cents: number;
    } | null;
    reserved_package?: {
      client_package_id: string;
      covered_amount_cents: number;
    } | null;
    eligible_packages: import('@/lib/memberships-types').ClientPackage[];
  }>;
}
