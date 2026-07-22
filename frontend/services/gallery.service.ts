import { api, API_BASE, getStoredTenantSlug, getStoredToken } from '@/lib/api-client';
import type { GalleryUploadResult, GalleryWork } from '@/lib/gallery-types';

const auth = { auth: true as const };

function publicOpts(tenantSlug: string, init?: RequestInit) {
  return {
    auth: false as const,
    tenant: false as const,
    headers: { 'X-Tenant-Slug': tenantSlug },
    ...init,
  };
}

function unwrapList<T>(data: unknown): T[] {
  if (Array.isArray(data)) return data as T[];
  if (data && typeof data === 'object') {
    const nested = (data as { data?: unknown }).data;
    if (Array.isArray(nested)) return nested as T[];
  }
  return [];
}

export async function fetchPublicGalleryWorks(tenantSlug: string): Promise<GalleryWork[]> {
  const data = await api<GalleryWork[] | { data: GalleryWork[] }>(
    '/gallery/works',
    publicOpts(tenantSlug),
  );
  return unwrapList(data);
}

export async function fetchAdminGalleryWorks(params?: {
  is_published?: boolean;
}): Promise<GalleryWork[]> {
  const search = new URLSearchParams();
  if (params?.is_published !== undefined) {
    search.set('is_published', params.is_published ? '1' : '0');
  }
  const q = search.toString() ? `?${search}` : '';
  const data = await api<GalleryWork[] | { data: GalleryWork[] }>(
    `/admin/gallery/works${q}`,
    auth,
  );
  return unwrapList(data);
}

export async function createAdminGalleryWork(payload: {
  image_url: string;
  caption?: string | null;
  service_tag?: string | null;
  sort_order?: number;
  is_published?: boolean;
}): Promise<GalleryWork> {
  return api<GalleryWork>('/admin/gallery/works', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function updateAdminGalleryWork(
  id: string,
  payload: Partial<{
    image_url: string;
    caption: string | null;
    service_tag: string | null;
    sort_order: number;
    is_published: boolean;
  }>,
): Promise<GalleryWork> {
  return api<GalleryWork>(`/admin/gallery/works/${id}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(payload),
  });
}

export async function deleteAdminGalleryWork(id: string): Promise<void> {
  await api(`/admin/gallery/works/${id}`, {
    ...auth,
    method: 'DELETE',
  });
}

export async function reorderAdminGalleryWorks(
  items: { id: string; sort_order: number }[],
): Promise<GalleryWork[]> {
  const data = await api<GalleryWork[] | { data: GalleryWork[] }>('/admin/gallery/works/reorder', {
    ...auth,
    method: 'POST',
    body: JSON.stringify({ items }),
  });
  return unwrapList(data);
}

export async function uploadAdminGalleryImage(payload: {
  image: File;
  caption?: string;
  service_tag?: string;
  sort_order?: number;
  is_published?: boolean;
}): Promise<GalleryUploadResult> {
  const form = new FormData();
  form.append('image', payload.image);
  if (payload.caption) form.append('caption', payload.caption);
  if (payload.service_tag) form.append('service_tag', payload.service_tag);
  if (payload.sort_order !== undefined) form.append('sort_order', String(payload.sort_order));
  if (payload.is_published !== undefined) {
    form.append('is_published', payload.is_published ? '1' : '0');
  }

  const headers: HeadersInit = { Accept: 'application/json' };
  const token = getStoredToken();
  if (token) headers.Authorization = `Bearer ${token}`;
  const slug = getStoredTenantSlug();
  if (slug) headers['X-Tenant-Slug'] = slug;

  const res = await fetch(`${API_BASE}/admin/gallery/works/upload-image`, {
    method: 'POST',
    headers,
    body: form,
    credentials: 'omit',
  });

  const json = (await res.json()) as {
    success: boolean;
    message: string;
    data?: GalleryUploadResult;
    errors?: Record<string, string[]>;
  };

  if (!res.ok || !json.success || !json.data) {
    const firstError = json.errors ? Object.values(json.errors).flat()[0] : undefined;
    throw new Error(firstError || json.message || 'Upload failed');
  }

  return json.data;
}
