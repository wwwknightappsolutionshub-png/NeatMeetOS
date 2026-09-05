export type SalonGrowthAssessmentAnswers = {
  knows_last_month_visitors: string;
  knows_how_many_returned: string;
  tracking_method: string;
  knows_when_due_return: string;
  return_percentage_band: string;
  encourage_return_methods: string[];
  avg_spend_band: string;
  knows_missed_revenue: string;
  uses_software: string;
  software_helps_with?: string[];
  software_satisfaction?: string;
  staff_band?: string;
  customers_per_month_band?: string;
  business_type?: string;
  business_name?: string;
};

export type SalonGrowthAssessmentSubmitPayload = {
  business_name: string;
  business_type: string;
  staff_band?: string;
  customers_per_month_band: string;
  contact_name: string;
  email: string;
  phone: string;
  postcode?: string;
  marketing_consent: boolean;
  send_whatsapp?: boolean;
  source?: string;
  referral_code?: string | null;
  hp_trap?: string;
  answers: SalonGrowthAssessmentAnswers;
};

export type SalonGrowthAssessmentResult = {
  public_token: string;
  business_name: string;
  assessed_at: string | null;
  score_overall: number;
  score_visibility: number;
  score_retention: number;
  score_revenue_visibility: number;
  score_reengagement: number;
  estimated_opportunity_cents: number;
  estimated_opportunity_display: string;
  primary_opportunity: string;
  primary_opportunity_label: string;
  opportunity_narrative: string;
  neatmeet_capabilities: string[];
  estimate_disclaimer: string;
  email_delivery_status: string;
  whatsapp_delivery_status: string;
  indicative_note: string;
};

export type PlatformGrowthAssessmentRow = {
  id: string;
  business_name: string;
  business_type: string;
  contact_name: string | null;
  email: string | null;
  phone: string | null;
  postcode: string | null;
  score_overall: number;
  estimated_opportunity_cents: number;
  estimated_opportunity_display: string;
  primary_opportunity_label: string | null;
  uses_software: string | null;
  software_satisfaction: string | null;
  lead_status: string;
  email_delivery_status: string;
  whatsapp_delivery_status: string;
  assigned_platform_user: { id: string; name: string; email: string } | null;
  created_at: string | null;
  next_follow_up_on: string | null;
};

export type PlatformGrowthAssessmentList = {
  items: PlatformGrowthAssessmentRow[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  lead_statuses: string[];
};

export type PlatformGrowthAssessmentDetail = {
  id: string;
  public_token: string;
  business_name: string;
  business_type: string;
  staff_band: string | null;
  customers_per_month_band: string | null;
  contact_name: string | null;
  email: string | null;
  phone: string | null;
  phone_normalized: string | null;
  postcode: string | null;
  marketing_consent: boolean;
  answers: Record<string, unknown>;
  score_overall: number;
  score_visibility: number;
  score_retention: number;
  score_revenue_visibility: number;
  score_reengagement: number;
  estimated_opportunity_cents: number;
  estimated_opportunity_display: string;
  primary_opportunity: string | null;
  primary_opportunity_label: string | null;
  sales_conversation_hint: string | null;
  uses_software: string | null;
  software_helps_with: string[] | null;
  software_satisfaction: string | null;
  tracking_methods: string | null;
  lead_status: string;
  assigned_platform_user: { id: string; name: string; email: string } | null;
  internal_notes: string | null;
  last_contacted_at: string | null;
  next_follow_up_on: string | null;
  email_delivery_status: string;
  email_sent_at: string | null;
  whatsapp_delivery_status: string;
  whatsapp_sent_at: string | null;
  whatsapp_delivery_error: string | null;
  source: string;
  referral_code: string | null;
  created_at: string | null;
  prospect_opportunity: {
    tier: string;
    label: string;
    growth_score: number;
    estimated_opportunity_display: string;
    current_system: string;
    main_weakness: string | null;
    suggested_sales_conversation: string | null;
  };
};

export type PlatformGrowthAssessmentLeadUpdate = {
  lead_status?: string;
  assigned_platform_user_id?: string | null;
  internal_notes?: string | null;
  last_contacted_at?: string | null;
  next_follow_up_on?: string | null;
};
