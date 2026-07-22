import { api } from '@/lib/api-client';
import type {
  StaffAbsence,
  StaffAvailabilityRule,
  StaffProfile,
  StaffProvider,
} from '@/lib/staff-types';

const auth = { auth: true as const, tenant: true as const };

export async function fetchStaffProviders(): Promise<StaffProvider[]> {
  return api<StaffProvider[]>('/admin/staff', auth);
}

export async function fetchStaffProvider(teamMemberId: string): Promise<StaffProvider> {
  return api<StaffProvider>(`/admin/staff/${teamMemberId}`, auth);
}

export async function updateStaffProfile(
  teamMemberId: string,
  data: Partial<StaffProfile>,
): Promise<StaffProfile> {
  return api<StaffProfile>(`/admin/staff/${teamMemberId}/profile`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function updateStaffOperatingScope(
  teamMemberId: string,
  data: { location_ids?: string[]; workspace_ids?: string[] },
): Promise<StaffProvider> {
  return api<StaffProvider>(`/admin/staff/${teamMemberId}/operating-scope`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function fetchStaffAvailability(teamMemberId: string): Promise<StaffAvailabilityRule[]> {
  return api<StaffAvailabilityRule[]>(`/admin/staff/${teamMemberId}/availability`, auth);
}

export async function createStaffAvailability(
  teamMemberId: string,
  data: {
    location_id: string;
    workspace_id?: string;
    day_of_week: number;
    start_time: string;
    end_time: string;
  },
): Promise<StaffAvailabilityRule> {
  return api<StaffAvailabilityRule>(`/admin/staff/${teamMemberId}/availability`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function updateStaffAvailability(
  teamMemberId: string,
  ruleId: string,
  data: Partial<{
    location_id: string;
    workspace_id: string | null;
    day_of_week: number;
    start_time: string;
    end_time: string;
  }>,
): Promise<StaffAvailabilityRule> {
  return api<StaffAvailabilityRule>(`/admin/staff/${teamMemberId}/availability/${ruleId}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function archiveStaffAvailability(
  teamMemberId: string,
  ruleId: string,
): Promise<StaffAvailabilityRule> {
  return api<StaffAvailabilityRule>(
    `/admin/staff/${teamMemberId}/availability/${ruleId}/archive`,
    { ...auth, method: 'PATCH' },
  );
}

export async function fetchStaffAbsences(teamMemberId: string): Promise<StaffAbsence[]> {
  return api<StaffAbsence[]>(`/admin/staff/${teamMemberId}/absences`, auth);
}

export async function createStaffAbsence(
  teamMemberId: string,
  data: { category: string; starts_at: string; ends_at: string; note?: string },
): Promise<StaffAbsence> {
  return api<StaffAbsence>(`/admin/staff/${teamMemberId}/absences`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function cancelStaffAbsence(
  teamMemberId: string,
  absenceId: string,
): Promise<StaffAbsence> {
  return api<StaffAbsence>(`/admin/staff/${teamMemberId}/absences/${absenceId}/cancel`, {
    ...auth,
    method: 'PATCH',
  });
}
