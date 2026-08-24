import { api, API_BASE, getStoredTenantSlug, getStoredToken } from '@/lib/api-client';
import type {
  Client,
  ClientConsentRecord,
  ClientConsentState,
  ClientDocument,
  ClientFormula,
  ClientImportMapping,
  ClientImportPreview,
  ClientImportResult,
  ClientNote,
  ClientPhoto,
  ClientTag,
  ClientVisit,
  PaginatedClients,
  PaginatedTimeline,
} from '@/lib/crm-types';

const auth = { auth: true as const, tenant: true as const };

export async function fetchClients(params?: {
  search?: string;
  is_active?: boolean;
  primary_location_id?: string;
  tag_ids?: string;
  page?: number;
}): Promise<PaginatedClients> {
  const search = new URLSearchParams();
  if (params?.search) search.set('search', params.search);
  if (params?.is_active !== undefined) search.set('is_active', String(params.is_active));
  if (params?.primary_location_id) search.set('primary_location_id', params.primary_location_id);
  if (params?.tag_ids) search.set('tag_ids', params.tag_ids);
  if (params?.page) search.set('page', String(params.page));
  const query = search.toString();

  return api<PaginatedClients>(`/admin/clients${query ? `?${query}` : ''}`, auth);
}

export async function fetchClient(id: string): Promise<Client> {
  return api<Client>(`/admin/clients/${id}`, auth);
}

export async function createClient(data: Partial<Client>): Promise<Client> {
  return api<Client>('/admin/clients', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function updateClient(id: string, data: Partial<Client>): Promise<Client> {
  return api<Client>(`/admin/clients/${id}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function setClientStatus(id: string, is_active: boolean): Promise<Client> {
  return api<Client>(`/admin/clients/${id}/status`, {
    ...auth,
    method: 'PATCH',
    body: JSON.stringify({ is_active }),
  });
}

export async function fetchClientTags(): Promise<ClientTag[]> {
  return api<ClientTag[]>('/admin/crm/tags', auth);
}

export async function createClientTag(data: {
  name: string;
  slug?: string;
  color?: string;
}): Promise<ClientTag> {
  return api<ClientTag>('/admin/crm/tags', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function syncClientTags(clientId: string, tag_ids: string[]): Promise<Client> {
  return api<Client>(`/admin/clients/${clientId}/tags`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify({ tag_ids }),
  });
}

export async function fetchClientNotes(clientId: string): Promise<ClientNote[]> {
  return api<ClientNote[]>(`/admin/clients/${clientId}/notes`, auth);
}

export async function createClientNote(
  clientId: string,
  data: { body: string; note_type?: string },
): Promise<ClientNote> {
  return api<ClientNote>(`/admin/clients/${clientId}/notes`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function fetchClientConsents(clientId: string): Promise<ClientConsentState> {
  return api<ClientConsentState>(`/admin/clients/${clientId}/consents`, auth);
}

export async function recordClientConsent(
  clientId: string,
  data: {
    consent_type: string;
    granted: boolean;
    source?: string;
  },
): Promise<ClientConsentRecord> {
  return api<ClientConsentRecord>(`/admin/clients/${clientId}/consents`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function fetchClientTimeline(clientId: string): Promise<PaginatedTimeline> {
  return api<PaginatedTimeline>(`/admin/clients/${clientId}/timeline`, auth);
}

export async function fetchClientVisits(clientId: string): Promise<ClientVisit[]> {
  return api<ClientVisit[]>(`/admin/clients/${clientId}/visits`, auth);
}

export interface OpenClientVisit {
  id: string;
  client_id: string;
  client?: {
    id: string;
    display_name: string | null;
    first_name: string | null;
    last_name: string | null;
    resolved_display_name: string;
    phone: string | null;
    email: string | null;
  } | null;
  location_id: string | null;
  location?: { id: string; name: string } | null;
  checked_in_at: string | null;
  source: string | null;
  loyalty_points_awarded: number;
}

export async function fetchOpenVisits(locationId?: string): Promise<{
  items: OpenClientVisit[];
  count: number;
}> {
  const q = locationId ? `?location_id=${encodeURIComponent(locationId)}` : '';
  return api(`/admin/visits/open${q}`, auth);
}

export async function fetchClientFormulas(clientId: string): Promise<ClientFormula[]> {
  return api<ClientFormula[]>(`/admin/clients/${clientId}/formulas`, auth);
}

export async function createClientFormula(
  clientId: string,
  data: { title: string; formula_body: string; category?: string; service_context?: string },
): Promise<ClientFormula> {
  return api<ClientFormula>(`/admin/clients/${clientId}/formulas`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function updateClientFormula(
  clientId: string,
  formulaId: string,
  data: Partial<{ title: string; formula_body: string; category: string; service_context: string }>,
): Promise<ClientFormula> {
  return api<ClientFormula>(`/admin/clients/${clientId}/formulas/${formulaId}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function archiveClientFormula(
  clientId: string,
  formulaId: string,
): Promise<ClientFormula> {
  return api<ClientFormula>(`/admin/clients/${clientId}/formulas/${formulaId}/archive`, {
    ...auth,
    method: 'PATCH',
  });
}

export async function fetchClientPhotos(clientId: string): Promise<ClientPhoto[]> {
  return api<ClientPhoto[]>(`/admin/clients/${clientId}/photos`, auth);
}

export async function registerClientPhoto(
  clientId: string,
  data: { storage_path: string; category?: string; caption?: string },
): Promise<ClientPhoto> {
  return api<ClientPhoto>(`/admin/clients/${clientId}/photos`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function archiveClientPhoto(
  clientId: string,
  photoId: string,
): Promise<ClientPhoto> {
  return api<ClientPhoto>(`/admin/clients/${clientId}/photos/${photoId}/archive`, {
    ...auth,
    method: 'PATCH',
  });
}

export async function fetchClientDocuments(clientId: string): Promise<ClientDocument[]> {
  return api<ClientDocument[]>(`/admin/clients/${clientId}/documents`, auth);
}

export async function registerClientDocument(
  clientId: string,
  data: {
    title: string;
    storage_path: string;
    document_type?: string;
    description?: string;
  },
): Promise<ClientDocument> {
  return api<ClientDocument>(`/admin/clients/${clientId}/documents`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function archiveClientDocument(
  clientId: string,
  documentId: string,
): Promise<ClientDocument> {
  return api<ClientDocument>(`/admin/clients/${clientId}/documents/${documentId}/archive`, {
    ...auth,
    method: 'PATCH',
  });
}

async function postClientImportFormData<T>(path: string, form: FormData): Promise<T> {
  const headers: HeadersInit = { Accept: 'application/json' };
  const token = getStoredToken();
  if (token) headers.Authorization = `Bearer ${token}`;
  const slug = getStoredTenantSlug();
  if (slug) headers['X-Tenant-Slug'] = slug;

  const res = await fetch(`${API_BASE}${path}`, {
    method: 'POST',
    headers,
    body: form,
    credentials: 'omit',
  });

  const json = (await res.json()) as {
    success: boolean;
    message: string;
    data?: T;
    errors?: Record<string, string[]>;
  };

  if (!res.ok || !json.success || json.data === undefined) {
    const firstError = json.errors ? Object.values(json.errors).flat()[0] : undefined;
    throw new Error(firstError || json.message || 'Import request failed');
  }

  return json.data;
}

export async function previewClientImport(file: File): Promise<ClientImportPreview> {
  const form = new FormData();
  form.append('file', file);
  return postClientImportFormData<ClientImportPreview>('/admin/clients/import/preview', form);
}

export async function runClientImport(payload: {
  file: File;
  mapping: ClientImportMapping;
  grant_privacy_contact?: boolean;
  grant_marketing_email?: boolean;
  grant_marketing_sms?: boolean;
}): Promise<ClientImportResult> {
  const form = new FormData();
  form.append('file', payload.file);
  form.append('mapping', JSON.stringify(payload.mapping));
  form.append('grant_privacy_contact', payload.grant_privacy_contact === false ? '0' : '1');
  form.append('grant_marketing_email', payload.grant_marketing_email ? '1' : '0');
  form.append('grant_marketing_sms', payload.grant_marketing_sms ? '1' : '0');
  return postClientImportFormData<ClientImportResult>('/admin/clients/import', form);
}
