'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { AdminSettingsShell } from '@/components/admin/AdminSettingsShell';
import { BookingQrPanel } from '@/components/booking/BookingQrPanel';
import { EmptyState, ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import type { Location, TenantProfile } from '@/lib/identity-types';
import { fetchLocations, fetchOrganization } from '@/services/identity.service';

/**
 * Customer QR for membership funnel. Prints the booking page QR.
 * Guests install the PWA from /book, then join inside the member app.
 * Route kept for bookmarks; prefer Settings → Booking QR for the same codes.
 */
export default function CrmJoinQrSettingsPage() {
  const [org, setOrg] = useState<TenantProfile | null>(null);
  const [locations, setLocations] = useState<Location[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([fetchOrganization(), fetchLocations()])
      .then(([organization, locs]) => {
        setOrg(organization);
        setLocations(locs.filter((l) => l.is_active));
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const multiLocation = locations.length > 1;
  const brandName = useMemo(
    () => org?.trading_name || org?.name || org?.slug || 'Salon',
    [org],
  );

  return (
    <AdminSettingsShell title="Customer QR">
      {error ? <ErrorAlert message={error} /> : null}
      {loading ? <LoadingState /> : null}
      {!loading && org ? (
        <div className="space-y-4">
          <Card title="How it works">
            <p className="text-sm text-zinc-600">
              Print this booking QR for customers. Scanning opens the booking page with an install
              gate. After they install the salon app, they join Our Membership Family and log in
              with WhatsApp OTP. Membership join is not a separate public form QR anymore.
            </p>
            <p className="mt-2 font-mono text-xs text-zinc-500">
              /book/{org.slug}
            </p>
            <p className="mt-2 text-xs text-zinc-500">
              Legacy /join/{org.slug} links still redirect into this funnel.
            </p>
          </Card>

          {!multiLocation ? (
            <Card title="Salon customer QR">
              <BookingQrPanel
                tenantSlug={org.slug}
                locationId={locations[0]?.id ?? null}
                locationName={locations[0]?.name ?? null}
                brandName={brandName}
              />
            </Card>
          ) : (
            <>
              <Card title="Tenant customer QR (all locations)">
                <BookingQrPanel tenantSlug={org.slug} brandName={brandName} />
              </Card>
              {locations.length === 0 ? (
                <EmptyState message="No active locations — add a location first." />
              ) : (
                locations.map((location) => (
                  <Card key={location.id} title={`QR · ${location.name}`}>
                    <BookingQrPanel
                      tenantSlug={org.slug}
                      locationId={location.id}
                      locationName={location.name}
                      brandName={brandName}
                    />
                  </Card>
                ))
              )}
            </>
          )}
        </div>
      ) : null}
    </AdminSettingsShell>
  );
}
