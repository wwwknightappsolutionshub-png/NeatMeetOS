import { API_BASE } from '@/lib/api-client';

/** Origin of the Laravel API (without /api/v1), used for /storage media. */
function apiOrigin(): string {
  return API_BASE.replace(/\/api\/v1\/?$/i, '').replace(/\/$/, '');
}

/**
 * Resolve uploaded media URLs so /storage paths hit the API host
 * (e.g. http://localhost:8000), not the Next.js app origin.
 */
export function resolveMediaUrl(url: string | null | undefined): string | null {
  if (!url) return null;
  const trimmed = url.trim();
  if (!trimmed) return null;

  const origin = apiOrigin();

  if (trimmed.startsWith('/storage/')) {
    return `${origin}${trimmed}`;
  }

  try {
    const parsed = new URL(trimmed, origin);
    if (parsed.pathname.startsWith('/storage/')) {
      return `${origin}${parsed.pathname}${parsed.search}`;
    }
  } catch {
    return trimmed;
  }

  return trimmed;
}
