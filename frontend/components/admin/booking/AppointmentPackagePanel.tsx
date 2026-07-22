'use client';

import { useEffect, useState } from 'react';
import { Card } from '@/components/ui/Card';
import type { AppointmentPackageSummary } from '@/lib/booking-types';
import { formatMoneyCents } from '@/lib/memberships-types';
import {
  fetchAppointmentPackageSummary,
  releaseAppointmentPackage,
  reserveAppointmentPackage,
} from '@/services/booking.service';

interface AppointmentPackagePanelProps {
  appointmentId: string;
  clientId?: string | null;
}

export function AppointmentPackagePanel({ appointmentId, clientId }: AppointmentPackagePanelProps) {
  const [summary, setSummary] = useState<AppointmentPackageSummary | null>(null);
  const [loading, setLoading] = useState(false);

  const reload = () => fetchAppointmentPackageSummary(appointmentId).then(setSummary);

  useEffect(() => {
    if (!clientId) return;
    reload().catch(() => setSummary(null));
  }, [appointmentId, clientId]);

  if (!clientId) {
    return (
      <Card title="Package entitlements">
        <p className="text-sm text-zinc-500">Assign a client to reserve package entitlements.</p>
      </Card>
    );
  }

  return (
    <Card title="Package entitlements">
      {!summary ? (
        <p className="text-sm text-zinc-500">Loading…</p>
      ) : (
        <div className="space-y-3 text-sm">
          {summary.service_lines.map((line) => (
            <div key={line.id} className="rounded border border-zinc-200 p-2">
              <p className="font-medium">{line.service_name}</p>
              <p className="text-zinc-500">{formatMoneyCents(line.price_cents ?? 0)}</p>
              {line.client_package_redemption_id ? (
                <p className="text-emerald-700">
                  Reserved
                  {line.covered_amount_cents ? ` — covers ${formatMoneyCents(line.covered_amount_cents)}` : ''}
                </p>
              ) : null}
              {!line.client_package_redemption_id && summary.eligible_packages.length > 0 ? (
                <div className="mt-2 flex gap-2">
                  <select id={`appt-pkg-${line.id}`} className="w-full rounded border border-zinc-300 px-2 py-1" defaultValue="">
                    <option value="" disabled>
                      Select package
                    </option>
                    {summary.eligible_packages.map((pkg) => (
                      <option key={pkg.id} value={pkg.id}>
                        {pkg.package_name} ({pkg.quantity_remaining} left)
                      </option>
                    ))}
                  </select>
                  <button
                    type="button"
                    className="rounded bg-emerald-700 px-3 py-1 text-white disabled:opacity-50"
                    disabled={loading}
                    onClick={async () => {
                      const select = document.getElementById(`appt-pkg-${line.id}`) as HTMLSelectElement;
                      if (!select?.value) return;
                      setLoading(true);
                      try {
                        const updated = await reserveAppointmentPackage(appointmentId, line.id, select.value);
                        setSummary(updated);
                      } finally {
                        setLoading(false);
                      }
                    }}
                  >
                    Reserve
                  </button>
                </div>
              ) : null}
              {line.client_package_redemption_id ? (
                <button
                  type="button"
                  className="mt-2 rounded border border-zinc-300 px-3 py-1"
                  disabled={loading}
                  onClick={async () => {
                    setLoading(true);
                    try {
                      const updated = await releaseAppointmentPackage(appointmentId, line.id);
                      setSummary(updated);
                    } finally {
                      setLoading(false);
                    }
                  }}
                >
                  Release
                </button>
              ) : null}
            </div>
          ))}
        </div>
      )}
    </Card>
  );
}
