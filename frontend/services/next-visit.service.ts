import { api } from '@/lib/api-client';
import type { Appointment } from '@/lib/booking-types';

const auth = { auth: true as const };

function publicOpts(tenantSlug: string, init?: RequestInit) {
  return {
    auth: false as const,
    tenant: false as const,
    headers: { 'X-Tenant-Slug': tenantSlug },
    ...init,
  };
}

function memberAuthHeaders(tenantSlug: string, token: string): HeadersInit {
  return {
    'X-Tenant-Slug': tenantSlug,
    Authorization: `Bearer ${token}`,
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

export interface ClientThreadMessage {
  id: string;
  client_id: string;
  author_user_id: string | null;
  direction: string;
  channel: string;
  subject: string | null;
  body: string;
  whatsapp_deeplink: string | null;
  metadata: Record<string, unknown> | null;
  read_at: string | null;
  created_at: string | null;
}

export async function fetchAdminNextVisitUpcoming(): Promise<Appointment[]> {
  const data = await api<Appointment[] | { data: Appointment[] }>(
    '/admin/next-visit/upcoming',
    auth,
  );
  return unwrapList(data);
}

export async function nudgeAdminNextVisit(payload: {
  client_id: string;
  body: string;
  subject?: string;
  include_whatsapp_deeplink?: boolean;
}): Promise<ClientThreadMessage> {
  return api<ClientThreadMessage>('/admin/next-visit/nudge', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function fetchAdminClientThreads(clientId: string): Promise<ClientThreadMessage[]> {
  const data = await api<ClientThreadMessage[] | { data: ClientThreadMessage[] }>(
    `/admin/clients/${clientId}/threads`,
    auth,
  );
  return unwrapList(data);
}

export async function postAdminClientThread(
  clientId: string,
  payload: {
    body: string;
    subject?: string;
    channel?: string;
    include_whatsapp_deeplink?: boolean;
  },
): Promise<ClientThreadMessage> {
  return api<ClientThreadMessage>(`/admin/clients/${clientId}/threads`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function fetchMemberNextVisit(
  tenantSlug: string,
  token: string,
): Promise<Appointment[]> {
  const data = await api<Appointment[] | { data: Appointment[] }>('/member/next-visit', {
    ...publicOpts(tenantSlug, {
      headers: memberAuthHeaders(tenantSlug, token),
    }),
  });
  return unwrapList(data);
}

export async function scheduleMemberNextVisit(
  tenantSlug: string,
  token: string,
  payload: {
    visit_id: string;
    starts_at: string;
    team_member_id: string;
    location_id: string;
    workspace_id?: string | null;
    services: { booking_service_id: string; quantity?: number }[];
    client_notes?: string;
  },
): Promise<Appointment> {
  return api<Appointment>('/member/next-visit/schedule', {
    ...publicOpts(tenantSlug, {
      method: 'POST',
      headers: memberAuthHeaders(tenantSlug, token),
      body: JSON.stringify(payload),
    }),
  });
}

export async function fetchMemberNextVisitThreads(
  tenantSlug: string,
  token: string,
): Promise<ClientThreadMessage[]> {
  const data = await api<ClientThreadMessage[] | { data: ClientThreadMessage[] }>(
    '/member/next-visit/threads',
    {
      ...publicOpts(tenantSlug, {
        headers: memberAuthHeaders(tenantSlug, token),
      }),
    },
  );
  return unwrapList(data);
}
