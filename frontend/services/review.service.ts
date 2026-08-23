import { api } from '@/lib/api-client';
import { getTurnstileToken, withTurnstileToken } from '@/lib/turnstile';
import type { SalonReview } from '@/lib/review-types';

function publicOpts(tenantSlug: string, init?: RequestInit) {
  return {
    auth: false as const,
    tenant: false as const,
    headers: { 'X-Tenant-Slug': tenantSlug },
    ...init,
  };
}

export async function fetchPublicReviews(tenantSlug: string): Promise<SalonReview[]> {
  return api<SalonReview[]>('/book/reviews', publicOpts(tenantSlug));
}

export async function submitPublicReview(
  tenantSlug: string,
  payload: { author_name: string; rating: number; body: string },
): Promise<SalonReview> {
  const turnstile_token = await getTurnstileToken();
  return api<SalonReview>('/book/reviews', {
    ...publicOpts(tenantSlug, {
      method: 'POST',
      body: JSON.stringify(withTurnstileToken({ ...payload }, turnstile_token)),
    }),
  });
}

export async function fetchAdminReviews(): Promise<SalonReview[]> {
  return api<SalonReview[]>('/admin/reviews', { auth: true, tenant: true });
}

export async function updateAdminReview(
  id: string,
  payload: Partial<Pick<SalonReview, 'author_name' | 'rating' | 'body' | 'is_published' | 'display_order'>>,
): Promise<SalonReview> {
  return api<SalonReview>(`/admin/reviews/${id}`, {
    method: 'PUT',
    auth: true,
    tenant: true,
    body: JSON.stringify(payload),
  });
}

export async function deleteAdminReview(id: string): Promise<void> {
  await api<null>(`/admin/reviews/${id}`, {
    method: 'DELETE',
    auth: true,
    tenant: true,
  });
}
