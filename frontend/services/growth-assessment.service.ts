import { api } from '@/lib/api-client';
import { getTurnstileToken, withTurnstileToken } from '@/lib/turnstile';
import type {
  SalonGrowthAssessmentResult,
  SalonGrowthAssessmentSubmitPayload,
  PlatformGrowthAssessmentDetail,
  PlatformGrowthAssessmentList,
  PlatformGrowthAssessmentLeadUpdate,
} from '@/lib/growth-assessment-types';

export async function submitSalonGrowthAssessment(
  payload: SalonGrowthAssessmentSubmitPayload,
): Promise<SalonGrowthAssessmentResult> {
  const turnstile_token = await getTurnstileToken();
  return api<SalonGrowthAssessmentResult>('/growth-assessments', {
    method: 'POST',
    body: JSON.stringify(withTurnstileToken(payload, turnstile_token)),
    auth: false,
    tenant: false,
  });
}

export async function fetchSalonGrowthAssessmentResult(
  token: string,
): Promise<SalonGrowthAssessmentResult> {
  return api<SalonGrowthAssessmentResult>(
    `/growth-assessments/${encodeURIComponent(token)}`,
    { auth: false, tenant: false },
  );
}

export async function requestSalonGrowthAssessmentWhatsApp(
  token: string,
): Promise<{ whatsapp_delivery_status: string; whatsapp_delivery_error: string | null }> {
  const turnstile_token = await getTurnstileToken();
  return api(`/growth-assessments/${encodeURIComponent(token)}/whatsapp`, {
    method: 'POST',
    body: JSON.stringify(withTurnstileToken({}, turnstile_token)),
    auth: false,
    tenant: false,
  });
}

export async function fetchPlatformGrowthAssessments(params?: {
  lead_status?: string;
  business_type?: string;
  uses_software?: string;
  postcode?: string;
  score_min?: string;
  score_max?: string;
  opportunity_min_cents?: string;
  from?: string;
  to?: string;
  q?: string;
  sort?: string;
  dir?: string;
  per_page?: number;
  page?: number;
}): Promise<PlatformGrowthAssessmentList> {
  const qs = new URLSearchParams();
  if (params) {
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== '') qs.set(k, String(v));
    });
  }
  const suffix = qs.toString() ? `?${qs}` : '';
  return api<PlatformGrowthAssessmentList>(`/platform/growth-assessments${suffix}`, {
    auth: true,
    tenant: false,
  });
}

export async function fetchPlatformGrowthAssessment(
  id: string,
): Promise<PlatformGrowthAssessmentDetail> {
  return api<PlatformGrowthAssessmentDetail>(`/platform/growth-assessments/${id}`, {
    auth: true,
    tenant: false,
  });
}

export async function updatePlatformGrowthAssessment(
  id: string,
  payload: PlatformGrowthAssessmentLeadUpdate,
): Promise<PlatformGrowthAssessmentDetail> {
  return api<PlatformGrowthAssessmentDetail>(`/platform/growth-assessments/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
    auth: true,
    tenant: false,
  });
}
