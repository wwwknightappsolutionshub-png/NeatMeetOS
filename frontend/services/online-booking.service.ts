import { api } from '@/lib/api-client';
import type {
  Appointment,
  BookingChangeRequest,
  OnlineBookPayload,
  OnlineBookingCatalog,
  OnlineBookingSlot,
} from '@/lib/booking-types';

function publicOpts(tenantSlug: string, init?: RequestInit) {
  return {
    auth: false as const,
    tenant: false as const,
    headers: { 'X-Tenant-Slug': tenantSlug },
    ...init,
  };
}

export async function fetchOnlineCatalog(
  tenantSlug: string,
  locationId?: string,
): Promise<OnlineBookingCatalog> {
  const query = locationId ? `?location_id=${encodeURIComponent(locationId)}` : '';
  return api<OnlineBookingCatalog>(`/book/catalog${query}`, publicOpts(tenantSlug));
}

export async function fetchOnlineSlots(
  tenantSlug: string,
  params: {
    booking_service_id: string;
    location_id: string;
    date: string;
    team_member_id?: string;
  },
): Promise<OnlineBookingSlot[]> {
  const search = new URLSearchParams({
    booking_service_id: params.booking_service_id,
    location_id: params.location_id,
    date: params.date,
  });
  if (params.team_member_id) search.set('team_member_id', params.team_member_id);
  const data = await api<{ slots: OnlineBookingSlot[] }>(
    `/book/slots?${search.toString()}`,
    publicOpts(tenantSlug),
  );
  return data.slots;
}

export async function createOnlineAppointment(
  tenantSlug: string,
  payload: OnlineBookPayload,
): Promise<Appointment> {
  return api<Appointment>('/book/appointments', {
    ...publicOpts(tenantSlug, {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  });
}

export async function fetchManagedAppointment(
  tenantSlug: string,
  bookingReference: string,
  token: string,
): Promise<Appointment> {
  const search = new URLSearchParams({ token });
  return api<Appointment>(
    `/book/appointments/${encodeURIComponent(bookingReference)}?${search.toString()}`,
    publicOpts(tenantSlug),
  );
}

export async function cancelManagedAppointment(
  tenantSlug: string,
  bookingReference: string,
  token: string,
  reason?: string,
): Promise<BookingChangeRequest> {
  return api<BookingChangeRequest>(
    `/book/appointments/${encodeURIComponent(bookingReference)}/cancel`,
    {
      ...publicOpts(tenantSlug, {
        method: 'POST',
        body: JSON.stringify({ token, reason }),
      }),
    },
  );
}

export async function fetchBookingChangeRequest(
  tenantSlug: string,
  id: string,
  token: string,
): Promise<BookingChangeRequest> {
  const search = new URLSearchParams({ id, token });
  return api<BookingChangeRequest>(`/book/change-requests?${search.toString()}`, publicOpts(tenantSlug));
}

export async function resolveBookingChangeRequest(
  tenantSlug: string,
  id: string,
  token: string,
  action: 'accept' | 'decline',
): Promise<BookingChangeRequest> {
  return api<BookingChangeRequest>('/book/change-requests/resolve', {
    ...publicOpts(tenantSlug, {
      method: 'POST',
      body: JSON.stringify({ id, token, action }),
    }),
  });
}
