import type { ApiError, ApiResponse, ModuleUpgradePayload } from './types';

const API_BASE =
  process.env.NEXT_PUBLIC_API_URL?.replace(/\/$/, '') ??
  'http://localhost:8000/api/v1';

const TOKEN_KEY = 'neatmeet_auth_token';
const TENANT_SLUG_KEY = 'neatmeet_tenant_slug';

export function getStoredToken(): string | null {
  if (typeof window === 'undefined') return null;
  return localStorage.getItem(TOKEN_KEY);
}

export function setStoredToken(token: string | null): void {
  if (typeof window === 'undefined') return;
  if (token) localStorage.setItem(TOKEN_KEY, token);
  else localStorage.removeItem(TOKEN_KEY);
}

export function getStoredTenantSlug(): string | null {
  if (typeof window === 'undefined') return null;
  return localStorage.getItem(TENANT_SLUG_KEY);
}

export function setStoredTenantSlug(slug: string | null): void {
  if (typeof window === 'undefined') return;
  if (slug) localStorage.setItem(TENANT_SLUG_KEY, slug);
  else localStorage.removeItem(TENANT_SLUG_KEY);
}

export function clearStoredSession(): void {
  setStoredToken(null);
  setStoredTenantSlug(null);
}

export class ApiRequestError extends Error {
  readonly status: number;
  readonly code: string | null;
  readonly upgrade: ModuleUpgradePayload | null;

  constructor(
    message: string,
    options?: {
      status?: number;
      code?: string | null;
      upgrade?: ModuleUpgradePayload | null;
    },
  ) {
    super(message);
    this.name = 'ApiRequestError';
    this.status = options?.status ?? 400;
    this.code = options?.code ?? null;
    this.upgrade = options?.upgrade ?? null;
  }

  get isUpgradeRequired(): boolean {
    return this.code === 'module_upgrade_required';
  }
}

export function isUpgradeRequiredError(error: unknown): error is ApiRequestError {
  return error instanceof ApiRequestError && error.isUpgradeRequired;
}

export function getUpgradeFromError(error: unknown): ModuleUpgradePayload | null {
  if (error instanceof ApiRequestError && error.upgrade) return error.upgrade;
  return null;
}

type RequestOptions = RequestInit & {
  auth?: boolean;
  tenant?: boolean;
};

export async function api<T>(
  path: string,
  options: RequestOptions = {},
): Promise<T> {
  const { auth = false, tenant = true, headers, ...init } = options;
  const url = `${API_BASE}${path.startsWith('/') ? path : `/${path}`}`;

  const requestHeaders = new Headers(headers);
  requestHeaders.set('Accept', 'application/json');
  if (!(init.body instanceof FormData)) {
    requestHeaders.set('Content-Type', 'application/json');
  }

  if (auth) {
    const token = getStoredToken();
    if (token) requestHeaders.set('Authorization', `Bearer ${token}`);
  }

  if (tenant) {
    const slug = getStoredTenantSlug();
    if (slug) requestHeaders.set('X-Tenant-Slug', slug);
  }

  // Bearer-token auth — do not send cookies. credentials:'include' triggers
  // CORS/CSRF failures from the browser that surface as "Failed to fetch".
  const response = await fetch(url, {
    ...init,
    headers: requestHeaders,
    credentials: 'omit',
  });

  const raw = await response.text();
  let payload: ApiResponse<T> | ApiError | null = null;
  try {
    payload = raw ? (JSON.parse(raw) as ApiResponse<T> | ApiError) : null;
  } catch {
    const looksHtml = raw.trimStart().startsWith('<!') || raw.includes('<html');
    throw new ApiRequestError(
      looksHtml
        ? `API returned HTML instead of JSON (${response.status}). The route may be missing — clear Laravel route cache on the server.`
        : `Invalid JSON response (${response.status}).`,
      { status: response.status },
    );
  }

  if (!payload || !response.ok || !('success' in payload) || !payload.success) {
    let message =
      payload && 'message' in payload ? payload.message : 'Request failed';
    if (response.status === 419) {
      message =
        'Session expired. Refresh the page and try again.';
    } else if (response.status === 404) {
      message =
        message && message !== 'Request failed'
          ? message
          : 'API route not found (404). Clear Laravel route cache after deploy.';
    } else if (
      response.status >= 500 &&
      (!message || message === 'Server Error')
    ) {
      message = 'Something went wrong on the server. Please try again.';
    }
    const code =
      payload && 'code' in payload && typeof payload.code === 'string'
        ? payload.code
        : null;
    const upgrade =
      code === 'module_upgrade_required' &&
      payload &&
      'data' in payload &&
      payload.data &&
      typeof payload.data === 'object' &&
      'module' in payload.data
        ? (payload.data as ModuleUpgradePayload)
        : null;

    throw new ApiRequestError(message, {
      status: response.status,
      code,
      upgrade,
    });
  }

  return payload.data;
}

export { API_BASE };
