import { api, API_BASE, getStoredTenantSlug, getStoredToken } from '@/lib/api-client';
import type {
  BrandingSettings,
  Location,
  PermissionGroup,
  Role,
  SubscriptionPlan,
  TeamMember,
  TenantProfile,
  TenantSubscription,
  Workspace,
} from '@/lib/identity-types';
import type { TenantOwnerNotice } from '@/lib/types';

const auth = { auth: true as const, tenant: true as const };

export async function fetchOrganization(): Promise<TenantProfile> {
  return api<TenantProfile>('/admin/organization', auth);
}

export async function updateOrganization(
  data: Partial<TenantProfile>,
): Promise<TenantProfile> {
  return api<TenantProfile>('/admin/organization', {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function fetchLocations(): Promise<Location[]> {
  return api<Location[]>('/admin/locations', auth);
}

export async function createLocation(
  data: Partial<Location>,
): Promise<Location> {
  return api<Location>('/admin/locations', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function updateLocation(
  id: string,
  data: Partial<Location>,
): Promise<Location> {
  return api<Location>(`/admin/locations/${id}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function setLocationStatus(
  id: string,
  is_active: boolean,
): Promise<Location> {
  return api<Location>(`/admin/locations/${id}/status`, {
    ...auth,
    method: 'PATCH',
    body: JSON.stringify({ is_active }),
  });
}

export async function fetchWorkspaces(locationId?: string): Promise<Workspace[]> {
  const query = locationId ? `?location_id=${locationId}` : '';
  return api<Workspace[]>(`/admin/workspaces${query}`, auth);
}

export async function createWorkspace(
  data: Partial<Workspace>,
): Promise<Workspace> {
  return api<Workspace>('/admin/workspaces', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function updateWorkspace(
  id: string,
  data: Partial<Workspace>,
): Promise<Workspace> {
  return api<Workspace>(`/admin/workspaces/${id}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function setWorkspaceStatus(
  id: string,
  is_active: boolean,
): Promise<Workspace> {
  return api<Workspace>(`/admin/workspaces/${id}/status`, {
    ...auth,
    method: 'PATCH',
    body: JSON.stringify({ is_active }),
  });
}

export async function fetchTeamMembers(): Promise<TeamMember[]> {
  return api<TeamMember[]>('/admin/team-members', auth);
}

export async function createTeamMember(
  data: Record<string, unknown>,
): Promise<TeamMember> {
  return api<TeamMember>('/admin/team-members', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function updateTeamMember(
  id: string,
  data: Record<string, unknown>,
): Promise<TeamMember> {
  return api<TeamMember>(`/admin/team-members/${id}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function setTeamMemberStatus(
  id: string,
  is_active: boolean,
): Promise<TeamMember> {
  return api<TeamMember>(`/admin/team-members/${id}/status`, {
    ...auth,
    method: 'PATCH',
    body: JSON.stringify({ is_active }),
  });
}

export async function fetchRoles(): Promise<Role[]> {
  return api<Role[]>('/admin/roles', auth);
}

export async function updateTeamMemberRoles(
  teamMemberId: string,
  role_ids: string[],
): Promise<TeamMember> {
  return api<TeamMember>(`/admin/team-members/${teamMemberId}/roles`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify({ role_ids }),
  });
}

export async function fetchBranding(): Promise<BrandingSettings> {
  return api<BrandingSettings>('/admin/branding', auth);
}

export async function updateBranding(
  data: Partial<BrandingSettings>,
): Promise<BrandingSettings> {
  return api<BrandingSettings>('/admin/branding', {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function uploadBrandingEmblem(
  file: File,
): Promise<{ url: string; path: string; branding: BrandingSettings }> {
  const form = new FormData();
  form.append('image', file);

  const headers: HeadersInit = { Accept: 'application/json' };
  const token = getStoredToken();
  if (token) headers.Authorization = `Bearer ${token}`;
  const slug = getStoredTenantSlug();
  if (slug) headers['X-Tenant-Slug'] = slug;

  const res = await fetch(`${API_BASE}/admin/branding/upload-emblem`, {
    method: 'POST',
    headers,
    body: form,
    credentials: 'omit',
  });

  const json = (await res.json()) as {
    success: boolean;
    message: string;
    data?: { url: string; path: string; branding: BrandingSettings };
    errors?: Record<string, string[]>;
  };

  if (!res.ok || !json.success || !json.data) {
    const firstError = json.errors
      ? Object.values(json.errors).flat()[0]
      : undefined;
    throw new Error(firstError || json.message || 'Upload failed');
  }

  return json.data;
}

export async function uploadBrandingHeroImage(
  file: File,
): Promise<{ url: string; path: string; branding: BrandingSettings }> {
  const form = new FormData();
  form.append('image', file);

  const headers: HeadersInit = { Accept: 'application/json' };
  const token = getStoredToken();
  if (token) headers.Authorization = `Bearer ${token}`;
  const slug = getStoredTenantSlug();
  if (slug) headers['X-Tenant-Slug'] = slug;

  const res = await fetch(`${API_BASE}/admin/branding/upload-hero`, {
    method: 'POST',
    headers,
    body: form,
    credentials: 'omit',
  });

  const json = (await res.json()) as {
    success: boolean;
    message: string;
    data?: { url: string; path: string; branding: BrandingSettings };
    errors?: Record<string, string[]>;
  };

  if (!res.ok || !json.success || !json.data) {
    const firstError = json.errors
      ? Object.values(json.errors).flat()[0]
      : undefined;
    throw new Error(firstError || json.message || 'Upload failed');
  }

  return json.data;
}

export async function fetchPermissions(): Promise<PermissionGroup[]> {
  return api<PermissionGroup[]>('/admin/permissions', auth);
}

export async function createRole(data: {
  name: string;
  slug?: string;
  permission_ids?: string[];
}): Promise<Role> {
  return api<Role>('/admin/roles', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function updateRole(
  id: string,
  data: { name?: string; slug?: string },
): Promise<Role> {
  return api<Role>(`/admin/roles/${id}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function archiveRole(id: string): Promise<Role> {
  return api<Role>(`/admin/roles/${id}/archive`, {
    ...auth,
    method: 'PATCH',
  });
}

export async function updateRolePermissions(
  id: string,
  permission_ids: string[],
): Promise<Role> {
  return api<Role>(`/admin/roles/${id}/permissions`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify({ permission_ids }),
  });
}

export async function fetchSubscription(): Promise<TenantSubscription> {
  return api<TenantSubscription>('/admin/subscription', auth);
}

export async function fetchSubscriptionPlans(): Promise<SubscriptionPlan[]> {
  return api<SubscriptionPlan[]>('/admin/subscription/plans', auth);
}

export async function fetchOwnerNotices(): Promise<{
  items: TenantOwnerNotice[];
  unread_count: number;
}> {
  return api('/admin/owner-notices', auth);
}

export async function markOwnerNoticeRead(id: string): Promise<{ id: string; read_at: string | null }> {
  return api(`/admin/owner-notices/${id}/read`, {
    method: 'POST',
    ...auth,
  });
}

