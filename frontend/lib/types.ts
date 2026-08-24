/**
 * NeatMeet OS API types — extend per domain module in Module 1+.
 */

export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export interface ModuleUpgradePayload {
  code?: string;
  module: string;
  module_label: string;
  available_on: Array<{ slug: string; name: string }>;
  suggested_plan_slug: string | null;
  upgrade_href: string;
}

export interface ApiError {
  success: false;
  message: string;
  code?: string;
  data?: ModuleUpgradePayload | null;
  errors?: Record<string, string[]>;
}

export interface HealthCheck {
  status: string;
  service: string;
  checks: {
    database: { status: string; message?: string };
  };
}

export interface VersionInfo {
  name: string;
  version: string;
  api: string;
}

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  is_platform_admin?: boolean;
  platform_role?: 'owner' | 'manager' | 'support' | null;
}

export interface TenantSummary {
  id: string;
  name: string;
  slug: string;
  currency?: string;
}

export interface LoginResponse {
  token: string;
  token_type: string;
  user: AuthUser;
  tenant: TenantSummary | null;
  workspace_incomplete?: boolean;
}

export interface ShellStatus {
  authenticated: boolean;
  user: AuthUser | null;
  tenant: TenantSummary | null;
  workspace_surfaces: string[];
  features?: Record<string, boolean>;
  /** Permission slugs for the current tenant team member (empty if none). */
  permissions?: string[];
  locked_modules?: ModuleUpgradePayload[];
  limits?: {
    max_locations?: number | null;
    max_staff?: number | null;
    max_workspaces?: number | null;
  };
  trial?: {
    active: boolean;
    day: number;
    total_days: number;
    ends_at: string | null;
    label: string;
  };
  onboarding?: {
    availability_set: boolean;
    staff_path: string;
  };
  vapid_public_key?: string | null;
}

/** Public signup wizard field option */
export interface SignupFieldOption {
  value: string;
  label: string;
}

/** Public / platform signup form field definition */
export interface SignupFormField {
  key: string;
  label: string;
  type: 'text' | 'email' | 'tel' | 'select' | 'plan_picker' | string;
  required?: boolean;
  placeholder?: string;
  help?: string;
  options?: SignupFieldOption[];
}

export interface SignupFormStep {
  id: string;
  title: string;
  description?: string;
  fields: SignupFormField[];
}

export interface SignupPlan {
  slug: string;
  name: string;
  description?: string | null;
  display_price_cents: number;
  features?: Record<string, unknown> | null;
  limits?: Record<string, unknown> | null;
  is_default?: boolean;
  locked_until_trial_end?: boolean;
}

/** GET /signup/form */
export interface SignupForm {
  id: string;
  name: string;
  slug: string;
  version: number;
  steps: SignupFormStep[];
  plans: SignupPlan[];
  service_catalogue?: SignupServiceTemplate[];
  trial_days: number;
  default_plan_slug: string;
  basic_max_services?: number;
}

/** Generic service template from GET /signup/form */
export interface SignupServiceTemplate {
  key: string;
  name: string;
  category: string;
  description: string;
  duration_minutes: number;
  base_price_cents: number;
  selected_by_default: boolean;
  business_types?: string[];
}

/** Editable service row in the signup wizard */
export interface SignupServiceDraft {
  key: string;
  name: string;
  category: string;
  description: string;
  duration_minutes: number;
  base_price_cents: number;
  image_url: string | null;
  selected: boolean;
  business_types: string[];
  is_custom?: boolean;
}

export interface SignupRegisterResponse {
  tenant: {
    id: string;
    name: string;
    slug: string;
    status: string;
  };
  activation_sent: boolean;
  message: string;
}

/** Platform admin signup form definition CRUD */
export interface PlatformSignupForm {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  steps: SignupFormStep[];
  is_active: boolean;
  version: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface PlatformTenantRow {
  id: string;
  name: string;
  trading_name: string | null;
  slug: string;
  status: string;
  business_type: string | null;
  timezone: string | null;
  contact_email: string | null;
  owner_email?: string | null;
  owner_whatsapp: string | null;
  plan_name: string | null;
  plan_slug: string | null;
  desired_plan_slug: string | null;
  tier_unlocked: boolean;
  trial_ends_at: string | null;
  staff_count: number;
  created_at: string | null;
  presence?: 'online' | 'offline';
  online?: boolean;
  admin_last_seen_at?: string | null;
  pwa_subscribers?: number;
}

export interface PlatformPwaUserRow {
  id: string;
  type: 'admin' | 'member';
  tenant_id: string;
  tenant_name: string | null;
  tenant_slug: string | null;
  user_id: string | null;
  display_name: string | null;
  email: string | null;
  last_seen_at: string | null;
  created_at: string | null;
}

export interface UnlockTenantTiersResponse {
  tenant_id: string;
  tier_unlocked: boolean;
  tier_unlocked_at: string | null;
  plan_slug: string | null;
  desired_plan_slug: string | null;
  trial_ends_at: string | null;
}

export interface PlatformNotificationItem {
  id: string;
  type: string;
  title: string;
  body: string;
  data: Record<string, unknown> | null;
  read_at: string | null;
  created_at: string | null;
}

export interface PlatformModuleDef {
  key: string;
  label: string;
  description: string;
  core: boolean;
}

export interface PlatformPlanModules {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  features: Record<string, boolean>;
  limits: Record<string, number>;
  display_price_cents: number | null;
  is_active: boolean;
}

export interface PlatformModulesIndex {
  catalogue: PlatformModuleDef[];
  plans: PlatformPlanModules[];
}

export interface TenantModulesState {
  tenant_id: string;
  plan_slug: string | null;
  plan_features: Record<string, boolean>;
  overrides: Record<string, boolean>;
  effective: Record<string, boolean>;
  limits: Record<string, number | null>;
  catalogue: PlatformModuleDef[];
  ai_hairstyle_eligible?: boolean;
  ai_hairstyle_trial_ends_at?: string | null;
}

export interface PlatformUpgradeCampaignSettings {
  is_enabled: boolean;
  discount_percent: number;
  channel_email: boolean;
  channel_whatsapp: boolean;
  channel_in_app: boolean;
}

export interface PlatformReferralSettings {
  enabled: boolean;
  reward_type: string;
  reward_amount: number;
  qualification_goal: string;
  qualification_days: number | null;
  share_headline: string | null;
  share_body: string | null;
  reward_types: string[];
  qualification_goals: string[];
}

export interface PlatformBroadcastResult {
  tenants: number;
  notices: number;
  emails: number;
  pushes: number;
}

export interface PlatformUpgradeTemplate {
  id: string;
  path: string;
  step: string;
  channel: string;
  subject: string | null;
  headline: string | null;
  body_html: string | null;
  body_text: string | null;
  cta_label: string | null;
  image_path: string | null;
  features: Array<{ key?: string; label: string }>;
  use_cases: Array<{ key?: string; label: string; text?: string }>;
  is_active: boolean;
  version: number;
  updated_at: string | null;
}

export interface TenantOwnerNotice {
  id: string;
  type: string;
  title: string;
  body: string;
  image_url: string | null;
  href: string | null;
  data: Record<string, unknown> | null;
  read_at: string | null;
  created_at: string | null;
}

export interface UpgradeOfferPreview {
  code: string;
  percent: number;
  path: string;
  status: string;
  expires_at: string | null;
  trial_ends_at: string | null;
  tenant: {
    id: string;
    name: string;
    slug: string;
  };
}

export interface UpgradeOfferClaimResult {
  code: string;
  percent: number;
  path: string;
  status: string;
  claimed_at: string | null;
  expires_at: string | null;
  message: string;
}
