export const MARKETING_CHANNELS = ['email', 'sms', 'whatsapp', 'push', 'in_app'] as const;

export const CAMPAIGN_TYPES = ['broadcast', 'automation'] as const;

export const CAMPAIGN_STATUSES = ['draft', 'active', 'paused', 'archived'] as const;

export const TRIGGER_TYPES = [
  'booking_reminder',
  'rebooking_nudge',
  'win_back',
  'review_request',
  'birthday',
  'membership_renewal',
] as const;

export const ACTIVE_TRIGGER_TYPES = [
  'booking_reminder',
  'rebooking_nudge',
  'win_back',
  'review_request',
] as const;

export const MESSAGE_STATUSES = [
  'pending',
  'queued',
  'processing',
  'sent',
  'delivered',
  'failed',
  'cancelled',
  'skipped',
  'suppressed',
  'unsubscribed',
] as const;

export const RUN_STATUSES = ['pending', 'processing', 'completed', 'failed', 'cancelled'] as const;

export const RUN_SOURCES = ['manual', 'scheduler', 'event'] as const;

export const MESSAGE_PURPOSES = [
  'booking_reminder',
  'rebooking_nudge',
  'win_back',
  'review_request',
  'broadcast',
  'membership_reminder',
  'workflow',
] as const;

// ── Module 10B: workflows, executions, suppressions ──────────────────────────

export const WORKFLOW_STATUSES = ['draft', 'active', 'paused', 'archived'] as const;

export const WORKFLOW_TRIGGERS = [
  'client_created',
  'consent_granted',
  'consent_withdrawn',
  'appointment_completed',
  'appointment_no_show',
  'birthday',
  'membership_started',
  'membership_cancelled',
  'manual',
] as const;

export const WORKFLOW_STEP_TYPES = ['send_message', 'wait', 'tag_client', 'internal_note'] as const;

export const EXECUTION_STATUSES = [
  'queued',
  'running',
  'completed',
  'cancelled',
  'failed',
  'skipped',
] as const;

export const EXECUTION_STEP_STATUSES = [
  'queued',
  'processing',
  'completed',
  'skipped',
  'failed',
] as const;

export const SUPPRESSION_REASONS = [
  'unsubscribe',
  'hard_bounce',
  'manual',
  'complaint',
  'invalid_contact',
] as const;

export const SUPPRESSION_SOURCES = ['client_action', 'staff_action', 'system'] as const;

export const TEMPLATE_PLACEHOLDERS = [
  'client.first_name',
  'client.last_name',
  'business.name',
  'location.name',
  'appointment.start_at',
  'appointment.service_summary',
  'membership.plan_name',
  'review.link',
  'booking.link',
] as const;

export type MarketingChannel = (typeof MARKETING_CHANNELS)[number];

export type CampaignType = (typeof CAMPAIGN_TYPES)[number];

export type CampaignStatus = (typeof CAMPAIGN_STATUSES)[number];

export type TriggerType = (typeof TRIGGER_TYPES)[number];

export type MessageStatus = (typeof MESSAGE_STATUSES)[number];

export type RunStatus = (typeof RUN_STATUSES)[number];

export type RunSource = (typeof RUN_SOURCES)[number];

export type MessagePurpose = (typeof MESSAGE_PURPOSES)[number];

export type WorkflowStatus = (typeof WORKFLOW_STATUSES)[number];

export type WorkflowTrigger = (typeof WORKFLOW_TRIGGERS)[number];

export type WorkflowStepType = (typeof WORKFLOW_STEP_TYPES)[number];

export type ExecutionStatus = (typeof EXECUTION_STATUSES)[number];

export type ExecutionStepStatus = (typeof EXECUTION_STEP_STATUSES)[number];

export type SuppressionReason = (typeof SUPPRESSION_REASONS)[number];

export type SuppressionSource = (typeof SUPPRESSION_SOURCES)[number];

export interface MarketingTemplate {
  id: string;
  name: string;
  category?: string | null;
  channel: MarketingChannel | string;
  subject?: string | null;
  body_text?: string | null;
  body_html?: string | null;
  variables?: string[];
  is_system?: boolean;
  is_active?: boolean;
  campaigns_count?: number;
  created_at?: string;
  updated_at?: string;
}

export interface AudienceRules {
  location_ids?: string[];
  client_tag_ids?: string[];
  client_status?: string;
  requires_email_consent?: boolean;
  requires_sms_consent?: boolean;
  preferred_team_member_ids?: string[];
  loyalty_display_statuses?: string[];
  has_future_booking?: boolean;
  last_visit_before?: string;
  last_visit_after?: string;
}

export interface MarketingAudience {
  id: string;
  name: string;
  description?: string | null;
  rules: AudienceRules;
  is_active?: boolean;
  created_by_team_member_id?: string | null;
  created_by?: { id: string; display_name?: string | null } | null;
  created_at?: string;
  updated_at?: string;
}

export interface MarketingCampaign {
  id: string;
  name: string;
  campaign_type: CampaignType | string;
  trigger_type?: TriggerType | string | null;
  channel: MarketingChannel | string;
  status: CampaignStatus | string;
  template_id?: string | null;
  audience_name?: string | null;
  audience_rules?: AudienceRules;
  location_id?: string | null;
  created_by_team_member_id?: string | null;
  notes?: string | null;
  last_run_at?: string | null;
  template?: MarketingTemplate | null;
  location?: { id: string; name: string } | null;
  created_by?: { id: string; display_name?: string | null } | null;
  runs_count?: number;
  messages_count?: number;
  created_at?: string;
  updated_at?: string;
}

export interface MarketingRunSummary {
  eligible?: number;
  skipped?: number;
  total?: number;
  dispatch?: {
    processed?: number;
    sent?: number;
    failed?: number;
    skipped?: number;
  };
  [key: string]: unknown;
}

export interface MarketingRun {
  id: string;
  marketing_campaign_id?: string | null;
  trigger_type?: TriggerType | string | null;
  run_source?: RunSource | string;
  status: RunStatus | string;
  filters?: Record<string, unknown>;
  summary?: MarketingRunSummary;
  started_at?: string | null;
  completed_at?: string | null;
  created_by_team_member_id?: string | null;
  campaign?: MarketingCampaign | null;
  created_by?: { id: string; display_name?: string | null } | null;
  messages?: MarketingMessage[];
  messages_count?: number;
  created_at?: string;
  updated_at?: string;
}

export interface MarketingMessageAttempt {
  id: string;
  status: string;
  provider?: string | null;
  provider_reference?: string | null;
  failure_category?: string | null;
  attempted_at?: string | null;
  error_message?: string | null;
}

export interface MarketingMessage {
  id: string;
  marketing_campaign_id?: string | null;
  marketing_run_id?: string | null;
  workflow_execution_id?: string | null;
  workflow_step_id?: string | null;
  client_id?: string | null;
  appointment_id?: string | null;
  membership_id?: string | null;
  location_id?: string | null;
  channel: MarketingChannel | string;
  purpose?: MessagePurpose | string;
  status: MessageStatus | string;
  recipient_address?: string | null;
  subject?: string | null;
  rendered_body_text?: string | null;
  rendered_body_html?: string | null;
  template_snapshot?: Record<string, unknown>;
  variables_snapshot?: Record<string, unknown>;
  scheduled_for?: string | null;
  sent_at?: string | null;
  delivered_at?: string | null;
  opened_at?: string | null;
  clicked_at?: string | null;
  unsubscribed_at?: string | null;
  failed_at?: string | null;
  suppressed_at?: string | null;
  skipped_reason?: string | null;
  provider_message_reference?: string | null;
  provider_message_id?: string | null;
  failure_category?: string | null;
  error_message?: string | null;
  attempts?: MarketingMessageAttempt[];
  client?: {
    id: string;
    display_name?: string | null;
    first_name?: string | null;
    last_name?: string | null;
  } | null;
  created_at?: string;
  updated_at?: string;
}

export interface MarketingAutomationSettings {
  booking_reminder_hours_before: number;
  review_request_delay_hours: number;
  rebooking_window_days: number;
  win_back_inactivity_days: number;
  review_request_enabled: boolean;
  auto_pause_on_consent_withdrawal: boolean;
}

export interface AudiencePreviewSampleClient {
  client_id: string;
  client_name: string;
  recipient_address?: string | null;
}

export interface AudiencePreviewSkipped {
  client_id: string;
  client_name?: string;
  reason: string;
}

export interface AudiencePreviewResult {
  channel: string;
  counts: Record<string, number>;
  eligible_sample: AudiencePreviewSampleClient[];
  skipped_sample: AudiencePreviewSkipped[];
  render_preview?: {
    subject?: string | null;
    body_text?: string;
    body_html?: string | null;
  } | null;
}

// ── Module 10B: workflow, execution and suppression models ───────────────────

export interface MarketingWorkflowStep {
  id?: string;
  position?: number;
  step_type: WorkflowStepType | string;
  delay_minutes?: number | null;
  template_id?: string | null;
  channel?: MarketingChannel | string | null;
  payload?: Record<string, unknown>;
}

export interface MarketingWorkflow {
  id: string;
  name: string;
  slug?: string | null;
  description?: string | null;
  trigger_type: WorkflowTrigger | string;
  channel: MarketingChannel | string;
  status: WorkflowStatus | string;
  audience_rules?: AudienceRules;
  template_id?: string | null;
  delay_minutes?: number | null;
  cooldown_days?: number | null;
  allow_repeat?: boolean;
  max_executions_per_client?: number | null;
  settings?: Record<string, unknown>;
  last_triggered_at?: string | null;
  template?: MarketingTemplate | null;
  steps?: MarketingWorkflowStep[];
  steps_count?: number;
  created_by?: { id: string; display_name?: string | null } | null;
  created_at?: string;
  updated_at?: string;
}

export interface MarketingWorkflowExecutionStep {
  id: string;
  position?: number;
  step_type: WorkflowStepType | string;
  status: ExecutionStepStatus | string;
  scheduled_for?: string | null;
  processed_at?: string | null;
  failure_reason?: string | null;
  message_id?: string | null;
  message?: MarketingMessage | null;
}

export interface MarketingWorkflowExecution {
  id: string;
  workflow_id?: string | null;
  client_id?: string | null;
  campaign_id?: string | null;
  trigger_type?: WorkflowTrigger | string | null;
  trigger_reference_type?: string | null;
  trigger_reference_id?: string | null;
  status: ExecutionStatus | string;
  current_step_position?: number | null;
  scheduled_for?: string | null;
  started_at?: string | null;
  completed_at?: string | null;
  cancelled_at?: string | null;
  failure_reason?: string | null;
  context?: Record<string, unknown>;
  workflow?: MarketingWorkflow | null;
  client?: {
    id: string;
    display_name?: string | null;
    first_name?: string | null;
    last_name?: string | null;
  } | null;
  steps?: MarketingWorkflowExecutionStep[];
  messages?: MarketingMessage[];
  messages_count?: number;
  created_at?: string;
  updated_at?: string;
}

export interface MarketingContactSuppression {
  id: string;
  client_id?: string | null;
  channel: MarketingChannel | string;
  contact_value: string;
  reason?: SuppressionReason | string | null;
  source?: SuppressionSource | string;
  is_active?: boolean;
  lifted_at?: string | null;
  notes?: string | null;
  client?: { id: string; display_name?: string | null } | null;
  created_by?: { id: string; display_name?: string | null } | null;
  created_at?: string;
  updated_at?: string;
}

export interface AutomationReportingSummary {
  period: { from: string | null; to: string | null };
  workflows: { total: number; active: number; by_trigger: Record<string, number> };
  executions: Record<string, number>;
  messages: Record<string, number>;
  suppressions: { total: number; active: number; by_channel: Record<string, number> };
}

export interface AutomationWorkflowReport {
  workflow_id: string;
  name: string;
  trigger_type: string;
  channel: string;
  status: string;
  steps_count: number;
  last_triggered_at?: string | null;
  executions: Record<string, number>;
  messages: Record<string, number>;
}

export interface AutomationExecutionReport {
  execution_id: string;
  workflow_id?: string | null;
  workflow_name?: string | null;
  trigger_type?: string | null;
  client_id?: string | null;
  client_name?: string | null;
  status: string;
  failure_reason?: string | null;
  scheduled_for?: string | null;
  started_at?: string | null;
  completed_at?: string | null;
  messages_count: number;
}

export interface AutomationMessageReport {
  message_id: string;
  workflow_execution_id?: string | null;
  client_id?: string | null;
  client_name?: string | null;
  channel: string;
  status: string;
  recipient_address?: string | null;
  sent_at?: string | null;
  delivered_at?: string | null;
  failed_at?: string | null;
  failure_category?: string | null;
}

export interface AutomationSuppressionReport {
  total: number;
  active: number;
  by_reason: Record<string, number>;
  by_channel: Record<string, number>;
}

export interface MarketingReportingSummary {
  period: { from: string | null; to: string | null };
  messages: Record<string, number>;
  runs: Record<string, number>;
  campaigns: { total: number; active: number };
  channels: Record<string, number>;
}

export interface MarketingCampaignReport {
  campaign_id: string;
  name: string;
  campaign_type: string;
  trigger_type?: string | null;
  channel: string;
  status: string;
  runs_count: number;
  messages_count: number;
  messages_by_status: Record<string, number>;
  last_run_at?: string | null;
}

export interface MarketingRunReport {
  run_id: string;
  campaign_id?: string | null;
  campaign_name?: string | null;
  trigger_type?: string | null;
  run_source?: string;
  status: string;
  started_at?: string | null;
  completed_at?: string | null;
  summary_json?: MarketingRunSummary | null;
  messages_total: number;
  messages_sent: number;
  messages_failed: number;
  messages_skipped: number;
}

const CHANNEL_LABELS: Record<string, string> = {
  email: 'Email',
  sms: 'SMS',
  whatsapp: 'WhatsApp',
  push: 'Push',
  in_app: 'In-app',
};

const TRIGGER_LABELS: Record<string, string> = {
  booking_reminder: 'Booking reminder',
  rebooking_nudge: 'Rebooking nudge',
  win_back: 'Win-back',
  review_request: 'Review request',
  birthday: 'Birthday',
  membership_renewal: 'Membership renewal',
  client_created: 'Client created',
  consent_granted: 'Consent granted',
  consent_withdrawn: 'Consent withdrawn',
  appointment_completed: 'Appointment completed',
  appointment_no_show: 'Appointment no-show',
  membership_started: 'Membership started',
  membership_cancelled: 'Membership cancelled',
  manual: 'Manual',
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

export function triggerLabel(trigger?: string | null): string {
  if (!trigger) return '—';
  return TRIGGER_LABELS[trigger] ?? humanizeToken(trigger);
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
  active: 'bg-emerald-100 text-emerald-800',
  completed: 'bg-emerald-100 text-emerald-800',
  sent: 'bg-emerald-100 text-emerald-800',
  delivered: 'bg-emerald-100 text-emerald-800',
  draft: 'bg-zinc-200 text-zinc-700',
  pending: 'bg-amber-100 text-amber-800',
  queued: 'bg-amber-100 text-amber-800',
  processing: 'bg-blue-100 text-blue-800',
  running: 'bg-blue-100 text-blue-800',
  paused: 'bg-amber-100 text-amber-800',
  failed: 'bg-red-100 text-red-700',
  cancelled: 'bg-zinc-200 text-zinc-600',
  skipped: 'bg-zinc-200 text-zinc-600',
  archived: 'bg-zinc-200 text-zinc-600',
  suppressed: 'bg-orange-100 text-orange-800',
  unsubscribed: 'bg-orange-100 text-orange-800',
};

export function statusTone(status?: string | null): string {
  if (!status) return 'bg-zinc-200 text-zinc-600';
  return STATUS_TONES[status] ?? 'bg-zinc-200 text-zinc-600';
}
