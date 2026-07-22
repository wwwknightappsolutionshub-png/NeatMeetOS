import {
  api,
  API_BASE,
  getStoredTenantSlug,
  getStoredToken,
} from '@/lib/api-client';
import type { Appointment, BookableService, BookingDayBoard, RecurrenceSeriesResult, WaitlistEntry } from '@/lib/booking-types';

const auth = { auth: true as const, tenant: true as const };

export async function uploadBookableServiceImage(
  file: File,
): Promise<{ url: string; path: string }> {
  const form = new FormData();
  form.append('image', file);

  const headers: HeadersInit = { Accept: 'application/json' };
  const token = getStoredToken();
  if (token) headers.Authorization = `Bearer ${token}`;
  const slug = getStoredTenantSlug();
  if (slug) headers['X-Tenant-Slug'] = slug;

  const res = await fetch(`${API_BASE}/admin/booking-services/upload-image`, {
    method: 'POST',
    headers,
    body: form,
    credentials: 'omit',
  });

  const json = (await res.json()) as {
    success: boolean;
    message: string;
    data?: { url: string; path: string };
    errors?: Record<string, string[]>;
  };

  if (!res.ok || !json.success || !json.data) {
    const firstError = json.errors
      ? Object.values(json.errors).flat()[0]
      : undefined;
    throw new Error(firstError || json.message || 'Upload failed');
  }

  return json.data;
}

export async function fetchBookableServices(activeOnly = true): Promise<BookableService[]> {
  const query = activeOnly ? '?active_only=1' : '?active_only=0';
  return api<BookableService[]>(`/admin/booking-services${query}`, auth);
}

export async function createBookableService(
  data: Partial<BookableService>,
): Promise<BookableService> {
  return api<BookableService>('/admin/booking-services', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function updateBookableService(
  id: string,
  data: Partial<BookableService>,
): Promise<BookableService> {
  return api<BookableService>(`/admin/booking-services/${id}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function archiveBookableService(id: string): Promise<BookableService> {
  return api<BookableService>(`/admin/booking-services/${id}/archive`, {
    ...auth,
    method: 'PATCH',
  });
}

export async function fetchAppointments(params?: {
  from?: string;
  to?: string;
  location_id?: string;
  team_member_id?: string;
  status?: string;
}): Promise<Appointment[]> {
  const search = new URLSearchParams();
  if (params?.from) search.set('from', params.from);
  if (params?.to) search.set('to', params.to);
  if (params?.location_id) search.set('location_id', params.location_id);
  if (params?.team_member_id) search.set('team_member_id', params.team_member_id);
  if (params?.status) search.set('status', params.status);
  const query = search.toString();

  return api<Appointment[]>(`/admin/appointments${query ? `?${query}` : ''}`, auth);
}

export async function fetchAppointment(id: string): Promise<Appointment> {
  return api<Appointment>(`/admin/appointments/${id}`, auth);
}

export async function createAppointment(data: {
  client_id: string;
  team_member_id: string;
  location_id: string;
  workspace_id?: string;
  starts_at: string;
  status?: string;
  client_notes?: string;
  internal_notes?: string;
  services: { booking_service_id: string }[];
}): Promise<Appointment> {
  return api<Appointment>('/admin/appointments', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function updateAppointment(
  id: string,
  data: Partial<{
    client_id: string;
    team_member_id: string;
    location_id: string;
    workspace_id: string | null;
    starts_at: string;
    client_notes: string;
    internal_notes: string;
    services: { booking_service_id: string }[];
  }>,
): Promise<Appointment> {
  return api<Appointment>(`/admin/appointments/${id}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function updateAppointmentStatus(
  id: string,
  status: string,
  no_show_reason?: string,
): Promise<Appointment> {
  return api<Appointment>(`/admin/appointments/${id}/status`, {
    ...auth,
    method: 'PATCH',
    body: JSON.stringify({ status, no_show_reason }),
  });
}

export async function correctAppointmentStatus(
  id: string,
  status: string,
  correction_note: string,
): Promise<Appointment> {
  return api<Appointment>(`/admin/appointments/${id}/correct-status`, {
    ...auth,
    method: 'PATCH',
    body: JSON.stringify({ status, correction_note }),
  });
}

export async function rebookAppointment(
  id: string,
  data: {
    starts_at: string;
    team_member_id?: string;
    location_id?: string;
    workspace_id?: string;
    client_notes?: string;
    internal_notes?: string;
    status?: string;
  },
): Promise<Appointment> {
  return api<Appointment>(`/admin/appointments/${id}/rebook`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function reassignAppointmentWorkspace(
  id: string,
  workspace_id: string | null,
): Promise<Appointment> {
  return api<Appointment>(`/admin/appointments/${id}/workspace`, {
    ...auth,
    method: 'PATCH',
    body: JSON.stringify({ workspace_id }),
  });
}

export async function fetchBookingDayBoard(params?: {
  date?: string;
  location_id?: string;
  team_member_id?: string;
  status?: string;
  booking_source?: string;
}): Promise<BookingDayBoard> {
  const search = new URLSearchParams();
  if (params?.date) search.set('date', params.date);
  if (params?.location_id) search.set('location_id', params.location_id);
  if (params?.team_member_id) search.set('team_member_id', params.team_member_id);
  if (params?.status) search.set('status', params.status);
  if (params?.booking_source) search.set('booking_source', params.booking_source);
  const query = search.toString();
  return api<BookingDayBoard>(`/admin/booking-board/day${query ? `?${query}` : ''}`, auth);
}

export async function fetchWalkIns(params?: {
  walk_in_stage?: string;
  location_id?: string;
}): Promise<Appointment[]> {
  const search = new URLSearchParams();
  if (params?.walk_in_stage) search.set('walk_in_stage', params.walk_in_stage);
  if (params?.location_id) search.set('location_id', params.location_id);
  const query = search.toString();
  return api<Appointment[]>(`/admin/walk-ins${query ? `?${query}` : ''}`, auth);
}

export async function createWalkIn(data: {
  client_id: string;
  location_id: string;
  team_member_id?: string;
  workspace_id?: string;
  seat_immediately?: boolean;
  client_notes?: string;
  internal_notes?: string;
  services: { booking_service_id: string }[];
}): Promise<Appointment> {
  return api<Appointment>('/admin/walk-ins', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function seatWalkIn(
  id: string,
  data: {
    team_member_id: string;
    workspace_id?: string;
    starts_at?: string;
  },
): Promise<Appointment> {
  return api<Appointment>(`/admin/walk-ins/${id}/seat`, {
    ...auth,
    method: 'PATCH',
    body: JSON.stringify(data),
  });
}

export async function cancelAppointment(
  id: string,
  cancellation_reason?: string,
): Promise<Appointment> {
  return api<Appointment>(`/admin/appointments/${id}/cancel`, {
    ...auth,
    method: 'PATCH',
    body: JSON.stringify({ cancellation_reason }),
  });
}

export async function updateAppointmentDepositStatus(
  id: string,
  deposit_status: string,
): Promise<Appointment> {
  return api<Appointment>(`/admin/appointments/${id}/deposit-status`, {
    ...auth,
    method: 'PATCH',
    body: JSON.stringify({ deposit_status }),
  });
}

export async function createRecurrenceSeries(data: {
  client_id: string;
  team_member_id: string;
  location_id: string;
  workspace_id?: string;
  starts_at: string;
  interval_weeks?: number;
  occurrence_count?: number;
  end_date?: string;
  services: { booking_service_id: string }[];
}): Promise<RecurrenceSeriesResult> {
  return api<RecurrenceSeriesResult>('/admin/recurrence-series', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function fetchWaitlist(params?: {
  status?: string;
  location_id?: string;
  team_member_id?: string;
  from?: string;
  to?: string;
  booking_service_id?: string;
}): Promise<WaitlistEntry[]> {
  const search = new URLSearchParams();
  if (params?.status) search.set('status', params.status);
  if (params?.location_id) search.set('location_id', params.location_id);
  if (params?.team_member_id) search.set('team_member_id', params.team_member_id);
  if (params?.from) search.set('from', params.from);
  if (params?.to) search.set('to', params.to);
  if (params?.booking_service_id) search.set('booking_service_id', params.booking_service_id);
  const query = search.toString();
  return api<WaitlistEntry[]>(`/admin/waitlist${query ? `?${query}` : ''}`, auth);
}

export async function createWaitlistEntry(data: {
  client_id: string;
  location_id: string;
  team_member_id?: string;
  workspace_id?: string;
  workspace_type_preference?: string;
  preferred_starts_at?: string;
  availability_notes?: string;
  notes?: string;
  services?: { booking_service_id: string }[];
}): Promise<WaitlistEntry> {
  return api<WaitlistEntry>('/admin/waitlist', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function updateWaitlistEntry(
  id: string,
  data: Partial<WaitlistEntry>,
): Promise<WaitlistEntry> {
  return api<WaitlistEntry>(`/admin/waitlist/${id}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function fulfillWaitlistEntry(
  id: string,
  data: {
    starts_at: string;
    team_member_id?: string;
    location_id?: string;
    workspace_id?: string;
  },
): Promise<{ waitlist: WaitlistEntry; appointment: Appointment }> {
  return api<{ waitlist: WaitlistEntry; appointment: Appointment }>(
    `/admin/waitlist/${id}/fulfill`,
    { ...auth, method: 'POST', body: JSON.stringify(data) },
  );
}

export async function fetchAppointmentPackageSummary(appointmentId: string): Promise<import('@/lib/booking-types').AppointmentPackageSummary> {
  return api(`/admin/appointments/${appointmentId}/eligible-packages`, auth);
}

export async function reserveAppointmentPackage(
  appointmentId: string,
  serviceLineId: string,
  clientPackageId: string,
): Promise<import('@/lib/booking-types').AppointmentPackageSummary> {
  return api(`/admin/appointments/${appointmentId}/service-lines/${serviceLineId}/package-reserve`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify({ client_package_id: clientPackageId }),
  });
}

export async function releaseAppointmentPackage(
  appointmentId: string,
  serviceLineId: string,
): Promise<import('@/lib/booking-types').AppointmentPackageSummary> {
  return api(`/admin/appointments/${appointmentId}/service-lines/${serviceLineId}/package-release`, {
    ...auth,
    method: 'POST',
  });
}
