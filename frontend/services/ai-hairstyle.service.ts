import { api } from '@/lib/api-client';
import type {
  AiHairstyleSession,
  AiHairstyleSubmitPayload,
} from '@/lib/ai-hairstyle-types';

function publicOpts(tenantSlug: string, init?: RequestInit) {
  return {
    auth: false as const,
    tenant: false as const,
    headers: { 'X-Tenant-Slug': tenantSlug },
    ...init,
  };
}

export async function createAiHairstyleSession(
  tenantSlug: string,
): Promise<AiHairstyleSession> {
  return api<AiHairstyleSession>(
    '/book/ai-hairstyle/sessions',
    publicOpts(tenantSlug, { method: 'POST', body: JSON.stringify({}) }),
  );
}

export async function fetchAiHairstyleSession(
  tenantSlug: string,
  sessionId: string,
  publicToken: string,
): Promise<AiHairstyleSession> {
  const search = new URLSearchParams({ public_token: publicToken });
  return api<AiHairstyleSession>(
    `/book/ai-hairstyle/sessions/${encodeURIComponent(sessionId)}?${search.toString()}`,
    publicOpts(tenantSlug),
  );
}

export async function generateAiHairstylePreviews(
  tenantSlug: string,
  sessionId: string,
  publicToken: string,
  selfie: File,
): Promise<AiHairstyleSession> {
  const body = new FormData();
  body.append('public_token', publicToken);
  body.append('selfie', selfie);

  return api<AiHairstyleSession>(
    `/book/ai-hairstyle/sessions/${encodeURIComponent(sessionId)}/generate`,
    publicOpts(tenantSlug, { method: 'POST', body }),
  );
}

export async function pollAiHairstyleSessionUntilSettled(
  tenantSlug: string,
  sessionId: string,
  publicToken: string,
  options?: { intervalMs?: number; timeoutMs?: number },
): Promise<AiHairstyleSession> {
  const intervalMs = options?.intervalMs ?? 2500;
  // Align with GenerateAiHairstyleJob timeout (360s) plus a small buffer.
  const timeoutMs = options?.timeoutMs ?? 390_000;
  const started = Date.now();

  let latest = await fetchAiHairstyleSession(tenantSlug, sessionId, publicToken);
  while (latest.status === 'generating') {
    if (Date.now() - started > timeoutMs) {
      throw new Error('Look generation timed out. Please try again.');
    }
    await new Promise((r) => setTimeout(r, intervalMs));
    latest = await fetchAiHairstyleSession(tenantSlug, sessionId, publicToken);
  }

  if (latest.status === 'failed') {
    throw new Error(latest.error_message || 'Preview generation failed. Please try again.');
  }

  return latest;
}

export async function selectAiHairstylePreviews(
  tenantSlug: string,
  sessionId: string,
  publicToken: string,
  previewIds: string[],
): Promise<AiHairstyleSession> {
  return api<AiHairstyleSession>(
    `/book/ai-hairstyle/sessions/${encodeURIComponent(sessionId)}/select`,
    publicOpts(tenantSlug, {
      method: 'POST',
      body: JSON.stringify({
        public_token: publicToken,
        preview_ids: previewIds,
      }),
    }),
  );
}

export async function submitAiHairstyleSession(
  tenantSlug: string,
  sessionId: string,
  publicToken: string,
  payload: AiHairstyleSubmitPayload,
): Promise<AiHairstyleSession> {
  return api<AiHairstyleSession>(
    `/book/ai-hairstyle/sessions/${encodeURIComponent(sessionId)}/submit`,
    publicOpts(tenantSlug, {
      method: 'POST',
      body: JSON.stringify({
        public_token: publicToken,
        ...payload,
      }),
    }),
  );
}
