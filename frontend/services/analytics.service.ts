import { api, API_BASE, getStoredToken, getStoredTenantSlug } from '@/lib/api-client';
import type {
  AnalyticsDateFilters,
  AnalyticsExportJob,
  AnalyticsFullFilters,
  AnalyticsOverview,
  AnalyticsSavedReport,
  AnalyticsScopedFilters,
  BookingAnalytics,
  ClientAnalytics,
  CommunicationsAnalytics,
  ExportCreatePayload,
  ExportJobFilters,
  InventoryAnalytics,
  RevenueAnalytics,
  SavedReportPayload,
} from '@/lib/analytics-types';

const auth = { auth: true as const, tenant: true as const };

type QueryParams = Record<string, string | number | boolean | undefined | null>;

function buildQuery(params?: QueryParams): string {
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

export async function fetchAnalyticsOverview(
  params?: AnalyticsFullFilters,
): Promise<AnalyticsOverview> {
  return api<AnalyticsOverview>(`/admin/analytics/overview${buildQuery(params as QueryParams | undefined)}`, auth);
}

export async function fetchBookingAnalytics(
  params?: AnalyticsFullFilters,
): Promise<BookingAnalytics> {
  return api<BookingAnalytics>(`/admin/analytics/bookings${buildQuery(params as QueryParams | undefined)}`, auth);
}

export async function fetchRevenueAnalytics(
  params?: AnalyticsFullFilters,
): Promise<RevenueAnalytics> {
  return api<RevenueAnalytics>(`/admin/analytics/revenue${buildQuery(params as QueryParams | undefined)}`, auth);
}

export async function fetchClientAnalytics(
  params?: AnalyticsScopedFilters,
): Promise<ClientAnalytics> {
  return api<ClientAnalytics>(`/admin/analytics/clients${buildQuery(params as QueryParams | undefined)}`, auth);
}

export async function fetchInventoryAnalytics(
  params?: AnalyticsScopedFilters,
): Promise<InventoryAnalytics> {
  return api<InventoryAnalytics>(`/admin/analytics/inventory${buildQuery(params as QueryParams | undefined)}`, auth);
}

export async function fetchCommunicationsAnalytics(
  params?: AnalyticsDateFilters,
): Promise<CommunicationsAnalytics> {
  return api<CommunicationsAnalytics>(`/admin/analytics/communications${buildQuery(params as QueryParams | undefined)}`, auth);
}

// ── Module 12B — Saved reports ────────────────────────────────────────────────

export async function fetchAnalyticsSavedReports(params?: {
  report_type?: string;
  archived?: boolean;
}): Promise<AnalyticsSavedReport[]> {
  return api<AnalyticsSavedReport[]>(`/admin/analytics/saved-reports${buildQuery(params as QueryParams | undefined)}`, auth);
}

export async function fetchAnalyticsSavedReport(id: string): Promise<AnalyticsSavedReport> {
  return api<AnalyticsSavedReport>(`/admin/analytics/saved-reports/${id}`, auth);
}

export async function createAnalyticsSavedReport(
  payload: SavedReportPayload,
): Promise<AnalyticsSavedReport> {
  return api<AnalyticsSavedReport>('/admin/analytics/saved-reports', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function updateAnalyticsSavedReport(
  id: string,
  payload: Partial<SavedReportPayload>,
): Promise<AnalyticsSavedReport> {
  return api<AnalyticsSavedReport>(`/admin/analytics/saved-reports/${id}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(payload),
  });
}

export async function archiveAnalyticsSavedReport(id: string): Promise<AnalyticsSavedReport> {
  return api<AnalyticsSavedReport>(`/admin/analytics/saved-reports/${id}/archive`, {
    ...auth,
    method: 'PATCH',
  });
}

export async function runAnalyticsSavedReport(id: string): Promise<AnalyticsExportJob> {
  return api<AnalyticsExportJob>(`/admin/analytics/saved-reports/${id}/run`, {
    ...auth,
    method: 'POST',
  });
}

// ── Module 12B — Export jobs ──────────────────────────────────────────────────

export async function fetchAnalyticsExportJobs(
  params?: ExportJobFilters,
): Promise<AnalyticsExportJob[]> {
  return api<AnalyticsExportJob[]>(`/admin/analytics/exports${buildQuery(params as QueryParams | undefined)}`, auth);
}

export async function fetchAnalyticsExportJob(id: string): Promise<AnalyticsExportJob> {
  return api<AnalyticsExportJob>(`/admin/analytics/exports/${id}`, auth);
}

export async function createAnalyticsExport(
  payload: ExportCreatePayload,
): Promise<AnalyticsExportJob> {
  return api<AnalyticsExportJob>('/admin/analytics/exports', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

/**
 * Download a completed export file. The download endpoint streams a file rather
 * than JSON, so we fetch the blob directly (with the same auth/tenant headers
 * the shared api() client uses) and trigger a browser download.
 */
export async function downloadAnalyticsExport(job: AnalyticsExportJob): Promise<void> {
  const path = `/admin/analytics/exports/${job.id}/download`;
  const headers = new Headers({ Accept: 'application/octet-stream' });

  const token = getStoredToken();
  if (token) headers.set('Authorization', `Bearer ${token}`);
  const slug = getStoredTenantSlug();
  if (slug) headers.set('X-Tenant-Slug', slug);

  const response = await fetch(`${API_BASE}${path}`, {
    method: 'GET',
    headers,
    credentials: 'include',
  });

  if (!response.ok) {
    throw new Error('Failed to download export file.');
  }

  const blob = await response.blob();
  const objectUrl = window.URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = objectUrl;
  anchor.download = job.file_name ?? `analytics-${job.report_type}.${job.export_format}`;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  window.URL.revokeObjectURL(objectUrl);
}
