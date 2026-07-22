import { api, API_BASE, getStoredTenantSlug, getStoredToken } from '@/lib/api-client';
import type { LookbookItem, LookbookReplaceImageResult } from '@/lib/lookbook-types';

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

export async function fetchPublicLookbookItems(tenantSlug: string): Promise<LookbookItem[]> {
  const data = await api<LookbookItem[] | { data: LookbookItem[] }>(
    '/lookbook/items',
    publicOpts(tenantSlug),
  );
  return unwrapList(data);
}

export async function fetchAdminLookbookItems(params?: {
  is_published?: boolean;
}): Promise<LookbookItem[]> {
  const search = new URLSearchParams();
  if (params?.is_published !== undefined) {
    search.set('is_published', params.is_published ? '1' : '0');
  }
  const q = search.toString() ? `?${search}` : '';
  const data = await api<LookbookItem[] | { data: LookbookItem[] }>(
    `/admin/lookbook/items${q}`,
    auth,
  );
  return unwrapList(data);
}

export async function updateAdminLookbookItem(
  id: string,
  payload: Partial<{
    image_url: string;
    title: string | null;
    caption: string | null;
    sort_order: number;
    is_published: boolean;
  }>,
): Promise<LookbookItem> {
  return api<LookbookItem>(`/admin/lookbook/items/${id}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(payload),
  });
}

export async function reorderAdminLookbookItems(
  items: { id: string; sort_order: number }[],
): Promise<LookbookItem[]> {
  const data = await api<LookbookItem[] | { data: LookbookItem[] }>(
    '/admin/lookbook/items/reorder',
    {
      ...auth,
      method: 'POST',
      body: JSON.stringify({ items }),
    },
  );
  return unwrapList(data);
}

export async function hideAdminLookbookItem(id: string): Promise<LookbookItem> {
  return api<LookbookItem>(`/admin/lookbook/items/${id}/hide`, {
    ...auth,
    method: 'POST',
  });
}

export async function publishAdminLookbookItem(id: string): Promise<LookbookItem> {
  return api<LookbookItem>(`/admin/lookbook/items/${id}/publish`, {
    ...auth,
    method: 'POST',
  });
}

export async function replaceAdminLookbookImage(
  id: string,
  image: File,
): Promise<LookbookReplaceImageResult> {
  const form = new FormData();
  form.append('image', image);

  const headers: HeadersInit = { Accept: 'application/json' };
  const token = getStoredToken();
  if (token) headers.Authorization = `Bearer ${token}`;
  const slug = getStoredTenantSlug();
  if (slug) headers['X-Tenant-Slug'] = slug;

  const res = await fetch(`${API_BASE}/admin/lookbook/items/${id}/replace-image`, {
    method: 'POST',
    headers,
    body: form,
    credentials: 'omit',
  });

  const json = (await res.json()) as {
    success: boolean;
    message: string;
    data?: LookbookReplaceImageResult;
    errors?: Record<string, string[]>;
  };

  if (!res.ok || !json.success || !json.data) {
    const firstError = json.errors ? Object.values(json.errors).flat()[0] : undefined;
    throw new Error(firstError || json.message || 'Replace failed');
  }

  return json.data;
}
