'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { AdminSettingsShell } from '@/components/admin/AdminSettingsShell';
import { BookingQrPanel } from '@/components/booking/BookingQrPanel';
import { EmptyState, ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import type { Location, TenantProfile } from '@/lib/identity-types';
import { fetchLocations, fetchOrganization } from '@/services/identity.service';

export default function BookingQrSettingsPage() {
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
    <AdminSettingsShell title="Booking QR">
      {error ? <ErrorAlert message={error} /> : null}
      {loading ? <LoadingState /> : null}
      {!loading && org ? (
        <div className="space-y-4">
          <Card title="How it works">
            <p className="text-sm text-zinc-600">
              Clients scan the QR to open your public booking page. Single-location salons use one
              QR. Multi-location salons get one QR per location (
              <code className="text-xs">?location=…</code>).
            </p>
          </Card>

          {!multiLocation ? (
            <Card title="Salon booking QR">
              <BookingQrPanel
                tenantSlug={org.slug}
                locationId={locations[0]?.id ?? null}
                locationName={locations[0]?.name ?? null}
                brandName={brandName}
              />
            </Card>
          ) : (
            <>
              <Card title="Tenant booking QR (all locations)">
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
