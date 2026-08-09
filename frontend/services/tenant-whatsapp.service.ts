import { api } from '@/lib/api-client';

const auth = { auth: true as const, tenant: true as const };

export interface TenantWhatsAppHostedSession {
  session_id: string | null;
  phone_number: string | null;
  status: 'inactive' | 'pending_scan' | 'active' | 'expired' | 'disconnected' | string;
  qr_payload: string | null;
  connected_at: string | null;
  last_seen_at: string | null;
  expires_at: string | null;
  remaining_days: number | null;
  lifecycle_days: number;
}

export interface TenantWhatsAppSettings {
  tenant_id: string;
  enabled: boolean;
  provider: string;
  hosted_session: TenantWhatsAppHostedSession;
  using_platform_fallback: boolean;
  active_source: 'tenant' | 'platform' | 'none' | string;
  active_provider: string;
  ready: boolean;
  platform_api_configured: boolean;
}

async function wrap(path: string, init?: RequestInit): Promise<TenantWhatsAppSettings> {
  const data = await api<{ whatsapp: TenantWhatsAppSettings }>(path, {
    ...auth,
    ...init,
  });
  return data.whatsapp;
}

export function fetchTenantWhatsAppSettings(): Promise<TenantWhatsAppSettings> {
  return wrap('/admin/integrations/whatsapp');
}

export function initTenantWhatsAppSession(): Promise<TenantWhatsAppSettings> {
  return wrap('/admin/integrations/whatsapp/session/init', {
    method: 'POST',
    body: JSON.stringify({}),
  });
}

export function activateTenantWhatsAppSession(phoneNumber: string): Promise<TenantWhatsAppSettings> {
  return wrap('/admin/integrations/whatsapp/session/activate', {
    method: 'POST',
    body: JSON.stringify({ phone_number: phoneNumber }),
  });
}

export function refreshTenantWhatsAppSession(): Promise<TenantWhatsAppSettings> {
  return wrap('/admin/integrations/whatsapp/session/refresh', {
    method: 'POST',
    body: JSON.stringify({}),
  });
}

export function disconnectTenantWhatsAppSession(): Promise<TenantWhatsAppSettings> {
  return wrap('/admin/integrations/whatsapp/session/disconnect', {
    method: 'POST',
    body: JSON.stringify({}),
  });
}

export function qrImageUrl(payload: string): string {
  return `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(payload)}`;
}
