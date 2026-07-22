import { api } from '@/lib/api-client';
import type {
  ProviderAccount,
  ProviderAccountFilters,
  ProviderAccountPayload,
  ProviderAttemptFilters,
  ProviderDeliveryAttempt,
  ProviderWebhookEvent,
  ProviderWebhookEventFilters,
} from '@/lib/integrations-types';

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

// ── Provider accounts ──────────────────────────────────────────────────────────

export async function fetchProviderAccounts(
  params?: ProviderAccountFilters,
): Promise<ProviderAccount[]> {
  return api<ProviderAccount[]>(
    `/admin/integrations/provider-accounts${buildQuery(params as QueryParams)}`,
    auth,
  );
}

export async function fetchProviderAccount(id: string): Promise<ProviderAccount> {
  return api<ProviderAccount>(`/admin/integrations/provider-accounts/${id}`, auth);
}

export async function createProviderAccount(
  payload: ProviderAccountPayload,
): Promise<ProviderAccount> {
  return api<ProviderAccount>('/admin/integrations/provider-accounts', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function updateProviderAccount(
  id: string,
  payload: Partial<ProviderAccountPayload>,
): Promise<ProviderAccount> {
  return api<ProviderAccount>(`/admin/integrations/provider-accounts/${id}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(payload),
  });
}

export async function activateProviderAccount(id: string): Promise<ProviderAccount> {
  return api<ProviderAccount>(`/admin/integrations/provider-accounts/${id}/activate`, {
    ...auth,
    method: 'PATCH',
  });
}

export async function deactivateProviderAccount(id: string): Promise<ProviderAccount> {
  return api<ProviderAccount>(`/admin/integrations/provider-accounts/${id}/deactivate`, {
    ...auth,
    method: 'PATCH',
  });
}

export async function archiveProviderAccount(id: string): Promise<ProviderAccount> {
  return api<ProviderAccount>(`/admin/integrations/provider-accounts/${id}/archive`, {
    ...auth,
    method: 'PATCH',
  });
}

export async function setDefaultProviderAccount(id: string): Promise<ProviderAccount> {
  return api<ProviderAccount>(`/admin/integrations/provider-accounts/${id}/set-default`, {
    ...auth,
    method: 'PATCH',
  });
}

export async function testProviderAccount(id: string): Promise<ProviderAccount> {
  return api<ProviderAccount>(`/admin/integrations/provider-accounts/${id}/test`, {
    ...auth,
    method: 'POST',
  });
}

// ── Provider delivery attempts ─────────────────────────────────────────────────

export async function fetchProviderAttempts(
  params?: ProviderAttemptFilters,
): Promise<ProviderDeliveryAttempt[]> {
  return api<ProviderDeliveryAttempt[]>(
    `/admin/integrations/provider-attempts${buildQuery(params as QueryParams)}`,
    auth,
  );
}

export async function fetchProviderAttempt(id: string): Promise<ProviderDeliveryAttempt> {
  return api<ProviderDeliveryAttempt>(`/admin/integrations/provider-attempts/${id}`, auth);
}

export async function retryProviderAttempt(id: string): Promise<{
  attempt: ProviderDeliveryAttempt;
  result: { provider_reference: string | null; status: string; simulated: boolean };
}> {
  return api(`/admin/integrations/provider-attempts/${id}/retry`, {
    ...auth,
    method: 'POST',
  });
}

// ── Provider webhook events ──────────────────────────────────────────────────────

export async function fetchProviderWebhookEvents(
  params?: ProviderWebhookEventFilters,
): Promise<ProviderWebhookEvent[]> {
  return api<ProviderWebhookEvent[]>(
    `/admin/integrations/provider-events${buildQuery(params as QueryParams)}`,
    auth,
  );
}

export async function fetchProviderWebhookEvent(id: string): Promise<ProviderWebhookEvent> {
  return api<ProviderWebhookEvent>(`/admin/integrations/provider-events/${id}`, auth);
}
