'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { AdminSettingsShell } from '@/components/admin/AdminSettingsShell';
import { CrmJoinQrPanel } from '@/components/crm/CrmJoinQrPanel';
import { EmptyState, ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import type { Location, TenantProfile } from '@/lib/identity-types';
import { fetchLocations, fetchOrganization } from '@/services/identity.service';

/**
 * Salon customer QR — public CRM join form at /join/{slug}.
 * Distinct from Settings → Booking QR which opens online booking.
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
              Print this QR for walk-in and lobby customers. Scanning opens the salon CRM join
              form — they save their details (WhatsApp required), then can open the member app
              or book online.
            </p>
            <p className="mt-2 font-mono text-xs text-zinc-500">
              /join/{org.slug}
            </p>
            <p className="mt-2 text-xs text-zinc-500">
              For appointment booking only, use Settings → Booking QR (
              <span className="font-mono">/book/{org.slug}</span>).
            </p>
          </Card>

          {!multiLocation ? (
            <Card title="Salon customer QR">
              <CrmJoinQrPanel
                tenantSlug={org.slug}
                locationId={locations[0]?.id ?? null}
                locationName={locations[0]?.name ?? null}
                brandName={brandName}
              />
            </Card>
          ) : (
            <>
              <Card title="Tenant customer QR (all locations)">
                <CrmJoinQrPanel tenantSlug={org.slug} brandName={brandName} />
              </Card>
              {locations.length === 0 ? (
                <EmptyState message="No active locations — add a location first." />
              ) : (
                locations.map((location) => (
                  <Card key={location.id} title={`QR · ${location.name}`}>
                    <CrmJoinQrPanel
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
