/** sessionStorage key so Skip survives refresh within the tab session. */
export function aiHairstyleSkipStorageKey(tenantSlug: string): string {
  return `neatmeet:ai_hairstyle_skip:${tenantSlug}`;
}

export function hasSkippedAiHairstyleLanding(tenantSlug: string): boolean {
  if (typeof window === 'undefined') return false;
  try {
    return window.sessionStorage.getItem(aiHairstyleSkipStorageKey(tenantSlug)) === '1';
  } catch {
    return false;
  }
}

export function markAiHairstyleLandingSkipped(tenantSlug: string): void {
  if (typeof window === 'undefined') return;
  try {
    window.sessionStorage.setItem(aiHairstyleSkipStorageKey(tenantSlug), '1');
  } catch {
    // Ignore quota / private-mode failures; gate may reappear until storage works.
  }
}
