import { api } from '@/lib/api-client';

export interface AdminAiHairstylePreview {
  id: string;
  composite_image_url: string | null;
  style_label: string | null;
  style_key: string | null;
  sort_order: number;
}

export interface AdminAiHairstyleSession {
  id: string;
  status: string;
  submitted_at: string | null;
  accepted_at: string | null;
  client: {
    id: string;
    display_name: string;
    email: string | null;
    phone: string | null;
  } | null;
  contact: {
    first_name: string | null;
    last_name: string | null;
    email: string | null;
    phone: string | null;
    notes: string | null;
  } | null;
  selected_previews: AdminAiHairstylePreview[];
}

export async function fetchAdminAiHairstyleQueue(): Promise<AdminAiHairstyleSession[]> {
  const data = await api<{ items: AdminAiHairstyleSession[] }>('/admin/ai-hairstyle/sessions', {
    auth: true,
  });
  return data.items;
}

export async function acceptAdminAiHairstyleSession(
  id: string,
): Promise<AdminAiHairstyleSession> {
  return api<AdminAiHairstyleSession>(`/admin/ai-hairstyle/sessions/${encodeURIComponent(id)}/accept`, {
    method: 'POST',
    auth: true,
    body: JSON.stringify({}),
  });
}

export async function declineAdminAiHairstyleSession(
  id: string,
): Promise<AdminAiHairstyleSession> {
  return api<AdminAiHairstyleSession>(`/admin/ai-hairstyle/sessions/${encodeURIComponent(id)}/decline`, {
    method: 'POST',
    auth: true,
    body: JSON.stringify({}),
  });
}
