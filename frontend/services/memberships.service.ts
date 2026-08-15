import { api } from '@/lib/api-client';
import type {
  ClientMembership,
  ClientPackage,
  LoyaltyEntry,
  MembershipPlan,
  MembershipSummary,
  PackageProduct,
  WalletEntry,
} from '@/lib/memberships-types';

const auth = { auth: true as const, tenant: true as const };

export async function fetchMembershipSummary(): Promise<MembershipSummary> {
  return api<MembershipSummary>('/admin/memberships/summary', auth);
}

export async function fetchMembershipPlans(): Promise<MembershipPlan[]> {
  return api<MembershipPlan[]>('/admin/memberships/plans', auth);
}

export async function createMembershipPlan(data: Partial<MembershipPlan>): Promise<MembershipPlan> {
  return api<MembershipPlan>('/admin/memberships/plans', { ...auth, method: 'POST', body: JSON.stringify(data) });
}

export async function updateMembershipPlan(id: string, data: Partial<MembershipPlan>): Promise<MembershipPlan> {
  return api<MembershipPlan>(`/admin/memberships/plans/${id}`, { ...auth, method: 'PUT', body: JSON.stringify(data) });
}

export async function archiveMembershipPlan(id: string): Promise<MembershipPlan> {
  return api<MembershipPlan>(`/admin/memberships/plans/${id}/archive`, { ...auth, method: 'PATCH' });
}

export async function fetchPackageProducts(): Promise<PackageProduct[]> {
  return api<PackageProduct[]>('/admin/memberships/packages', auth);
}

export async function createPackageProduct(data: Partial<PackageProduct> & { price_cents: number; included_quantity: number }): Promise<PackageProduct> {
  return api<PackageProduct>('/admin/memberships/packages', { ...auth, method: 'POST', body: JSON.stringify(data) });
}

export async function updatePackageProduct(
  id: string,
  data: Partial<PackageProduct> & { price_cents?: number; included_quantity?: number },
): Promise<PackageProduct> {
  return api<PackageProduct>(`/admin/memberships/packages/${id}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function archivePackageProduct(id: string): Promise<PackageProduct> {
  return api<PackageProduct>(`/admin/memberships/packages/${id}/archive`, { ...auth, method: 'PATCH' });
}

export async function fetchClientMemberships(params?: { client_id?: string }): Promise<ClientMembership[]> {
  const search = new URLSearchParams();
  if (params?.client_id) search.set('client_id', params.client_id);
  const q = search.toString();
  return api<ClientMembership[]>(`/admin/memberships/client-memberships${q ? `?${q}` : ''}`, auth);
}

export async function createClientMembership(data: { client_id: string; membership_plan_id: string; notes?: string }): Promise<ClientMembership> {
  return api<ClientMembership>('/admin/memberships/client-memberships', { ...auth, method: 'POST', body: JSON.stringify(data) });
}

export async function pauseClientMembership(id: string): Promise<ClientMembership> {
  return api<ClientMembership>(`/admin/memberships/client-memberships/${id}/pause`, { ...auth, method: 'PATCH' });
}

export async function resumeClientMembership(id: string): Promise<ClientMembership> {
  return api<ClientMembership>(`/admin/memberships/client-memberships/${id}/resume`, { ...auth, method: 'PATCH' });
}

export async function cancelClientMembership(id: string, atPeriodEnd = false): Promise<ClientMembership> {
  return api<ClientMembership>(`/admin/memberships/client-memberships/${id}/cancel`, {
    ...auth,
    method: 'PATCH',
    body: JSON.stringify({ at_period_end: atPeriodEnd }),
  });
}

export async function fetchWalletEntries(clientId?: string): Promise<WalletEntry[]> {
  const q = clientId ? `?client_id=${clientId}` : '';
  return api<WalletEntry[]>(`/admin/memberships/wallet-entries${q}`, auth);
}

export async function createWalletEntry(data: { client_id: string; direction: 'credit' | 'debit'; amount_cents: number; notes?: string }): Promise<WalletEntry> {
  return api<WalletEntry>('/admin/memberships/wallet-entries', { ...auth, method: 'POST', body: JSON.stringify(data) });
}

export async function fetchClientWallet(clientId: string): Promise<{ balance_cents: number; entries: WalletEntry[] }> {
  return api(`/admin/memberships/clients/${clientId}/wallet`, auth);
}

export async function fetchLoyaltyEntries(clientId?: string): Promise<LoyaltyEntry[]> {
  const q = clientId ? `?client_id=${clientId}` : '';
  return api<LoyaltyEntry[]>(`/admin/memberships/loyalty-entries${q}`, auth);
}

export async function createLoyaltyEntry(data: { client_id: string; direction: 'credit' | 'debit'; points: number; notes?: string }): Promise<LoyaltyEntry> {
  return api<LoyaltyEntry>('/admin/memberships/loyalty-entries', { ...auth, method: 'POST', body: JSON.stringify(data) });
}

export async function fetchClientLoyalty(clientId: string): Promise<{ points_balance: number; entries: LoyaltyEntry[] }> {
  return api(`/admin/memberships/clients/${clientId}/loyalty`, auth);
}

export async function fetchClientPackages(clientId?: string): Promise<ClientPackage[]> {
  const q = clientId ? `?client_id=${clientId}` : '';
  return api<ClientPackage[]>(`/admin/memberships/client-packages${q}`, auth);
}

export async function assignClientPackage(data: { client_id: string; package_product_id: string; notes?: string }): Promise<ClientPackage> {
  return api<ClientPackage>('/admin/memberships/client-packages', { ...auth, method: 'POST', body: JSON.stringify(data) });
}

export async function redeemClientPackage(id: string, quantity: number): Promise<ClientPackage> {
  return api<ClientPackage>(`/admin/memberships/client-packages/${id}/redeem`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify({ quantity }),
  });
}

export async function restoreClientPackage(id: string, quantity: number): Promise<ClientPackage> {
  return api<ClientPackage>(`/admin/memberships/client-packages/${id}/restore`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify({ quantity }),
  });
}

export interface LoyaltyRedemptionSettings {
  is_loyalty_redemption_enabled: boolean;
  points_per_redemption_block: number;
  value_cents_per_block: number;
  crm_join_signup_points: number;
}

export async function fetchLoyaltyRedemptionSettings(): Promise<LoyaltyRedemptionSettings> {
  return api<LoyaltyRedemptionSettings>('/admin/memberships/settings/loyalty-redemption', auth);
}

export async function updateLoyaltyRedemptionSettings(data: Partial<LoyaltyRedemptionSettings>): Promise<LoyaltyRedemptionSettings> {
  return api<LoyaltyRedemptionSettings>('/admin/memberships/settings/loyalty-redemption', {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}
