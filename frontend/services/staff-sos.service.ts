import { api } from '@/lib/api-client';

const auth = { auth: true as const, tenant: true as const };

export type StaffSosKind = 'new_booking' | 'approaching';
export type StaffSosStatus = 'active' | 'acknowledged' | 'resolved';

export interface StaffSosAlert {
  id: string;
  kind: StaffSosKind;
  status: StaffSosStatus;
  title: string;
  body: string | null;
  payload: Record<string, unknown>;
  allow_shift: boolean;
  shift_minutes: number[];
  appointment: {
    id: string;
    booking_reference: string | null;
    starts_at: string | null;
    client_name: string | null;
    provider_name: string | null;
    location_name: string | null;
    services: string[];
  } | null;
  created_at: string | null;
  acknowledged_at: string | null;
}

export async function fetchActiveStaffSosAlerts(): Promise<StaffSosAlert[]> {
  const data = await api<{ items: StaffSosAlert[] }>('/admin/staff-sos-alerts', auth);
  return data.items ?? [];
}

export async function acknowledgeStaffSosAlert(id: string): Promise<StaffSosAlert> {
  return api<StaffSosAlert>(`/admin/staff-sos-alerts/${encodeURIComponent(id)}/acknowledge`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify({}),
  });
}

export async function shiftStaffSosAppointment(
  id: string,
  minutes: number,
): Promise<{ alert: StaffSosAlert; appointment_id: string; starts_at: string | null }> {
  return api(`/admin/staff-sos-alerts/${encodeURIComponent(id)}/shift`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify({ minutes }),
  });
}
