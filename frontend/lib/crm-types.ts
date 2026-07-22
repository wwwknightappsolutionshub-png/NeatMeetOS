export interface Client {
  id: string;
  tenant_id: string;
  first_name: string;
  last_name: string | null;
  display_name: string | null;
  resolved_display_name: string;
  email: string | null;
  phone: string | null;
  date_of_birth: string | null;
  primary_location_id: string | null;
  primary_location?: { id: string; name: string } | null;
  loyalty_display_status: string | null;
  communication_preferences?: Record<
    string,
    { granted: boolean; recorded_at: string | null; source: string }
  >;
  preferred_team_member_id: string | null;
  preferred_team_member?: { id: string; display_name: string } | null;
  preferences: Record<string, unknown> | null;
  internal_flags: Record<string, unknown> | null;
  is_active: boolean;
  special_event_month: number | null;
  special_event_day: number | null;
  special_event_label: string | null;
  last_visited_at: string | null;
  active_membership?: {
    id: string;
    status: string;
    plan_id: string | null;
    plan_name: string | null;
  } | null;
  tag_ids?: string[];
  tags?: ClientTag[];
  created_at: string | null;
  updated_at: string | null;
}

export interface ClientVisit {
  id: string;
  client_id: string;
  location_id: string | null;
  location?: { id: string; name: string } | null;
  visited_at: string | null;
  source: string | null;
  notes: string | null;
  created_at: string | null;
}

export interface ClientTag {
  id: string;
  name: string;
  slug: string;
  color: string | null;
  is_active: boolean;
}

export interface ClientNote {
  id: string;
  client_id: string;
  note_type: string;
  body: string;
  author_team_member_id: string | null;
  author_name?: string | null;
  created_at: string | null;
}

export interface ClientConsentRecord {
  id: string;
  client_id: string;
  consent_type: string;
  granted: boolean;
  source: string;
  actor_user_id: number | null;
  actor_name?: string | null;
  metadata: Record<string, unknown> | null;
  recorded_at: string | null;
}

export interface ClientConsentState {
  current: Record<
    string,
    { granted: boolean; recorded_at: string | null; source: string }
  >;
  history: ClientConsentRecord[];
}

export interface ClientTimelineEvent {
  id: string;
  client_id: string;
  event_type: string;
  title: string;
  description: string | null;
  payload: Record<string, unknown> | null;
  actor_name?: string | null;
  occurred_at: string | null;
}

export interface PaginatedClients {
  items: Client[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface PaginatedTimeline {
  items: ClientTimelineEvent[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export const CONSENT_TYPES = [
  'marketing_email',
  'marketing_sms',
  'privacy_contact',
] as const;

export const NOTE_TYPES = ['general', 'follow_up', 'internal'] as const;

export const CONSENT_SOURCES = [
  'in_person',
  'online_form',
  'staff_entry',
  'import',
] as const;

export interface ClientFormula {
  id: string;
  client_id: string;
  title: string;
  formula_body: string;
  category: string | null;
  service_context: string | null;
  recorded_by_name?: string | null;
  is_active: boolean;
  created_at: string | null;
  updated_at: string | null;
}

export interface ClientPhoto {
  id: string;
  client_id: string;
  storage_path: string;
  category: string;
  caption: string | null;
  uploaded_by_name?: string | null;
  is_active: boolean;
  created_at: string | null;
}

export interface ClientDocument {
  id: string;
  client_id: string;
  title: string;
  document_type: string;
  storage_path: string;
  description: string | null;
  uploaded_by_name?: string | null;
  is_active: boolean;
  created_at: string | null;
}

export const FORMULA_CATEGORIES = ['colour', 'treatment', 'product_mix', 'other'] as const;

export const PHOTO_CATEGORIES = [
  'profile',
  'reference',
  'formula_reference',
  'history',
] as const;

export const DOCUMENT_TYPES = ['reference', 'signed', 'preference', 'other'] as const;

export const LOYALTY_DISPLAY_STATUSES = ['none', 'member', 'vip'] as const;
