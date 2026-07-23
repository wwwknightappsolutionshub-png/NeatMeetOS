import { api, setStoredTenantSlug, setStoredToken } from '@/lib/api-client';
import type {
  LoginResponse,
  SignupForm,
  SignupRegisterResponse,
  SignupServiceDraft,
} from '@/lib/types';

const API_BASE =
  process.env.NEXT_PUBLIC_API_URL?.replace(/\/$/, '') ??
  'http://localhost:8000/api/v1';

export interface AddressSuggestion {
  label: string;
  address_line1: string;
  city: string;
  postcode: string;
  country: string;
}

export async function fetchSignupForm(): Promise<SignupForm> {
  return api<SignupForm>('/signup/form', { tenant: false });
}

export async function lookupAddressByPostcode(
  postcode: string,
): Promise<{
  postcode: string;
  formatted_postcode: string | null;
  suggestions: AddressSuggestion[];
}> {
  const q = encodeURIComponent(postcode.trim());
  return api(`/signup/address-lookup?postcode=${q}`, { tenant: false });
}

export async function registerSignup(
  answers: Record<string, unknown>,
): Promise<SignupRegisterResponse> {
  return api<SignupRegisterResponse>('/signup/register', {
    method: 'POST',
    body: JSON.stringify({ answers }),
    tenant: false,
  });
}

export interface SignupLeadResponse {
  status: 'created' | 'resent' | 'existing';
  message: string;
  login_url: string;
  temporary_password?: string | null;
}

export async function captureSignupLead(payload: {
  name: string;
  email: string;
  referral_code?: string | null;
  website?: string;
}): Promise<SignupLeadResponse> {
  return api<SignupLeadResponse>('/signup/lead', {
    method: 'POST',
    body: JSON.stringify({
      name: payload.name,
      email: payload.email,
      referral_code: payload.referral_code || undefined,
      website: payload.website || '',
    }),
    tenant: false,
  });
}

export async function completeWorkspaceSignup(
  answers: Record<string, unknown>,
): Promise<LoginResponse> {
  const data = await api<LoginResponse>('/signup/complete-workspace', {
    method: 'POST',
    body: JSON.stringify({ answers }),
    auth: true,
    tenant: false,
  });
  setStoredToken(data.token);
  if (data.tenant?.slug) setStoredTenantSlug(data.tenant.slug);
  return data;
}

export async function uploadSignupServiceImage(
  file: File,
): Promise<{ url: string; path: string }> {
  const form = new FormData();
  form.append('image', file);

  const res = await fetch(`${API_BASE}/signup/upload-service-image`, {
    method: 'POST',
    body: form,
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

export function selectedServicesPayload(
  drafts: SignupServiceDraft[],
): Array<{
  name: string;
  category: string;
  description: string;
  image_url: string | null;
  duration_minutes: number;
  base_price_cents: number;
}> {
  return drafts
    .filter((d) => d.selected)
    .map((d) => ({
      name: d.name,
      category: d.category,
      description: d.description,
      image_url: d.image_url,
      duration_minutes: d.duration_minutes,
      base_price_cents: d.base_price_cents,
    }));
}
