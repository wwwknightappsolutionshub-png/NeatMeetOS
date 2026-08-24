import { api } from '@/lib/api-client';

const auth = { auth: true as const };

function unwrapList<T>(data: unknown): T[] {
  if (Array.isArray(data)) return data as T[];
  if (data && typeof data === 'object') {
    const nested = (data as { data?: unknown }).data;
    if (Array.isArray(nested)) return nested as T[];
  }
  return [];
}

export interface AdminThreadMessage {
  id: string;
  client_id: string;
  author_user_id: string | null;
  direction: 'inbound' | 'outbound' | string;
  channel: string;
  subject: string | null;
  body: string;
  whatsapp_deeplink: string | null;
  metadata: Record<string, unknown> | null;
  read_at: string | null;
  created_at: string | null;
}

export interface AdminConversationSummary {
  client_id: string;
  client_name: string;
  client_phone: string | null;
  client_email: string | null;
  last_message: AdminThreadMessage;
  unread_inbound_count: number;
  needs_reply: boolean;
}

export async function fetchAdminConversations(
  filter: 'open' | 'all' = 'open',
): Promise<AdminConversationSummary[]> {
  const data = await api<AdminConversationSummary[] | { data: AdminConversationSummary[] }>(
    `/admin/messages/conversations?filter=${encodeURIComponent(filter)}`,
    auth,
  );
  return unwrapList<AdminConversationSummary>(data);
}

export async function fetchAdminClientThreads(clientId: string): Promise<AdminThreadMessage[]> {
  const data = await api<AdminThreadMessage[] | { data: AdminThreadMessage[] }>(
    `/admin/clients/${clientId}/threads`,
    auth,
  );
  return unwrapList<AdminThreadMessage>(data);
}

export async function postAdminClientThread(
  clientId: string,
  payload: { body: string; channel?: string; notify_member?: boolean },
): Promise<AdminThreadMessage> {
  return api<AdminThreadMessage>(`/admin/clients/${clientId}/threads`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function markAdminClientThreadRead(clientId: string): Promise<{ updated: number }> {
  return api<{ updated: number }>(`/admin/clients/${clientId}/threads/read`, {
    ...auth,
    method: 'POST',
  });
}
