import {
  api,
  clearStoredSession,
  setStoredTenantSlug,
  setStoredToken,
} from '@/lib/api-client';
import { getTurnstileToken, withTurnstileToken } from '@/lib/turnstile';
import type { LoginResponse, ShellStatus } from '@/lib/types';

function storeSession(data: LoginResponse): LoginResponse {
  setStoredToken(data.token);
  if (data.tenant?.slug) setStoredTenantSlug(data.tenant.slug);
  return data;
}

export async function login(
  email: string,
  password: string,
): Promise<LoginResponse> {
  const turnstile_token = await getTurnstileToken();
  const data = await api<LoginResponse>('/auth/login', {
    method: 'POST',
    body: JSON.stringify(
      withTurnstileToken(
        { email, password, device_name: 'neatmeet-os-web' },
        turnstile_token,
      ),
    ),
    tenant: false,
  });

  return storeSession(data);
}

export async function requestMagicLink(email: string): Promise<{ sent: boolean }> {
  const turnstile_token = await getTurnstileToken();
  return api<{ sent: boolean }>('/auth/magic-link', {
    method: 'POST',
    body: JSON.stringify(withTurnstileToken({ email }, turnstile_token)),
    tenant: false,
  });
}

export async function consumeMagicLink(token: string): Promise<LoginResponse> {
  const turnstile_token = await getTurnstileToken();
  const data = await api<LoginResponse>('/auth/magic-link/consume', {
    method: 'POST',
    body: JSON.stringify(
      withTurnstileToken(
        { token, device_name: 'neatmeet-os-web' },
        turnstile_token,
      ),
    ),
    tenant: false,
  });

  return storeSession(data);
}

export async function requestPasswordReset(
  email: string,
): Promise<{ sent: boolean }> {
  const turnstile_token = await getTurnstileToken();
  return api<{ sent: boolean }>('/auth/forgot-password', {
    method: 'POST',
    body: JSON.stringify(withTurnstileToken({ email }, turnstile_token)),
    tenant: false,
  });
}

export async function resetPassword(
  token: string,
  password: string,
  passwordConfirmation: string,
): Promise<null> {
  const turnstile_token = await getTurnstileToken();
  return api<null>('/auth/reset-password', {
    method: 'POST',
    body: JSON.stringify(
      withTurnstileToken(
        {
          token,
          password,
          password_confirmation: passwordConfirmation,
        },
        turnstile_token,
      ),
    ),
    tenant: false,
  });
}

export async function activateAccount(
  token: string,
  password: string,
  passwordConfirmation: string,
): Promise<LoginResponse> {
  const turnstile_token = await getTurnstileToken();
  const data = await api<LoginResponse>('/signup/activate', {
    method: 'POST',
    body: JSON.stringify(
      withTurnstileToken(
        {
          token,
          password,
          password_confirmation: passwordConfirmation,
          device_name: 'neatmeet-os-web',
        },
        turnstile_token,
      ),
    ),
    tenant: false,
  });

  return storeSession(data);
}

export async function logout(): Promise<void> {
  try {
    await api<null>('/auth/logout', { method: 'POST', auth: true });
  } finally {
    clearShellCache();
    clearStoredSession();
  }
}

let shellCache: { at: number; data: ShellStatus } | null = null;
let shellInflight: Promise<ShellStatus> | null = null;

export async function fetchShell(): Promise<ShellStatus> {
  const now = Date.now();
  if (shellCache && now - shellCache.at < 60_000) {
    return shellCache.data;
  }
  if (shellInflight) {
    return shellInflight;
  }
  shellInflight = api<ShellStatus>('/shell', { auth: true })
    .then((data) => {
      shellCache = { at: Date.now(), data };
      return data;
    })
    .finally(() => {
      shellInflight = null;
    });
  return shellInflight;
}

export function clearShellCache(): void {
  shellCache = null;
}
