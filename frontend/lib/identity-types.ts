export interface TenantProfile {
  id: string;
  name: string;
  trading_name: string | null;
  slug: string;
  status: string;
  business_type: string | null;
  timezone: string;
  contact_email: string | null;
  contact_phone: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface Address {
  line1?: string;
  line2?: string;
  city?: string;
  county?: string;
  postcode?: string;
  country?: string;
}

export interface LocationOpeningHour {
  day_of_week: number;
  start_time: string | null;
  end_time: string | null;
  is_closed?: boolean;
}

export interface Location {
  id: string;
  tenant_id: string;
  name: string;
  slug: string;
  timezone: string;
  address: Address | null;
  contact_email: string | null;
  contact_phone: string | null;
  opening_hours?: LocationOpeningHour[] | null;
  latitude: number | null;
  longitude: number | null;
  geofence_radius_meters: number | null;
  is_active: boolean;
}

export interface Workspace {
  id: string;
  tenant_id: string;
  location_id: string;
  location?: Location;
  name: string;
  code: string | null;
  workspace_type: string;
  metadata: Record<string, unknown> | null;
  is_active: boolean;
}

export interface TeamMember {
  id: string;
  tenant_id: string;
  user_id: number;
  email?: string;
  first_name: string | null;
  last_name: string | null;
  display_name: string;
  phone: string | null;
  employment_type: string;
  primary_location_id: string | null;
  workspace_ids?: string[];
  role_ids?: string[];
  roles?: Role[];
  is_active: boolean;
}

export interface Role {
  id: string;
  name: string;
  slug: string;
  is_system: boolean;
  is_active?: boolean;
  permission_ids?: string[];
  team_member_ids?: string[];
  team_member_count?: number;
}

export interface Permission {
  id: string;
  name: string;
  slug: string;
  module: string;
}

export interface PermissionGroup {
  module: string;
  permissions: Permission[];
}

export interface BrandingSettings {
  brand_display_name: string | null;
  logo_url: string | null;
  primary_color: string;
  secondary_color: string;
  receipt_display_name: string | null;
  support_email: string | null;
  support_phone: string | null;
  hero_emblem_mode: 'none' | 'logo' | 'custom';
  hero_emblem_url: string | null;
  hero_image_url: string | null;
  store_status: 'auto' | 'open' | 'opening_soon' | 'closing' | 'closed';
  social_facebook_url: string | null;
  social_instagram_url: string | null;
  social_tiktok_url: string | null;
}

export interface SubscriptionPlan {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  billing_interval: string;
  features: Record<string, unknown> | null;
  limits: Record<string, number> | null;
  display_price_cents: number | null;
  is_active: boolean;
}

export interface TenantSubscription {
  id: string;
  tenant_id: string;
  status: string;
  billing_interval: string;
  trial_ends_at: string | null;
  current_period_start: string | null;
  current_period_end: string | null;
  provider: string | null;
  plan?: SubscriptionPlan;
}

export interface AuditLogEntry {
  id: string;
  tenant_id?: string | null;
  tenant?: {
    id: string;
    name: string;
    slug: string;
  } | null;
  action: string;
  entity_type: string | null;
  entity_id: string | null;
  actor_type: string | null;
  actor_id: string | null;
  actor_name?: string | null;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  ip_address: string | null;
  created_at: string | null;
}

export interface PaginatedAuditLogs {
  items: AuditLogEntry[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export const WORKSPACE_TYPES = [
  'chair',
  'room',
  'station',
  'seat',
  'slot',
] as const;

export const EMPLOYMENT_TYPES = [
  'owner',
  'employee',
  'freelancer',
  'chair_renter',
  'room_renter',
] as const;
