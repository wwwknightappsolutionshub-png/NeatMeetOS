/**
 * Cloudflare Turnstile helpers for public/auth form posts.
 * When NEXT_PUBLIC_TURNSTILE_SITE_KEY is empty, tokens are omitted (local/dev).
 */

declare global {
  interface Window {
    turnstile?: {
      render: (
        container: string | HTMLElement,
        options: Record<string, unknown>,
      ) => string;
      reset: (widgetId?: string) => void;
      remove: (widgetId?: string) => void;
      getResponse: (widgetId?: string) => string | undefined;
      ready: (cb: () => void) => void;
    };
    onNeatMeetTurnstileLoad?: () => void;
  }
}

const SCRIPT_ID = 'cf-turnstile-script';
const SCRIPT_SRC =
  'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=onNeatMeetTurnstileLoad';

let widgetId: string | null = null;
let containerEl: HTMLDivElement | null = null;
let scriptPromise: Promise<void> | null = null;
let pendingToken: Promise<string> | null = null;
let resolvePending: ((token: string) => void) | null = null;
let rejectPending: ((err: Error) => void) | null = null;

export function getTurnstileSiteKey(): string | null {
  const key = (process.env.NEXT_PUBLIC_TURNSTILE_SITE_KEY ?? '').trim();
  return key || null;
}

export function isTurnstileConfigured(): boolean {
  return Boolean(getTurnstileSiteKey());
}

function ensureContainer(): HTMLDivElement {
  if (typeof document === 'undefined') {
    throw new Error('Turnstile requires a browser');
  }
  if (containerEl && document.body.contains(containerEl)) {
    return containerEl;
  }
  containerEl = document.createElement('div');
  containerEl.id = 'nm-turnstile-host';
  containerEl.setAttribute('aria-hidden', 'true');
  containerEl.style.cssText =
    'position:fixed;left:-9999px;top:0;width:1px;height:1px;overflow:hidden;';
  document.body.appendChild(containerEl);
  return containerEl;
}

function loadScript(): Promise<void> {
  if (typeof window === 'undefined') {
    return Promise.resolve();
  }
  if (window.turnstile) {
    return Promise.resolve();
  }
  if (scriptPromise) {
    return scriptPromise;
  }

  scriptPromise = new Promise<void>((resolve, reject) => {
    const existing = document.getElementById(SCRIPT_ID);
    if (existing) {
      window.onNeatMeetTurnstileLoad = () => resolve();
      if (window.turnstile) resolve();
      return;
    }

    window.onNeatMeetTurnstileLoad = () => resolve();

    const script = document.createElement('script');
    script.id = SCRIPT_ID;
    script.src = SCRIPT_SRC;
    script.async = true;
    script.defer = true;
    script.onerror = () => reject(new Error('Failed to load Turnstile'));
    document.head.appendChild(script);
  });

  return scriptPromise;
}

function renderWidget(): void {
  const siteKey = getTurnstileSiteKey();
  if (!siteKey || !window.turnstile) return;

  const host = ensureContainer();
  host.innerHTML = '';
  widgetId = window.turnstile.render(host, {
    sitekey: siteKey,
    size: 'invisible',
    appearance: 'interaction-only',
    callback: (token: string) => {
      resolvePending?.(token);
      resolvePending = null;
      rejectPending = null;
      pendingToken = null;
    },
    'error-callback': () => {
      rejectPending?.(new Error('Security check failed. Please try again.'));
      resolvePending = null;
      rejectPending = null;
      pendingToken = null;
    },
    'expired-callback': () => {
      // Token expired — next getTurnstileToken will reset.
    },
  });
}

async function ensureWidget(): Promise<void> {
  if (!isTurnstileConfigured()) return;
  await loadScript();
  if (!widgetId) {
    renderWidget();
  }
}

/**
 * Obtain a fresh Turnstile token for the next form POST.
 * Returns undefined when Turnstile is not configured (local/dev).
 */
export async function getTurnstileToken(): Promise<string | undefined> {
  if (!isTurnstileConfigured()) {
    return undefined;
  }

  await ensureWidget();
  if (!window.turnstile || !widgetId) {
    throw new Error('Security check unavailable. Please refresh and try again.');
  }

  if (pendingToken) {
    return pendingToken;
  }

  pendingToken = new Promise<string>((resolve, reject) => {
    resolvePending = resolve;
    rejectPending = reject;
  });

  try {
    window.turnstile.reset(widgetId);
  } catch {
    renderWidget();
  }

  // Invisible widgets often fire callback after reset; also poll getResponse.
  const started = Date.now();
  const poll = window.setInterval(() => {
    const response = window.turnstile?.getResponse(widgetId ?? undefined);
    if (response) {
      window.clearInterval(poll);
      resolvePending?.(response);
      resolvePending = null;
      rejectPending = null;
      pendingToken = null;
    } else if (Date.now() - started > 20_000) {
      window.clearInterval(poll);
      rejectPending?.(new Error('Security check timed out. Please try again.'));
      resolvePending = null;
      rejectPending = null;
      pendingToken = null;
    }
  }, 200);

  return pendingToken;
}

export function withTurnstileToken<T extends Record<string, unknown>>(
  body: T,
  token: string | undefined,
): T & { turnstile_token?: string } {
  if (!token) return body;
  return { ...body, turnstile_token: token };
}

/** Mount early on public pages so the first submit is faster. */
export async function prefetchTurnstile(): Promise<void> {
  if (!isTurnstileConfigured()) return;
  try {
    await ensureWidget();
  } catch {
    // Non-fatal — submit will retry.
  }
}
