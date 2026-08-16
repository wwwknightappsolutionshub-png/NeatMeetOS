export const PAYMENT_TRANSACTION_STATUSES = [
  'pending',
  'authorized',
  'succeeded',
  'failed',
  'cancelled',
  'refunded',
  'partially_refunded',
] as const;

export type PaymentTransactionStatus = (typeof PAYMENT_TRANSACTION_STATUSES)[number];

export const PAYMENT_TRANSACTION_TYPES = [
  'deposit',
  'sale',
  'membership',
  'gift_card',
  'refund',
  'adjustment',
] as const;

export type PaymentTransactionType = (typeof PAYMENT_TRANSACTION_TYPES)[number];

export const PAYMENT_METHOD_TYPES = [
  'cash',
  'card',
  'bank_transfer',
  'payment_link',
  'terminal',
  'other',
] as const;

export type PaymentMethodType = (typeof PAYMENT_METHOD_TYPES)[number];

export const PAYMENT_DIRECTIONS = ['inbound', 'outbound'] as const;

export type PaymentDirection = (typeof PAYMENT_DIRECTIONS)[number];

export interface PaymentAllocation {
  id: string;
  allocation_type: string;
  amount_cents: number;
  appointment_id?: string | null;
  commerce_deposit_record_id?: string | null;
  commerce_checkout_id?: string | null;
  notes?: string | null;
}

export interface PaymentRefund {
  id: string;
  payment_transaction_id: string;
  refund_transaction_id?: string | null;
  amount_cents: number;
  reason?: string | null;
  status: string;
  processed_at?: string | null;
  created_at?: string | null;
}

export interface PaymentTransaction {
  id: string;
  location_id?: string | null;
  location?: { id: string; name: string } | null;
  client_id?: string | null;
  client?: { id: string; resolved_display_name: string } | null;
  appointment_id?: string | null;
  appointment?: { id: string; booking_reference?: string | null } | null;
  team_member_id?: string | null;
  transaction_type: PaymentTransactionType;
  direction: PaymentDirection;
  status: PaymentTransactionStatus;
  amount_cents: number;
  currency: string;
  provider?: string | null;
  provider_reference?: string | null;
  external_reference?: string | null;
  payment_method_type?: PaymentMethodType | null;
  payment_method_label?: string | null;
  processed_at?: string | null;
  failed_at?: string | null;
  failure_code?: string | null;
  failure_message?: string | null;
  metadata?: Record<string, unknown> | null;
  refundable_amount_cents?: number;
  allocations?: PaymentAllocation[];
  refunds?: PaymentRefund[];
  created_at?: string | null;
  updated_at?: string | null;
}

export interface PaymentSummary {
  total_transactions: number;
  succeeded_inbound_cents: number;
  by_status: Record<string, number>;
  by_transaction_type: Record<string, number>;
  by_payment_method: Record<string, number>;
}

export interface DepositInspect {
  appointment: {
    id: string;
    deposit_status: string;
    deposit_required_cents?: number | null;
    deposit_rule_snapshot?: Record<string, unknown> | null;
  };
  deposit_contract: Record<string, unknown>;
  deposit_record?: Record<string, unknown> | null;
}

export interface TenantPaymentsSettings {
  bank_account_name: string | null;
  bank_name: string | null;
  bank_sort_code: string | null;
  bank_account_number: string | null;
  bank_iban: string | null;
  bank_reference_hint: string | null;
}

export interface ReservationPaymentDocument {
  id: string;
  appointment_id: string | null;
  client_id: string | null;
  booking_service_id: string | null;
  amount_cents: number;
  payment_method: string;
  status: 'pending_review' | 'confirmed' | 'rejected' | string;
  proof_url: string | null;
  proof_original_name: string | null;
  proof_mime: string | null;
  proof_size_bytes: number | null;
  public_token: string;
  review_note: string | null;
  reviewed_at: string | null;
  created_at: string | null;
  appointment: {
    id: string;
    booking_reference: string | null;
    starts_at: string | null;
    status: string;
    deposit_status: string | null;
    deposit_required_cents: number | null;
    client_name: string | null;
    provider_name: string | null;
    services: string[];
  } | null;
  client_name: string | null;
  service_name: string | null;
  reviewed_by_name: string | null;
}

export function formatMoneyCents(cents: number, currency = 'GBP'): string {
  const symbol = currency === 'GBP' ? '£' : currency;
  return `${symbol}${(cents / 100).toFixed(2)}`;
}
