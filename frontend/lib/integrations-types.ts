// Module 13A + 13B — Provider Integrations (simulation-first + live adapter foundation)

export const PROVIDER_CATEGORIES = [
  'email',
  'sms',
  'payment_gateway',
  'gift_card',
  'generic_webhook',
] as const;

export const PROVIDER_DRIVERS = [
  'simulation',
  'mailgun',
  'twilio',
  'stripe',
  'manual',
  'custom',
] as const;

export const PROVIDER_ACCOUNT_STATUSES = [
  'active',
  'inactive',
  'archived',
  'test_only',
] as const;

export const PROVIDER_ATTEMPT_STATUSES = [
  'pending',
  'processing',
  'sent',
  'delivered',
  'failed',
  'cancelled',
  'suppressed',
] as const;

export const PROVIDER_ATTEMPT_DIRECTIONS = ['outbound', 'inbound'] as const;

export const PROVIDER_WEBHOOK_PROCESSING_STATUSES = [
  'received',
  'processed',
  'ignored',
  'failed',
] as const;

export const PROVIDER_SOURCE_DOMAINS = [
  'notifications',
  'marketing',
  'payments',
  'pos',
] as const;

export const PROVIDER_SOURCE_TYPES = [
  'notification_message',
  'marketing_message',
  'payment_transaction',
  'payment_link',
] as const;

export type ProviderCategory = (typeof PROVIDER_CATEGORIES)[number];
export type ProviderDriver = (typeof PROVIDER_DRIVERS)[number];
export type ProviderAccountStatus = (typeof PROVIDER_ACCOUNT_STATUSES)[number];
export type ProviderAttemptStatus = (typeof PROVIDER_ATTEMPT_STATUSES)[number];
export type ProviderAttemptDirection = (typeof PROVIDER_ATTEMPT_DIRECTIONS)[number];
export type ProviderWebhookProcessingStatus = (typeof PROVIDER_WEBHOOK_PROCESSING_STATUSES)[number];
export type ProviderSourceDomain = (typeof PROVIDER_SOURCE_DOMAINS)[number];
export type ProviderSourceType = (typeof PROVIDER_SOURCE_TYPES)[number];

export type BadgeTone = 'green' | 'amber' | 'red' | 'zinc';

// ── Models (mirror Step 23A JsonResources) ───────────────────────────────────

export interface ProviderAccountActor {
  id: string | null;
  name?: string | null;
}

export interface ProviderAccount {
  id: string;
  name: string;
  category: ProviderCategory | string;
  driver: ProviderDriver | string;
  status: ProviderAccountStatus | string;
  is_default: boolean;
  configuration: Record<string, unknown>;
  has_credentials: boolean;
  config_summary?: Record<string, unknown>;
  from_name?: string | null;
  from_address?: string | null;
  reply_to?: string | null;
  phone_number?: string | null;
  metadata: Record<string, unknown>;
  last_tested_at?: string | null;
  last_test_result?: string | null;
  archived_at?: string | null;
  created_by?: ProviderAccountActor | null;
  updated_by?: ProviderAccountActor | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface ProviderDeliveryAttempt {
  id: string;
  provider_account_id?: string | null;
  category: ProviderCategory | string;
  source_domain: ProviderSourceDomain | string;
  source_type: ProviderSourceType | string;
  source_id?: string | null;
  related_client_id?: string | null;
  related_appointment_id?: string | null;
  related_payment_transaction_id?: string | null;
  direction: ProviderAttemptDirection | string;
  purpose?: string | null;
  recipient_address?: string | null;
  recipient_phone?: string | null;
  subject?: string | null;
  payload: Record<string, unknown>;
  provider_reference?: string | null;
  idempotency_key?: string | null;
  status: ProviderAttemptStatus | string;
  failure_code?: string | null;
  failure_message?: string | null;
  requested_at?: string | null;
  sent_at?: string | null;
  delivered_at?: string | null;
  failed_at?: string | null;
  metadata: Record<string, unknown>;
  provider_account?: ProviderAccount | null;
  related_client?: { id: string | null; name?: string | null } | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface ProviderWebhookEvent {
  id: string;
  tenant_id?: string | null;
  provider_account_id?: string | null;
  category?: ProviderCategory | string | null;
  driver: ProviderDriver | string;
  event_type: string;
  external_event_id?: string | null;
  received_at?: string | null;
  processed_at?: string | null;
  processing_status: ProviderWebhookProcessingStatus | string;
  processing_error?: string | null;
  signature_valid?: boolean | null;
  payload: Record<string, unknown>;
  headers: Record<string, unknown>;
  resolved_source_domain?: string | null;
  resolved_source_type?: string | null;
  resolved_source_id?: string | null;
  metadata: Record<string, unknown>;
  provider_account?: ProviderAccount | null;
  created_at?: string | null;
  updated_at?: string | null;
}

// ── Filters / payloads ───────────────────────────────────────────────────────

export interface ProviderAccountFilters {
  category?: string;
  status?: string;
  archived?: boolean;
}

export interface ProviderAccountPayload {
  name: string;
  category: string;
  driver: string;
  status?: string;
  is_default?: boolean;
  configuration?: Record<string, unknown>;
  credentials?: Record<string, unknown>;
  webhook_secret?: string;
  from_name?: string;
  from_address?: string;
  reply_to?: string;
  phone_number?: string;
  metadata?: Record<string, unknown>;
}

export interface ProviderAttemptFilters {
  category?: string;
  source_domain?: string;
  status?: string;
  provider_account_id?: string;
  client_id?: string;
  from?: string;
  to?: string;
}

export interface ProviderWebhookEventFilters {
  driver?: string;
  category?: string;
  processing_status?: string;
  from?: string;
  to?: string;
}

/** Backend list endpoints return at most this many rows (no pagination). */
export const INTEGRATIONS_LIST_WINDOW = 200;

export interface IntegrationsOverviewSummary {
  total_accounts: number;
  active_accounts: number;
  default_accounts: number;
  recent_attempts: number;
  failed_attempts: number;
  received_webhook_events: number;
  /** True when attempt/event list hit the backend window cap. */
  attempts_truncated?: boolean;
  events_truncated?: boolean;
}

// ── Helpers ──────────────────────────────────────────────────────────────────

export function humanizeToken(value: string | null | undefined): string {
  if (!value) return '—';
  return value
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase());
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) return '—';
  try {
    return new Intl.DateTimeFormat(undefined, {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(value));
  } catch {
    return value;
  }
}

export function categoryLabel(category: string | null | undefined): string {
  const map: Record<string, string> = {
    email: 'Email',
    sms: 'SMS',
    payment_gateway: 'Payment gateway',
    gift_card: 'Gift card',
    generic_webhook: 'Generic webhook',
  };
  if (!category) return '—';
  return map[category] ?? humanizeToken(category);
}

export function driverLabel(driver: string | null | undefined): string {
  const map: Record<string, string> = {
    simulation: 'Simulation',
    mailgun: 'Mailgun',
    twilio: 'Twilio',
    stripe: 'Stripe',
    manual: 'Manual',
    custom: 'Custom',
  };
  if (!driver) return '—';
  return map[driver] ?? humanizeToken(driver);
}

export function accountStatusLabel(status: string | null | undefined): string {
  if (!status) return '—';
  return humanizeToken(status);
}

export function accountStatusTone(status: string | null | undefined): BadgeTone {
  switch (status) {
    case 'active':
      return 'green';
    case 'test_only':
      return 'amber';
    case 'inactive':
      return 'zinc';
    case 'archived':
      return 'red';
    default:
      return 'zinc';
  }
}

export function attemptStatusLabel(status: string | null | undefined): string {
  if (!status) return '—';
  return humanizeToken(status);
}

export function attemptStatusTone(status: string | null | undefined): BadgeTone {
  switch (status) {
    case 'delivered':
    case 'sent':
      return 'green';
    case 'pending':
    case 'processing':
      return 'amber';
    case 'failed':
    case 'cancelled':
      return 'red';
    case 'suppressed':
      return 'zinc';
    default:
      return 'zinc';
  }
}

export function attemptDirectionLabel(direction: string | null | undefined): string {
  if (!direction) return '—';
  return humanizeToken(direction);
}

export function webhookProcessingStatusLabel(status: string | null | undefined): string {
  if (!status) return '—';
  return humanizeToken(status);
}

export function webhookProcessingStatusTone(status: string | null | undefined): BadgeTone {
  switch (status) {
    case 'processed':
      return 'green';
    case 'received':
      return 'amber';
    case 'failed':
      return 'red';
    case 'ignored':
      return 'zinc';
    default:
      return 'zinc';
  }
}

export function sourceDomainLabel(domain: string | null | undefined): string {
  const map: Record<string, string> = {
    notifications: 'Notifications',
    marketing: 'Marketing',
    payments: 'Payments',
    pos: 'POS',
  };
  if (!domain) return '—';
  return map[domain] ?? humanizeToken(domain);
}

export function sourceTypeLabel(type: string | null | undefined): string {
  const map: Record<string, string> = {
    notification_message: 'Notification message',
    marketing_message: 'Marketing message',
    payment_transaction: 'Payment transaction',
    payment_link: 'Payment link',
  };
  if (!type) return '—';
  return map[type] ?? humanizeToken(type);
}

export function driversForCategory(category: string): readonly string[] {
  switch (category) {
    case 'email':
      return ['simulation', 'mailgun', 'manual', 'custom'];
    case 'sms':
      return ['simulation', 'twilio', 'manual', 'custom'];
    case 'payment_gateway':
      return ['simulation', 'stripe', 'manual', 'custom'];
    default:
      return PROVIDER_DRIVERS;
  }
}

export function isLiveDriver(driver: string | null | undefined): boolean {
  return driver === 'mailgun' || driver === 'twilio' || driver === 'stripe';
}

export function accountNeedsCredentials(account: Pick<ProviderAccount, 'driver' | 'has_credentials'>): boolean {
  return isLiveDriver(account.driver) && !account.has_credentials;
}

export function testResultLabel(result: string | null | undefined): string {
  if (!result) return 'Not tested';
  const map: Record<string, string> = {
    simulation_ok: 'Simulation OK',
    simulation_ok_pending_live_driver: 'Config OK (live driver pending)',
    credentials_valid_stub: 'Credentials valid (stub transport)',
    credentials_missing: 'Credentials missing or incomplete',
    category_driver_mismatch: 'Category/driver mismatch',
  };
  return map[result] ?? humanizeToken(result);
}

export function testResultTone(result: string | null | undefined): BadgeTone {
  switch (result) {
    case 'simulation_ok':
    case 'credentials_valid_stub':
      return 'green';
    case 'credentials_missing':
    case 'category_driver_mismatch':
      return 'red';
    case 'simulation_ok_pending_live_driver':
      return 'amber';
    default:
      return 'zinc';
  }
}

export function isLiveAttempt(attempt: ProviderDeliveryAttempt): boolean {
  if (isSimulationFallback(attempt)) return false;
  return attempt.metadata?.simulated === false;
}

export function attemptTransportLabel(attempt: ProviderDeliveryAttempt): string {
  if (isSimulationFallback(attempt)) return 'Simulation fallback';
  if (isLiveAttempt(attempt)) return 'Live (stub)';
  return 'Simulation';
}

export function attemptTransportTone(attempt: ProviderDeliveryAttempt): BadgeTone {
  if (isSimulationFallback(attempt)) return 'amber';
  if (isLiveAttempt(attempt)) return 'green';
  return 'zinc';
}

export function isSimulationFallback(attempt: ProviderDeliveryAttempt): boolean {
  return attempt.metadata?.simulation_fallback === true;
}

export function attemptDriverLabel(attempt: ProviderDeliveryAttempt): string {
  const driver = attempt.metadata?.driver;
  if (typeof driver === 'string') return driverLabel(driver);
  if (attempt.provider_account?.driver) return driverLabel(attempt.provider_account.driver);
  return 'Simulation (fallback)';
}

export function recipientSummary(attempt: ProviderDeliveryAttempt): string {
  return attempt.recipient_address ?? attempt.recipient_phone ?? '—';
}

export function buildIntegrationsOverview(
  accounts: ProviderAccount[],
  attempts: ProviderDeliveryAttempt[],
  events: ProviderWebhookEvent[],
): IntegrationsOverviewSummary {
  const activeAccounts = accounts.filter(
    (a) => !a.archived_at && (a.status === 'active' || a.status === 'test_only'),
  );
  return {
    total_accounts: accounts.length,
    active_accounts: activeAccounts.length,
    default_accounts: accounts.filter((a) => a.is_default && !a.archived_at).length,
    recent_attempts: attempts.length,
    failed_attempts: attempts.filter((a) => a.status === 'failed').length,
    received_webhook_events: events.length,
    attempts_truncated: attempts.length >= INTEGRATIONS_LIST_WINDOW,
    events_truncated: events.length >= INTEGRATIONS_LIST_WINDOW,
  };
}
