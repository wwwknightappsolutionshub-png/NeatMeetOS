import { api } from '@/lib/api-client';
import type { UpgradeOfferClaimResult, UpgradeOfferPreview } from '@/lib/types';

export async function fetchUpgradeOffer(token: string): Promise<UpgradeOfferPreview> {
  const q = new URLSearchParams({ token });
  return api<UpgradeOfferPreview>(`/upgrade-offer?${q.toString()}`, {
    auth: true,
    tenant: false,
  });
}

export async function claimUpgradeOffer(token: string): Promise<UpgradeOfferClaimResult> {
  return api<UpgradeOfferClaimResult>('/upgrade-offer/claim', {
    method: 'POST',
    auth: true,
    tenant: false,
    body: JSON.stringify({ token }),
  });
}
