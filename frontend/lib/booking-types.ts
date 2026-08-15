export interface BookableService {
  id: string;
  name: string;
  category: string | null;
  description: string | null;
  image_url: string | null;
  duration_minutes: number;
  base_price_cents: number | null;
  membership_price_cents: number | null;
  loyalty_price_cents: number | null;
  is_active: boolean;
  is_bookable_online: boolean;
  display_order: number;
  deposit_required: boolean;
  deposit_amount_cents: number | null;
  min_lead_time_hours: number | null;
  cancellation_window_hours: number | null;
}

export interface AppointmentServiceLine {
  id?: string;
  booking_service_id: string | null;
  service_name: string;
  duration_minutes: number;
  price_cents: number | null;
  sort_order: number;
  package_entitlement_id?: string | null;
  entitlement_source?: string | null;
  entitlement_state?: string | null;
  client_package_id?: string | null;
  client_package_redemption_id?: string | null;
  covered_quantity?: number | null;
  covered_amount_cents?: number;
}

export interface AppointmentPackageSummary {
  appointment_id: string;
  client_id: string | null;
  eligible_packages: import('@/lib/memberships-types').ClientPackage[];
  service_lines: Array<{
    id: string;
    booking_service_id: string | null;
    service_name?: string;
    price_cents: number | null;
    entitlement_state?: string | null;
    client_package_id?: string | null;
    client_package_redemption_id?: string | null;
    covered_quantity?: number | null;
    covered_amount_cents?: number;
  }>;
}

export interface Appointment {
  id: string;
  location_id: string;
  location?: { id: string; name: string };
  client_id: string;
  client?: { id: string; resolved_display_name: string };
  team_member_id: string;
  team_member?: { id: string; display_name: string };
  workspace_id: string | null;
  workspace?: { id: string; name: string; workspace_type: string } | null;
  starts_at: string;
  ends_at: string;
  status: string;
  booking_source: string;
  client_notes: string | null;
  internal_notes: string | null;
  cancelled_at: string | null;
  cancellation_reason: string | null;
  booking_reference: string | null;
  public_manage_token?: string | null;
  manage_path?: string | null;
  manage_url?: string | null;
  recurrence_series_id: string | null;
  occurrence_index: number | null;
  deposit_status: string;
  deposit_required_cents: number | null;
  deposit_rule_snapshot: Record<string, unknown> | null;
  walk_in_stage: string | null;
  arrived_at: string | null;
  no_show_reason: string | null;
  status_correction_note: string | null;
  rebooked_from_appointment_id: string | null;
  recurrence_series?: { id: string; pattern: string; status: string } | null;
  services?: AppointmentServiceLine[];
}

export interface RecurrenceSeriesResult {
  series: {
    id: string;
    pattern: string;
    status: string;
    occurrence_count: number | null;
    appointments?: Appointment[];
  };
  created_appointment_ids: string[];
  skipped: { starts_at: string; reason: string }[];
}

export interface WaitlistEntry {
  id: string;
  client_id: string;
  client?: { id: string; resolved_display_name: string };
  location_id: string;
  location?: { id: string; name: string };
  team_member_id: string | null;
  team_member?: { id: string; display_name: string } | null;
  workspace_id: string | null;
  workspace_type_preference: string | null;
  preferred_starts_at: string | null;
  preferred_ends_at: string | null;
  availability_notes: string | null;
  status: string;
  notes: string | null;
  fulfilled_appointment_id: string | null;
  contacted_at: string | null;
  services?: { booking_service_id: string; service_name: string; sort_order: number }[];
  created_at: string | null;
}

export const APPOINTMENT_STATUSES = [
  'pending',
  'confirmed',
  'checked_in',
  'completed',
  'cancelled',
  'no_show',
] as const;

export interface BookingDayBoard {
  date: string;
  summary: {
    total: number;
    by_status: Record<string, number>;
    walk_ins_waiting: number;
  };
  workspace_occupancy: {
    workspace_id: string;
    workspace_name: string;
    workspace_type: string;
    appointments: number;
  }[];
  appointments: Appointment[];
}

export const BOOKING_SOURCES = [
  'admin',
  'internal',
  'waitlist',
  'walk_in',
  'online',
] as const;

export interface OnlineBookingLocation {
  id: string;
  name: string;
  slug: string;
  timezone: string | null;
  address?: {
    line1?: string;
    line2?: string;
    city?: string;
    county?: string;
    postcode?: string;
    country?: string;
  } | null;
  contact_phone?: string | null;
  opening_hours?: Array<{
    day_of_week: number;
    start_time: string | null;
    end_time: string | null;
    is_closed?: boolean;
  }> | null;
}

export interface OnlineBookingProvider {
  id: string;
  display_name: string;
  primary_location_id: string | null;
}

export interface OnlineBookingCatalog {
  tenant: {
    id: string | null;
    name: string | null;
    slug: string | null;
    owner_whatsapp?: string | null;
    branding?: {
      brand_display_name?: string | null;
      logo_url?: string | null;
      primary_color?: string | null;
      secondary_color?: string | null;
      support_email?: string | null;
      support_phone?: string | null;
      hero_emblem_mode?: 'none' | 'logo' | 'custom' | null;
      hero_emblem_url?: string | null;
      hero_image_url?: string | null;
      store_status?: 'auto' | 'open' | 'opening_soon' | 'closing' | 'closed' | null;
      social_facebook_url?: string | null;
      social_instagram_url?: string | null;
      social_tiktok_url?: string | null;
    };
  };
  locations: OnlineBookingLocation[];
  services: BookableService[];
  providers: OnlineBookingProvider[];
  /** When true, show AI look landing gate before the booking flow. */
  ai_hairstyle_landing?: boolean;
  booking_policy?: BookingPolicySummary | null;
}

export interface BookingPolicySummary {
  min_advance_notice_minutes: number;
  free_change_window_minutes: number;
  late_cancel_fee_percent: number;
  free_window_reminder_lead_minutes: number;
  approval_reminder_interval_minutes: number;
  approval_reminder_max_count: number;
}

export interface BookingChangeRequest {
  id: string;
  appointment_id: string;
  type: 'cancel' | 'postpone';
  initiated_by: 'customer' | 'tenant';
  status: 'pending' | 'accepted' | 'declined' | 'auto_accepted';
  decline_allowed: boolean;
  late_fee_applies: boolean;
  late_fee_cents: number | null;
  proposed_starts_at: string | null;
  reason: string | null;
  reminder_count: number;
  action_links?: {
    manage_path: string;
    manage_url: string;
    accept_url: string;
    decline_url: string;
  } | null;
  appointment?: {
    id: string;
    booking_reference: string | null;
    status: string;
    starts_at: string | null;
    ends_at: string | null;
    client?: { id: string; display_name: string; phone: string | null } | null;
  } | null;
}

export interface OnlineBookingSlot {
  starts_at: string;
  ends_at: string;
  team_member_id: string;
  location_id: string;
  workspace_id: string | null;
  provider_name: string | null;
}

export interface OnlineBookPayload {
  booking_service_id: string;
  location_id: string;
  team_member_id: string;
  workspace_id?: string | null;
  starts_at: string;
  first_name?: string;
  last_name?: string;
  email?: string;
  phone: string;
  /** Guest opted in to WhatsApp booking updates. */
  whatsapp_opt_in?: boolean;
  client_notes?: string;
  pricing_tier?: 'regular' | 'membership' | 'loyalty';
  member_token?: string;
}

export const WALK_IN_STAGES = ['waiting', 'seated'] as const;

export const DEPOSIT_STATUSES = [
  'not_required',
  'pending',
  'satisfied',
  'waived',
  'failed',
] as const;

export const WAITLIST_STATUSES = [
  'waiting',
  'contacted',
  'unreachable',
  'booked',
  'expired',
  'cancelled',
] as const;
