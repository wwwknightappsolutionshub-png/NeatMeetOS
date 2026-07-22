// Module 11A — Notifications & Communications Foundation (operational, not marketing)

export const NOTIFICATION_CHANNELS = ['email', 'sms', 'whatsapp', 'push', 'in_app', 'internal_note'] as const;

export const NOTIFICATION_CATEGORIES = ['booking', 'payments', 'membership', 'crm', 'general'] as const;

export const NOTIFICATION_MESSAGE_STATUSES = [
  'queued',
  'processing',
  'sent',
  'delivered',
  'failed',
  'cancelled',
  'suppressed',
] as const;

export const NOTIFICATION_DIRECTIONS = ['outbound', 'inbound'] as const;

export const NOTIFICATION_PURPOSES = [
  'booking_confirmation',
  'booking_reminder',
  'booking_cancellation',
  'waitlist_contact',
  'payment_link',
  'payment_reminder',
  'membership_renewal_notice',
  'membership_expiry_notice',
  'manual_client_message',
  'internal_note_delivery',
  'crm_join_welcome',
  'referral_thank_you',
  'referral_invite',
] as const;

export const NOTIFICATION_SOURCE_TYPES = [
  'booking',
  'payments',
  'memberships',
  'marketing',
  'crm',
  'manual',
  'system',
] as const;

export const NOTIFICATION_ATTEMPT_PROVIDERS = [
  'simulation',
  'manual',
  'email_link',
  'sms_link',
  'whatsapp_link',
  'in_app',
] as const;

export type NotificationChannel = (typeof NOTIFICATION_CHANNELS)[number];
export type NotificationCategory = (typeof NOTIFICATION_CATEGORIES)[number];
export type NotificationMessageStatus = (typeof NOTIFICATION_MESSAGE_STATUSES)[number];
export type NotificationDirection = (typeof NOTIFICATION_DIRECTIONS)[number];
export type NotificationPurpose = (typeof NOTIFICATION_PURPOSES)[number];
export type NotificationSourceType = (typeof NOTIFICATION_SOURCE_TYPES)[number];
export type NotificationAttemptProvider = (typeof NOTIFICATION_ATTEMPT_PROVIDERS)[number];

// ── Models ───────────────────────────────────────────────────────────────────

export interface NotificationTemplate {
  id: string;
  name: string;
  slug: string;
  channel: NotificationChannel | string;
  category: NotificationCategory | string;
  subject?: string | null;
  body_text?: string | null;
  body_html?: string | null;
  variables?: Record<string, unknown> | string[] | null;
  is_system: boolean;
  is_active: boolean;
  created_by?: { id: string | null; display_name?: string | null } | null;
  created_at?: string;
  updated_at?: string;
}

export interface NotificationMessageAttempt {
  id: string;
  attempt_number: number;
  provider: NotificationAttemptProvider | string;
  provider_reference?: string | null;
  status: NotificationMessageStatus | string;
  request_payload?: Record<string, unknown>;
  response_payload?: Record<string, unknown>;
  attempted_at?: string | null;
  delivered_at?: string | null;
  failed_at?: string | null;
  failure_reason?: string | null;
  created_at?: string;
}

export interface NotificationMessage {
  id: string;
  client_id?: string | null;
  appointment_id?: string | null;
  checkout_id?: string | null;
  payment_transaction_id?: string | null;
  client_membership_id?: string | null;
  marketing_workflow_execution_id?: string | null;
  notification_template_id?: string | null;
  source_type: NotificationSourceType | string;
  purpose: NotificationPurpose | string;
  channel: NotificationChannel | string;
  direction: NotificationDirection | string;
  status: NotificationMessageStatus | string;
  recipient_name?: string | null;
  recipient_address?: string | null;
  subject?: string | null;
  body_text?: string | null;
  body_html?: string | null;
  metadata?: Record<string, unknown>;
  queued_at?: string | null;
  sent_at?: string | null;
  delivered_at?: string | null;
  failed_at?: string | null;
  cancelled_at?: string | null;
  failure_reason?: string | null;
  attempts?: NotificationMessageAttempt[];
  client?: {
    id?: string;
    display_name?: string | null;
    email?: string | null;
    phone?: string | null;
  } | null;
  created_by?: {
    id?: string | null;
    display_name?: string | null;
  } | null;
  created_at?: string;
  updated_at?: string;
}

export interface NotificationPreference {
  id: string;
  client_id: string;
  allow_email: boolean;
  allow_sms: boolean;
  allow_whatsapp: boolean;
  allow_push: boolean;
  booking_notifications: boolean;
  payment_notifications: boolean;
  membership_notifications: boolean;
  general_notifications: boolean;
  preferred_channel?: NotificationChannel | string | null;
  last_synced_from_consent_at?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface NotificationAutomationSetting {
  id: string;
  booking_reminders_enabled: boolean;
  booking_confirmation_enabled: boolean;
  cancellation_notifications_enabled: boolean;
  payment_link_notifications_enabled: boolean;
  payment_reminders_enabled: boolean;
  membership_expiry_notifications_enabled: boolean;
  membership_renewal_notifications_enabled: boolean;
  default_booking_reminder_hours?: number | null;
  default_booking_reminder_minutes?: number | null;
  default_payment_reminder_days?: number | null;
  sender_name?: string | null;
  sender_email?: string | null;
  sender_sms_name?: string | null;
  metadata?: Record<string, unknown>;
  created_at?: string;
  updated_at?: string;
}

export interface NotificationTimelineEntry {
  id: string;
  source: string;
  source_type: string;
  purpose: string;
  channel: string;
  direction: string;
  status: string;
  subject?: string | null;
  preview?: string | null;
  recipient_address?: string | null;
  occurred_at?: string | null;
  created_at?: string | null;
}

// ── Reporting response shapes ────────────────────────────────────────────────

export interface NotificationReportingSummary {
  period: { from: string; to: string };
  total: number;
  successful: number;
  failed: number;
  suppressed: number;
  by_status: Record<string, number>;
  by_channel: Record<string, number>;
}

export interface NotificationFailureRow {
  message_id: string;
  client_id?: string | null;
  client_name?: string | null;
  channel: string;
  purpose: string;
  status: string;
  recipient_address?: string | null;
  failure_reason?: string | null;
  failed_at?: string | null;
  created_at?: string | null;
}

export interface NotificationByPurposeRow {
  purpose: string;
  total: number;
  by_status: Record<string, number>;
}

// ── Labels & helpers ─────────────────────────────────────────────────────────

const CHANNEL_LABELS: Record<string, string> = {
  email: 'Email',
  sms: 'SMS',
  whatsapp: 'WhatsApp',
  push: 'Push',
  in_app: 'In-app',
  internal_note: 'Internal note',
};

const PURPOSE_LABELS: Record<string, string> = {
  booking_confirmation: 'Booking confirmation',
  booking_reminder: 'Booking reminder',
  booking_cancellation: 'Booking cancellation',
  waitlist_contact: 'Waitlist contact',
  payment_link: 'Payment link',
  payment_reminder: 'Payment reminder',
  membership_renewal_notice: 'Membership renewal',
  membership_expiry_notice: 'Membership expiry',
  manual_client_message: 'Manual message',
  internal_note_delivery: 'Internal note',
  crm_join_welcome: 'CRM join welcome',
  referral_thank_you: 'Referral thank you',
  referral_invite: 'Referral invite',
};

export function humanizeToken(value?: string | null): string {
  if (!value) return '—';
  return value
    .split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
}

export function channelLabel(channel?: string | null): string {
  if (!channel) return '—';
  return CHANNEL_LABELS[channel] ?? humanizeToken(channel);
}

export function purposeLabel(purpose?: string | null): string {
  if (!purpose) return '—';
  return PURPOSE_LABELS[purpose] ?? humanizeToken(purpose);
}

export function sourceTypeLabel(sourceType?: string | null): string {
  return humanizeToken(sourceType);
}

export function formatDateTime(value?: string | null): string {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

const STATUS_TONES: Record<string, string> = {
  sent: 'bg-emerald-100 text-emerald-800',
  delivered: 'bg-emerald-100 text-emerald-800',
  queued: 'bg-amber-100 text-amber-800',
  processing: 'bg-blue-100 text-blue-800',
  failed: 'bg-red-100 text-red-700',
  cancelled: 'bg-zinc-200 text-zinc-600',
  suppressed: 'bg-orange-100 text-orange-800',
};

export function statusTone(status?: string | null): string {
  if (!status) return 'bg-zinc-200 text-zinc-600';
  return STATUS_TONES[status] ?? 'bg-zinc-200 text-zinc-600';
}

/**
 * Whether a message status still allows cancellation.
 */
export function isCancellable(status?: string | null): boolean {
  return status === 'queued' || status === 'processing';
}

/**
 * Whether a message status allows the admin "mark delivered" correction.
 */
export function canMarkDelivered(status?: string | null): boolean {
  return status === 'sent' || status === 'processing';
}
