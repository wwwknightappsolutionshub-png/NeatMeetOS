'use client';

import { useState } from 'react';
import { Card } from '@/components/ui/Card';
import type { EligibleAppointment } from '@/lib/pos-types';
import { formatMoneyCents } from '@/lib/pos-types';

interface AppointmentImportPanelProps {
  appointments: EligibleAppointment[];
  onImport: (appointmentId: string) => Promise<void>;
  disabled?: boolean;
}

export function AppointmentImportPanel({ appointments, onImport, disabled }: AppointmentImportPanelProps) {
  const [loading, setLoading] = useState<string | null>(null);
  const eligible = appointments.filter((a) => a.checkout_eligible);

  return (
    <Card title="Import appointment">
      {eligible.length === 0 ? (
        <p className="text-sm text-zinc-500">No eligible checked-in appointments for this location.</p>
      ) : (
        <ul className="space-y-2">
          {eligible.map((appt) => (
            <li key={appt.id} className="flex flex-wrap items-center justify-between gap-2 rounded border border-zinc-200 p-3 text-sm">
              <div>
                <p className="font-medium">{appt.booking_reference} · {appt.client_name}</p>
                <p className="text-zinc-500">{appt.service_count} service(s) · {formatMoneyCents(appt.import_subtotal_cents)}</p>
                {(appt.deposit_available_cents ?? 0) > 0 ? (
                  <p className="text-emerald-700">Deposit available: {formatMoneyCents(appt.deposit_available_cents ?? 0)}</p>
                ) : null}
              </div>
              <button
                type="button"
                disabled={disabled || loading === appt.id}
                className="rounded bg-zinc-900 px-3 py-1.5 text-white disabled:opacity-50"
                onClick={async () => {
                  setLoading(appt.id);
                  try {
                    await onImport(appt.id);
                  } finally {
                    setLoading(null);
                  }
                }}
              >
                Import
              </button>
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}
