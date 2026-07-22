// Module 12A — Analytics & Reporting (read-only operational reporting)
//
// Response shapes mirror the Step 21A backend services, which return stable
// arrays/objects directly (no JsonResources). Optional sections are typed
// defensively so the UI stays resilient to [] / 0 / missing subsections.

// ── Filters ──────────────────────────────────────────────────────────────────

export interface AnalyticsDateFilters {
  from?: string;
  to?: string;
}

export interface AnalyticsScopedFilters extends AnalyticsDateFilters {
  location_id?: string;
}

export interface AnalyticsFullFilters extends AnalyticsScopedFilters {
  provider_id?: string;
}

// ── Shared ───────────────────────────────────────────────────────────────────

export interface AnalyticsRange {
  from: string;
  to: string;
  days: number;
}

// ── Overview ─────────────────────────────────────────────────────────────────

export interface OverviewBookings {
  total_appointments: number;
  completed_appointments: number;
  cancelled_appointments: number;
  no_show_appointments: number;
  checked_in_appointments: number;
  walk_in_appointments: number;
  average_booking_value_cents: number;
}

export interface OverviewPayments {
  total_payment_collected_cents: number;
  deposit_collected_cents: number;
  deposit_refunded_cents: number;
  refund_total_cents: number;
  failed_payments_count: number;
}

export interface OverviewPos {
  completed_checkouts_count: number;
  gross_sales_cents: number;
  refund_value_cents: number;
  average_checkout_value_cents: number;
}

export interface OverviewClients {
  total_clients: number;
  new_clients_in_period: number;
  active_clients: number;
  marketing_email_opt_in_count: number;
  marketing_sms_opt_in_count: number;
}

export interface OverviewMemberships {
  active_memberships: number;
  active_packages: number;
  wallet_liability_cents: number;
  loyalty_points_outstanding: number;
}

export interface OverviewInventory {
  low_stock_items_count: number;
  stock_adjustments_count: number;
  stock_consumption_events_count: number;
}

export interface OverviewMarketing {
  campaigns_count: number;
  messages_sent_count: number;
  messages_failed_count: number;
  workflow_executions_count: number;
}

export interface OverviewNotifications {
  messages_sent_count: number;
  messages_failed_count: number;
  messages_suppressed_count: number;
}

export interface AnalyticsOverview {
  range: AnalyticsRange;
  bookings: OverviewBookings;
  payments: OverviewPayments;
  pos: OverviewPos;
  clients: OverviewClients;
  memberships: OverviewMemberships;
  inventory: OverviewInventory;
  marketing: OverviewMarketing;
  notifications: OverviewNotifications;
}

// ── Bookings ─────────────────────────────────────────────────────────────────

export interface BookingSummary {
  total_appointments: number;
  completed_appointments: number;
  cancelled_appointments: number;
  no_show_appointments: number;
  checked_in_appointments: number;
  confirmed_appointments: number;
  pending_appointments: number;
  walk_in_appointments: number;
  average_booking_value_cents: number;
  cancellation_rate: number;
  no_show_rate: number;
}

export interface BookingDailyRow {
  date: string;
  total: number;
  completed: number;
}

export interface BookingProviderRow {
  provider_id: string | null;
  provider_name: string | null;
  total_appointments: number;
  completed_appointments: number;
  no_show_appointments: number;
}

export interface BookingServiceRow {
  service_name: string | null;
  bookings: number;
  revenue_cents: number;
}

export interface BookingAnalytics {
  range: AnalyticsRange;
  summary: BookingSummary;
  daily: BookingDailyRow[];
  providers: BookingProviderRow[];
  services: BookingServiceRow[];
}

// ── Revenue ──────────────────────────────────────────────────────────────────

export interface RevenuePayments {
  total_payment_collected_cents: number;
  deposit_collected_cents: number;
  deposit_refunded_cents: number;
  refund_total_cents: number;
  failed_payments_count: number;
  net_collected_cents: number;
}

export interface RevenueDailyRow {
  date: string;
  collected_cents: number;
  pos_sales_cents: number;
}

export interface RevenueStatusRow {
  status: string;
  total: number;
  amount_cents: number;
}

export interface RevenueTypeRow {
  transaction_type: string;
  total: number;
  amount_cents: number;
}

export interface RevenueProviderRow {
  provider: string;
  total: number;
  amount_cents: number;
}

export interface RevenueAnalytics {
  range: AnalyticsRange;
  summary: {
    payments: RevenuePayments;
    pos: OverviewPos;
  };
  daily: RevenueDailyRow[];
  payment_status_breakdown: RevenueStatusRow[];
  payment_type_breakdown: RevenueTypeRow[];
  provider_breakdown: RevenueProviderRow[];
}

// ── Clients ──────────────────────────────────────────────────────────────────

export interface ClientGrowthRow {
  date: string;
  new_clients: number;
}

export interface ClientTagRow {
  tag_id: string;
  name: string;
  total: number;
}

export interface ClientConsentCounts {
  granted: number;
  denied: number;
}

export interface ClientMembershipAttachment {
  clients_with_active_membership: number;
  clients_with_active_package: number;
}

export interface ClientAnalytics {
  range: AnalyticsRange;
  summary: OverviewClients;
  growth: ClientGrowthRow[];
  tags: ClientTagRow[];
  consents: Record<string, ClientConsentCounts>;
  membership_attachment: ClientMembershipAttachment;
}

// ── Inventory ────────────────────────────────────────────────────────────────

export interface InventorySummary {
  low_stock_items_count: number;
  total_movements_count: number;
  stock_adjustments_count: number;
  stock_consumption_events_count: number;
  consumption_total_quantity: number;
  waste_total_quantity: number;
}

export interface InventoryMovementRow {
  movement_type: string;
  total: number;
  quantity: number;
}

export interface InventoryLowStockRow {
  item_id: string;
  item_name: string | null;
  item_type: string | null;
  location_id: string | null;
  on_hand_quantity: number;
  reorder_point: number;
}

export interface InventoryConsumedRow {
  item_id: string;
  item_name: string | null;
  item_type: string | null;
  quantity: number;
  events: number;
}

export interface InventoryAnalytics {
  range: AnalyticsRange;
  summary: InventorySummary;
  movement_breakdown: InventoryMovementRow[];
  low_stock: InventoryLowStockRow[];
  top_consumed_items: InventoryConsumedRow[];
}

// ── Communications ───────────────────────────────────────────────────────────

export interface CommunicationsChannelRow {
  channel: string;
  total: number;
  sent: number;
  failed: number;
  suppressed: number;
}

export interface CommunicationsWorkflowStatusRow {
  status: string;
  total: number;
}

export interface CommunicationsMarketing {
  campaigns_count: number;
  messages_sent_count: number;
  messages_failed_count: number;
  messages_suppressed_count: number;
  workflow_executions_count: number;
  workflow_execution_status_breakdown: CommunicationsWorkflowStatusRow[];
  by_channel: CommunicationsChannelRow[];
}

export interface CommunicationsNotifications {
  messages_sent_count: number;
  messages_failed_count: number;
  messages_suppressed_count: number;
  by_channel: CommunicationsChannelRow[];
}

export interface CommunicationsAnalytics {
  range: AnalyticsRange;
  marketing: CommunicationsMarketing;
  notifications: CommunicationsNotifications;
}

// ── Labels & helpers ─────────────────────────────────────────────────────────

const MOVEMENT_TYPE_LABELS: Record<string, string> = {
  opening: 'Opening',
  adjustment: 'Adjustment',
  purchase_receipt: 'Purchase receipt',
  sale: 'Sale',
  service_consumption: 'Service consumption',
  waste: 'Waste',
  transfer_in: 'Transfer in',
  transfer_out: 'Transfer out',
};

const CHANNEL_LABELS: Record<string, string> = {
  email: 'Email',
  sms: 'SMS',
  whatsapp: 'WhatsApp',
  push: 'Push',
  internal_note: 'Internal note',
};

export function humanizeToken(value?: string | null): string {
  if (!value) return '—';
  return value
    .split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
}

export function movementTypeLabel(type?: string | null): string {
  if (!type) return '—';
  return MOVEMENT_TYPE_LABELS[type] ?? humanizeToken(type);
}

export function channelLabel(channel?: string | null): string {
  if (!channel) return '—';
  return CHANNEL_LABELS[channel] ?? humanizeToken(channel);
}

export function formatMoneyCents(cents?: number | null, currency = 'GBP'): string {
  const amount = (cents ?? 0) / 100;
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency }).format(amount);
}

export function formatNumber(value?: number | null): string {
  if (value === undefined || value === null) return '—';
  return new Intl.NumberFormat('en-GB').format(value);
}

/**
 * Render a 0..1 rate as a whole-ish percentage string (e.g. 0.1234 → "12.3%").
 */
export function formatRate(rate?: number | null): string {
  if (rate === undefined || rate === null || Number.isNaN(rate)) return '—';
  return `${(rate * 100).toFixed(1)}%`;
}

export function formatDay(value?: string | null): string {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
}

export function formatRangeLabel(range?: AnalyticsRange | null): string {
  if (!range) return '—';
  const from = new Date(range.from);
  const to = new Date(range.to);
  if (Number.isNaN(from.getTime()) || Number.isNaN(to.getTime())) return '—';
  const fmt = (d: Date) => d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
  return `${fmt(from)} → ${fmt(to)} (${range.days} days)`;
}

// ── Module 12B — Saved reports & exports ───────────────────────────────────────

export type AnalyticsReportType =
  | 'overview'
  | 'bookings'
  | 'revenue'
  | 'clients'
  | 'inventory'
  | 'communications';

export type AnalyticsExportFormat = 'csv' | 'json';

export type AnalyticsExportJobStatus = 'pending' | 'processing' | 'completed' | 'failed';

export type AnalyticsScheduleFrequency = 'daily' | 'weekly' | 'monthly';

export const ANALYTICS_REPORT_TYPES: AnalyticsReportType[] = [
  'overview',
  'bookings',
  'revenue',
  'clients',
  'inventory',
  'communications',
];

export const ANALYTICS_EXPORT_FORMATS: AnalyticsExportFormat[] = ['csv', 'json'];

export const ANALYTICS_SCHEDULE_FREQUENCIES: AnalyticsScheduleFrequency[] = ['daily', 'weekly', 'monthly'];

/** Saved analytics filters snapshot (mirrors backend filters_json). */
export interface AnalyticsSavedFilters {
  from?: string;
  to?: string;
  location_id?: string;
  provider_id?: string;
}

export interface AnalyticsSavedReport {
  id: string;
  name: string;
  report_type: AnalyticsReportType;
  filters: AnalyticsSavedFilters;
  export_format: AnalyticsExportFormat;
  is_scheduled: boolean;
  schedule_frequency: AnalyticsScheduleFrequency | null;
  schedule_day_of_week: number | null;
  schedule_day_of_month: number | null;
  schedule_time: string | null;
  last_run_at: string | null;
  archived_at: string | null;
  created_by?: { id: string | null; name: string | null } | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface AnalyticsExportJob {
  id: string;
  report_type: AnalyticsReportType;
  export_format: AnalyticsExportFormat;
  status: AnalyticsExportJobStatus;
  filters: AnalyticsSavedFilters;
  file_name: string | null;
  row_count: number | null;
  started_at: string | null;
  completed_at: string | null;
  failed_at: string | null;
  failure_reason: string | null;
  saved_report?: { id: string; name: string } | null;
  download_url: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface SavedReportPayload {
  name: string;
  report_type: AnalyticsReportType;
  export_format: AnalyticsExportFormat;
  filters?: AnalyticsSavedFilters;
  is_scheduled?: boolean;
  schedule_frequency?: AnalyticsScheduleFrequency | null;
  schedule_day_of_week?: number | null;
  schedule_day_of_month?: number | null;
  schedule_time?: string | null;
}

export interface ExportCreatePayload {
  report_type: AnalyticsReportType;
  export_format: AnalyticsExportFormat;
  filters?: AnalyticsSavedFilters;
}

export interface ExportJobFilters {
  report_type?: AnalyticsReportType | '';
  status?: AnalyticsExportJobStatus | '';
}

const REPORT_TYPE_LABELS: Record<AnalyticsReportType, string> = {
  overview: 'Overview',
  bookings: 'Bookings',
  revenue: 'Revenue',
  clients: 'Clients',
  inventory: 'Inventory',
  communications: 'Communications',
};

const EXPORT_STATUS_LABELS: Record<AnalyticsExportJobStatus, string> = {
  pending: 'Pending',
  processing: 'Processing',
  completed: 'Completed',
  failed: 'Failed',
};

export function reportTypeLabel(type?: string | null): string {
  if (!type) return '—';
  return REPORT_TYPE_LABELS[type as AnalyticsReportType] ?? humanizeToken(type);
}

export function exportFormatLabel(format?: string | null): string {
  if (!format) return '—';
  return format.toUpperCase();
}

export function exportStatusLabel(status?: string | null): string {
  if (!status) return '—';
  return EXPORT_STATUS_LABELS[status as AnalyticsExportJobStatus] ?? humanizeToken(status);
}

/** Tailwind badge tone tokens keyed by export job status. */
export type BadgeTone = 'green' | 'amber' | 'red' | 'zinc';

export function exportStatusTone(status?: string | null): BadgeTone {
  switch (status) {
    case 'completed':
      return 'green';
    case 'processing':
    case 'pending':
      return 'amber';
    case 'failed':
      return 'red';
    default:
      return 'zinc';
  }
}

const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

/**
 * Human summary of a saved report's schedule config. Returns null when the
 * report is not scheduled so callers can render nothing / a plain badge.
 */
export function scheduleSummary(report: Pick<AnalyticsSavedReport,
  'is_scheduled' | 'schedule_frequency' | 'schedule_day_of_week' | 'schedule_day_of_month' | 'schedule_time'>): string | null {
  if (!report.is_scheduled) return null;

  const parts: string[] = [];
  if (report.schedule_frequency) {
    parts.push(report.schedule_frequency.charAt(0).toUpperCase() + report.schedule_frequency.slice(1));
  } else {
    parts.push('Scheduled');
  }

  if (report.schedule_frequency === 'weekly' && report.schedule_day_of_week !== null && report.schedule_day_of_week !== undefined) {
    parts.push(`on ${WEEKDAY_LABELS[report.schedule_day_of_week] ?? `day ${report.schedule_day_of_week}`}`);
  }
  if (report.schedule_frequency === 'monthly' && report.schedule_day_of_month !== null && report.schedule_day_of_month !== undefined) {
    parts.push(`on day ${report.schedule_day_of_month}`);
  }
  if (report.schedule_time) {
    parts.push(`at ${report.schedule_time}`);
  }

  return parts.join(' ');
}

/** Which report types accept a location filter (mirrors 21B behaviour). */
export function reportSupportsLocation(type: AnalyticsReportType): boolean {
  return type !== 'communications';
}

/** Which report types accept a provider filter (mirrors 21B behaviour). */
export function reportSupportsProvider(type: AnalyticsReportType): boolean {
  return type === 'overview' || type === 'bookings' || type === 'revenue';
}

export function formatDateTime(value?: string | null): string {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}
