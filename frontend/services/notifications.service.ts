import { api } from '@/lib/api-client';
import type {
  NotificationAutomationSetting,
  NotificationByPurposeRow,
  NotificationFailureRow,
  NotificationMessage,
  NotificationPreference,
  NotificationReportingSummary,
  NotificationTemplate,
  NotificationTimelineEntry,
} from '@/lib/notifications-types';

const auth = { auth: true as const, tenant: true as const };

function buildQuery(params?: Record<string, string | number | boolean | undefined | null>): string {
  if (!params) return '';
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') {
      search.set(key, String(value));
    }
  }
  const q = search.toString();
  return q ? `?${q}` : '';
}

// ── Templates ────────────────────────────────────────────────────────────────

export async function fetchNotificationTemplates(params?: {
  channel?: string;
  category?: string;
  is_active?: boolean;
  is_system?: boolean;
  search?: string;
}): Promise<NotificationTemplate[]> {
  return api<NotificationTemplate[]>(`/admin/notifications/templates${buildQuery(params)}`, auth);
}

export async function fetchNotificationTemplate(id: string): Promise<NotificationTemplate> {
  return api<NotificationTemplate>(`/admin/notifications/templates/${id}`, auth);
}

export async function createNotificationTemplate(
  data: Partial<NotificationTemplate>,
): Promise<NotificationTemplate> {
  return api<NotificationTemplate>('/admin/notifications/templates', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function updateNotificationTemplate(
  id: string,
  data: Partial<NotificationTemplate>,
): Promise<NotificationTemplate> {
  return api<NotificationTemplate>(`/admin/notifications/templates/${id}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function archiveNotificationTemplate(id: string): Promise<NotificationTemplate> {
  return api<NotificationTemplate>(`/admin/notifications/templates/${id}/archive`, {
    ...auth,
    method: 'PATCH',
  });
}

export async function installNotificationSampleTemplates(): Promise<{ created: number; skipped: number }> {
  return api<{ created: number; skipped: number }>('/admin/notifications/templates/install-samples', {
    ...auth,
    method: 'POST',
  });
}

// ── Messages ─────────────────────────────────────────────────────────────────

export interface NotificationMessageFilters {
  channel?: string;
  status?: string;
  source_type?: string;
  purpose?: string;
  client_id?: string;
  appointment_id?: string;
  from?: string;
  to?: string;
  desk_only?: string;
}

export async function fetchNotificationMessages(
  params?: NotificationMessageFilters,
): Promise<NotificationMessage[]> {
  return api<NotificationMessage[]>(
    `/admin/notifications/messages${buildQuery(params as Record<string, string | undefined> | undefined)}`,
    auth,
  );
}

export async function fetchNotificationMessage(id: string): Promise<NotificationMessage> {
  return api<NotificationMessage>(`/admin/notifications/messages/${id}`, auth);
}

export async function postDeskNotificationMessage(bodyText: string): Promise<NotificationMessage> {
  return api<NotificationMessage>('/admin/notifications/messages/desk', {
    ...auth,
    method: 'POST',
    body: JSON.stringify({ body_text: bodyText }),
  });
}

export interface ManualNotificationMessageInput {
  client_id: string;
  channel: string;
  purpose?: string;
  subject?: string | null;
  body_text?: string | null;
  body_html?: string | null;
  notification_template_id?: string | null;
  recipient_address?: string | null;
  metadata?: Record<string, unknown>;
}

export async function sendManualNotificationMessage(
  data: ManualNotificationMessageInput,
): Promise<NotificationMessage> {
  return api<NotificationMessage>('/admin/notifications/messages/manual', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function cancelNotificationMessage(id: string): Promise<NotificationMessage> {
  return api<NotificationMessage>(`/admin/notifications/messages/${id}/cancel`, {
    ...auth,
    method: 'POST',
  });
}

export async function markNotificationMessageDelivered(id: string): Promise<NotificationMessage> {
  return api<NotificationMessage>(`/admin/notifications/messages/${id}/mark-delivered`, {
    ...auth,
    method: 'POST',
  });
}

// ── Preferences ──────────────────────────────────────────────────────────────

export async function fetchClientNotificationPreferences(
  clientId: string,
): Promise<NotificationPreference> {
  return api<NotificationPreference>(`/admin/notifications/preferences/${clientId}`, auth);
}

export async function updateClientNotificationPreferences(
  clientId: string,
  data: Partial<NotificationPreference>,
): Promise<NotificationPreference> {
  return api<NotificationPreference>(`/admin/notifications/preferences/${clientId}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function syncClientNotificationPreferencesFromConsent(
  clientId: string,
): Promise<NotificationPreference> {
  return api<NotificationPreference>(`/admin/notifications/preferences/${clientId}/sync-from-consent`, {
    ...auth,
    method: 'POST',
  });
}

// ── Settings ─────────────────────────────────────────────────────────────────

export async function fetchNotificationSettings(): Promise<NotificationAutomationSetting> {
  return api<NotificationAutomationSetting>('/admin/notifications/settings', auth);
}

export async function updateNotificationSettings(
  data: Partial<NotificationAutomationSetting>,
): Promise<NotificationAutomationSetting> {
  return api<NotificationAutomationSetting>('/admin/notifications/settings', {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

// ── Timeline ─────────────────────────────────────────────────────────────────

export async function fetchClientNotificationTimeline(
  clientId: string,
  params?: { channel?: string; purpose?: string; from?: string; to?: string },
): Promise<NotificationTimelineEntry[]> {
  return api<NotificationTimelineEntry[]>(
    `/admin/notifications/timeline/clients/${clientId}${buildQuery(params)}`,
    auth,
  );
}

// ── Reporting ────────────────────────────────────────────────────────────────

export async function fetchNotificationReportingSummary(params?: {
  from?: string;
  to?: string;
}): Promise<NotificationReportingSummary> {
  return api<NotificationReportingSummary>(
    `/admin/notifications/reporting/summary${buildQuery(params)}`,
    auth,
  );
}

export async function fetchNotificationReportingFailures(params?: {
  from?: string;
  to?: string;
}): Promise<NotificationFailureRow[]> {
  return api<NotificationFailureRow[]>(
    `/admin/notifications/reporting/failures${buildQuery(params)}`,
    auth,
  );
}

export async function fetchNotificationReportingByPurpose(params?: {
  from?: string;
  to?: string;
}): Promise<NotificationByPurposeRow[]> {
  return api<NotificationByPurposeRow[]>(
    `/admin/notifications/reporting/by-purpose${buildQuery(params)}`,
    auth,
  );
}
