export interface StaffProfile {
  id: string;
  team_member_id: string;
  is_bookable: boolean;
  show_in_online_booking: boolean;
  accepts_walk_ins: boolean;
  booking_display_name: string | null;
  internal_notes: string | null;
  default_workspace_id: string | null;
  default_workspace?: { id: string; name: string; workspace_type: string } | null;
  min_lead_time_minutes: number | null;
  buffer_minutes: number | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface StaffProvider {
  id: string;
  tenant_id: string;
  display_name: string;
  first_name: string | null;
  last_name: string | null;
  employment_type: string;
  is_active: boolean;
  is_bookable: boolean;
  primary_location_id: string | null;
  primary_location?: { id: string; name: string } | null;
  profile?: StaffProfile | null;
  operating_location_ids?: string[];
  operating_locations?: { id: string; name: string }[];
  workspace_ids?: string[];
  workspaces?: {
    id: string;
    name: string;
    workspace_type: string;
    location_id: string;
  }[];
}

export interface StaffAvailabilityRule {
  id: string;
  team_member_id: string;
  location_id: string;
  location?: { id: string; name: string };
  workspace_id: string | null;
  workspace?: { id: string; name: string } | null;
  day_of_week: number;
  start_time: string;
  end_time: string;
  is_active: boolean;
  created_at: string | null;
  updated_at: string | null;
}

export interface StaffAbsence {
  id: string;
  team_member_id: string;
  category: string;
  starts_at: string;
  ends_at: string;
  note: string | null;
  status: string;
  created_at: string | null;
  updated_at: string | null;
}

export const DAYS_OF_WEEK: Record<number, string> = {
  1: 'Monday',
  2: 'Tuesday',
  3: 'Wednesday',
  4: 'Thursday',
  5: 'Friday',
  6: 'Saturday',
  7: 'Sunday',
};

export const ABSENCE_CATEGORIES = [
  'holiday',
  'sickness',
  'unavailable',
  'training',
  'other',
] as const;
