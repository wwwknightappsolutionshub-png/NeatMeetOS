/**
 * Build absolute public booking URLs for QR encoding.
 * Single-location: /book/{slug}
 * Multi-location: /book/{slug}?location={id}
 */
export function buildBookingPageUrl(
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

export function bookingQrFilename(tenantSlug: string, locationLabel?: string | null): string {
  const safeSlug = tenantSlug.replace(/[^a-z0-9-_]/gi, '-').toLowerCase();
  const safeLoc = locationLabel
    ? `-${locationLabel.replace(/[^a-z0-9-_]/gi, '-').toLowerCase()}`
    : '';
  return `neatmeet-booking-qr-${safeSlug}${safeLoc}.png`;
}
