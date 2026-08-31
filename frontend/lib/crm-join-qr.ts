/**
 * Salon customer QR → public CRM join form at /join/{slug}.
 * Optional ?location= pins a branch on multi-location tenants.
 */
export function buildCrmJoinPageUrl(
  tenantSlug: string,
  options?: { locationId?: string | null; origin?: string },
): string {
  const origin =
    options?.origin ??
    (typeof window !== 'undefined' ? window.location.origin : '');
  const path = `/join/${encodeURIComponent(tenantSlug)}`;
  const url = new URL(path, origin || 'http://localhost:3000');
  if (options?.locationId) {
    url.searchParams.set('location', options.locationId);
  }
  return url.toString();
}

export function crmJoinQrFilename(tenantSlug: string, locationLabel?: string | null): string {
  const safeSlug = tenantSlug.replace(/[^a-z0-9-_]/gi, '-').toLowerCase();
  const safeLoc = locationLabel
    ? `-${locationLabel.replace(/[^a-z0-9-_]/gi, '-').toLowerCase()}`
    : '';
  return `neatmeet-crm-join-qr-${safeSlug}${safeLoc}.png`;
}
