/**
 * Customer QR still uses the booking page. Join happens after PWA install.
 * Legacy /join/{slug} URLs redirect to /book/{slug}.
 */
export function buildCrmJoinPageUrl(
  tenantSlug: string,
  options?: { locationId?: string | null; origin?: string },
): string {
  const origin =
    options?.origin ??
    (typeof window !== 'undefined' ? window.location.origin : '');
  const path = `/book/${encodeURIComponent(tenantSlug)}`;
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
