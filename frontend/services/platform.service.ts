import { api } from '@/lib/api-client';
import type { PaginatedAuditLogs } from '@/lib/identity-types';
import type {
  PlatformModulesIndex,
  PlatformNotificationItem,
  PlatformSignupForm,
  PlatformTenantRow,
  PlatformUpgradeCampaignSettings,
  PlatformUpgradeTemplate,
  PlatformReferralSettings,
  PlatformBroadcastResult,
  PlatformPwaUserRow,
  SignupFormStep,
  TenantModulesState,
  UnlockTenantTiersResponse,
} from '@/lib/types';

export type { PlatformTenantRow, PlatformSignupForm };

export interface PlatformOverview {
  tenants_total: number;
  tenants_active: number;
  tenants_trial: number;
  tenants_suspended: number;
  users_total: number;
  team_members_total: number;
  clients_total: number;
  appointments_last_7d: number;
  payments_collected_last_7d_cents: number;
}

export async function fetchPlatformOverview(): Promise<PlatformOverview> {
  return api<PlatformOverview>('/platform/overview', { auth: true, tenant: false });
}

export async function fetchPlatformTenants(params?: {
  search?: string;
  status?: string;
}): Promise<PlatformTenantRow[]> {
  const search = new URLSearchParams();
  if (params?.search) search.set('search', params.search);
  if (params?.status) search.set('status', params.status);
  const q = search.toString();
  return api<PlatformTenantRow[]>(`/platform/tenants${q ? `?${q}` : ''}`, {
    auth: true,
    tenant: false,
  });
}

export async function unlockTenantTiers(
  tenantId: string,
  activatePlanSlug?: 'basic' | 'pro' | 'diamond' | null,
): Promise<UnlockTenantTiersResponse> {
  return api<UnlockTenantTiersResponse>(
    `/platform/tenants/${tenantId}/unlock-tiers`,
    {
      method: 'POST',
      auth: true,
      tenant: false,
      body: JSON.stringify(
        activatePlanSlug ? { activate_plan_slug: activatePlanSlug } : {},
      ),
    },
  );
}

export async function fetchPlatformNotifications(): Promise<{
  items: PlatformNotificationItem[];
  unread_count: number;
}> {
  return api('/platform/notifications', { auth: true, tenant: false });
}

export async function fetchPlatformUnreadCount(): Promise<{ unread_count: number }> {
  return api('/platform/notifications/unread-count', { auth: true, tenant: false });
}

export async function markPlatformNotificationRead(
  id: string,
): Promise<{ unread_count: number }> {
  return api(`/platform/notifications/${id}/read`, {
    method: 'POST',
    auth: true,
    tenant: false,
  });
}

export async function markAllPlatformNotificationsRead(): Promise<{
  marked: number;
  unread_count: number;
}> {
  return api('/platform/notifications/read-all', {
    method: 'POST',
    auth: true,
    tenant: false,
  });
}

export async function fetchPlatformModules(): Promise<PlatformModulesIndex> {
  return api<PlatformModulesIndex>('/platform/modules', {
    auth: true,
    tenant: false,
  });
}

export async function updatePlanModules(
  planId: string,
  payload: {
    features: Record<string, boolean>;
    limits?: {
      max_locations?: number;
      max_staff?: number;
      max_workspaces?: number;
    };
  },
): Promise<{
  id: string;
  slug: string;
  features: Record<string, boolean>;
  limits: Record<string, number>;
}> {
  return api(`/platform/plans/${planId}/modules`, {
    method: 'PUT',
    auth: true,
    tenant: false,
    body: JSON.stringify(payload),
  });
}

export async function fetchTenantModules(
  tenantId: string,
): Promise<TenantModulesState> {
  return api<TenantModulesState>(`/platform/tenants/${tenantId}/modules`, {
    auth: true,
    tenant: false,
  });
}

export async function updateTenantModules(
  tenantId: string,
  overrides: Record<string, boolean | null>,
): Promise<TenantModulesState> {
  return api<TenantModulesState>(`/platform/tenants/${tenantId}/modules`, {
    method: 'PUT',
    auth: true,
    tenant: false,
    body: JSON.stringify({ overrides }),
  });
}

export async function fetchPlatformSignupForms(): Promise<PlatformSignupForm[]> {
  return api<PlatformSignupForm[]>('/platform/signup-forms', {
    auth: true,
    tenant: false,
  });
}

export async function fetchPlatformSignupForm(
  id: string,
): Promise<PlatformSignupForm> {
  return api<PlatformSignupForm>(`/platform/signup-forms/${id}`, {
    auth: true,
    tenant: false,
  });
}

export async function createPlatformSignupForm(payload: {
  name: string;
  slug?: string;
  description?: string | null;
  steps: SignupFormStep[];
  is_active?: boolean;
}): Promise<PlatformSignupForm> {
  return api<PlatformSignupForm>('/platform/signup-forms', {
    method: 'POST',
    auth: true,
    tenant: false,
    body: JSON.stringify(payload),
  });
}

export async function updatePlatformSignupForm(
  id: string,
  payload: Partial<{
    name: string;
    slug: string;
    description: string | null;
    steps: SignupFormStep[];
    is_active: boolean;
  }>,
): Promise<PlatformSignupForm> {
  return api<PlatformSignupForm>(`/platform/signup-forms/${id}`, {
    method: 'PUT',
    auth: true,
    tenant: false,
    body: JSON.stringify(payload),
  });
}

export async function deletePlatformSignupForm(id: string): Promise<null> {
  return api<null>(`/platform/signup-forms/${id}`, {
    method: 'DELETE',
    auth: true,
    tenant: false,
  });
}

export async function fetchPlatformAuditLogs(params?: {
  tenant_id?: string;
  entity_type?: string;
  action?: string;
  from?: string;
  to?: string;
  page?: number;
}): Promise<PaginatedAuditLogs> {
  const search = new URLSearchParams();
  if (params?.tenant_id) search.set('tenant_id', params.tenant_id);
  if (params?.entity_type) search.set('entity_type', params.entity_type);
  if (params?.action) search.set('action', params.action);
  if (params?.from) search.set('from', params.from);
  if (params?.to) search.set('to', params.to);
  if (params?.page) search.set('page', String(params.page));
  const query = search.toString();

  return api(`/platform/audit-logs${query ? `?${query}` : ''}`, {
    auth: true,
    tenant: false,
  });
}

export async function fetchUpgradeCampaignSettings(): Promise<PlatformUpgradeCampaignSettings> {
  return api<PlatformUpgradeCampaignSettings>('/platform/upgrade-campaigns/settings', {
    auth: true,
    tenant: false,
  });
}

export async function updateUpgradeCampaignSettings(
  payload: Partial<PlatformUpgradeCampaignSettings>,
): Promise<PlatformUpgradeCampaignSettings> {
  return api<PlatformUpgradeCampaignSettings>('/platform/upgrade-campaigns/settings', {
    method: 'PUT',
    auth: true,
    tenant: false,
    body: JSON.stringify(payload),
  });
}

export async function fetchUpgradeCampaignTemplates(): Promise<PlatformUpgradeTemplate[]> {
  return api<PlatformUpgradeTemplate[]>('/platform/upgrade-campaigns/templates', {
    auth: true,
    tenant: false,
  });
}

export async function updateUpgradeCampaignTemplate(
  id: string,
  payload: Partial<
    Pick<
      PlatformUpgradeTemplate,
      | 'subject'
      | 'headline'
      | 'body_html'
      | 'body_text'
      | 'cta_label'
      | 'image_path'
      | 'features'
      | 'use_cases'
      | 'is_active'
    >
  >,
): Promise<PlatformUpgradeTemplate> {
  return api<PlatformUpgradeTemplate>(`/platform/upgrade-campaigns/templates/${id}`, {
    method: 'PUT',
    auth: true,
    tenant: false,
    body: JSON.stringify(payload),
  });
}

export async function dispatchUpgradeCampaign(payload: {
  tenant_id: string;
  step: 'day_3' | 'day_7' | 'day_21';
  force?: boolean;
}): Promise<{ sent: number; skipped: number; channels: string[] }> {
  return api('/platform/upgrade-campaigns/dispatch', {
    method: 'POST',
    auth: true,
    tenant: false,
    body: JSON.stringify(payload),
  });
}

export async function sendPlatformBroadcast(payload: {
  title: string;
  body: string;
  href?: string | null;
  tenant_id?: string | null;
  send_email?: boolean;
  send_push?: boolean;
}): Promise<PlatformBroadcastResult> {
  return api<PlatformBroadcastResult>('/platform/broadcasts', {
    method: 'POST',
    auth: true,
    tenant: false,
    body: JSON.stringify(payload),
  });
}

export async function fetchPlatformReferralSettings(): Promise<PlatformReferralSettings> {
  return api<PlatformReferralSettings>('/platform/referral-settings', {
    auth: true,
    tenant: false,
  });
}

export async function updatePlatformReferralSettings(
  payload: Partial<
    Pick<
      PlatformReferralSettings,
      | 'enabled'
      | 'reward_type'
      | 'reward_amount'
      | 'qualification_goal'
      | 'qualification_days'
      | 'share_headline'
      | 'share_body'
    >
  >,
): Promise<PlatformReferralSettings> {
  return api<PlatformReferralSettings>('/platform/referral-settings', {
    method: 'PUT',
    auth: true,
    tenant: false,
    body: JSON.stringify(payload),
  });
}

export async function pokeTenant(
  tenantId: string,
  message?: string,
): Promise<{ tenants: number; notices: number; emails: number; pushes: number }> {
  return api(`/platform/tenants/${tenantId}/poke`, {
    method: 'POST',
    auth: true,
    tenant: false,
    body: JSON.stringify(message ? { message } : {}),
  });
}

export async function fetchPlatformPwaUsers(type?: 'admin' | 'member'): Promise<PlatformPwaUserRow[]> {
  const q = type ? `?type=${type}` : '';
  return api<PlatformPwaUserRow[]>(`/platform/pwa-users${q}`, {
    auth: true,
    tenant: false,
  });
}

export async function pushPlatformPwaUsers(payload: {
  title: string;
  body: string;
  url?: string | null;
  type?: 'admin' | 'member' | null;
  subscription_ids?: string[];
}): Promise<{ sent: number; failed: number; skipped: number; targeted: number }> {
  return api('/platform/pwa-users/push', {
    method: 'POST',
    auth: true,
    tenant: false,
    body: JSON.stringify(payload),
  });
}
