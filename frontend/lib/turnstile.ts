/**
 * Cloudflare Turnstile helpers for public/auth form posts.
 * Renders a visible widget (managed / normal size). When
 * NEXT_PUBLIC_TURNSTILE_SITE_KEY is empty, tokens are omitted (local/dev).
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
      execute: (widgetId?: string) => void;
    };
    onNeatMeetTurnstileLoad?: () => void;
  }
}

const SCRIPT_ID = 'cf-turnstile-script';
const SCRIPT_SRC =
  'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=onNeatMeetTurnstileLoad';

const TOKEN_WAIT_MS = 120_000;

export type TurnstileWidgetSize = 'normal' | 'compact';

type TurnstileListener = (ready: boolean) => void;

type MountOptions = {
  size?: TurnstileWidgetSize;
  onError?: (message: string) => void;
  /** When true, challenge runs only after turnstile.execute() (user taps the widget). */
  deferExecution?: boolean;
};

let widgetId: string | null = null;
let hostElement: HTMLElement | null = null;
let widgetSize: TurnstileWidgetSize = 'normal';
let deferExecution = false;
let scriptPromise: Promise<void> | null = null;
let mountPromise: Promise<void> | null = null;
let pendingToken: Promise<string> | null = null;
let resolvePending: ((token: string) => void) | null = null;
let rejectPending: ((err: Error) => void) | null = null;
let pollTimer: number | null = null;
const readyListeners = new Set<TurnstileListener>();

function notifyReady(ready: boolean): void {
  readyListeners.forEach((listener) => listener(ready));
}

function clearPoll(): void {
  if (pollTimer) {
    window.clearInterval(pollTimer);
    pollTimer = null;
  }
}

function readToken(): string | undefined {
  if (!window.turnstile || !widgetId) return undefined;
  return window.turnstile.getResponse(widgetId) || undefined;
}

function isTokenReady(): boolean {
  return Boolean(readToken());
}

export function getTurnstileSiteKey(): string | null {
  const key = (process.env.NEXT_PUBLIC_TURNSTILE_SITE_KEY ?? '').trim();
  return key || null;
}

export function isTurnstileConfigured(): boolean {
  return Boolean(getTurnstileSiteKey());
}

export function subscribeTurnstileReady(listener: TurnstileListener): () => void {
  readyListeners.add(listener);
  listener(isTokenReady());
  return () => readyListeners.delete(listener);
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

function teardownWidget(): void {
  clearPoll();
  pendingToken = null;
  resolvePending = null;
  rejectPending = null;

  if (window.turnstile && widgetId) {
    try {
      window.turnstile.remove(widgetId);
    } catch {
      // Widget may already be gone.
    }
  }

  widgetId = null;
  deferExecution = false;
  if (hostElement) {
    hostElement.innerHTML = '';
  }
  notifyReady(false);
}

function renderWidget(options: MountOptions = {}): void {
  const siteKey = getTurnstileSiteKey();
  if (!siteKey || !window.turnstile || !hostElement) return;

  widgetSize = options.size ?? widgetSize;
  deferExecution = options.deferExecution ?? false;
  hostElement.innerHTML = '';

  widgetId = window.turnstile.render(hostElement, {
    sitekey: siteKey,
    size: widgetSize,
    theme: 'light',
    ...(deferExecution ? { execution: 'execute' } : {}),
    callback: (token: string) => {
      clearPoll();
      notifyReady(true);
      resolvePending?.(token);
      resolvePending = null;
      rejectPending = null;
      pendingToken = null;
    },
    'error-callback': () => {
      clearPoll();
      notifyReady(false);
      const message = 'Security check failed. Please try again.';
      options.onError?.(message);
      rejectPending?.(new Error(message));
      resolvePending = null;
      rejectPending = null;
      pendingToken = null;
    },
    'expired-callback': () => {
      notifyReady(false);
    },
  });

  notifyReady(isTokenReady());
}

async function ensureRendered(options: MountOptions = {}): Promise<void> {
  if (!isTurnstileConfigured() || !hostElement) return;

  await loadScript();
  if (!widgetId) {
    renderWidget(options);
  }
}

/**
 * Mount a visible Turnstile widget inside the given container.
 * Returns cleanup — call on React unmount.
 */
export function mountTurnstileIn(
  container: HTMLElement,
  options: MountOptions = {},
): () => void {
  if (!isTurnstileConfigured()) {
    return () => undefined;
  }

  if (hostElement && hostElement !== container) {
    teardownWidget();
  }

  hostElement = container;
  widgetSize = options.size ?? 'normal';
  deferExecution = options.deferExecution ?? false;

  mountPromise = ensureRendered(options).catch(() => {
    options.onError?.('Security check unavailable. Please refresh and try again.');
  });

  return () => {
    if (hostElement === container) {
      teardownWidget();
      hostElement = null;
      mountPromise = null;
    }
  };
}

function waitForToken(): Promise<string> {
  const existing = readToken();
  if (existing) {
    return Promise.resolve(existing);
  }

  if (pendingToken) {
    return pendingToken;
  }

  pendingToken = new Promise<string>((resolve, reject) => {
    resolvePending = resolve;
    rejectPending = reject;
  });

  clearPoll();
  const started = Date.now();
  pollTimer = window.setInterval(() => {
    const response = readToken();
    if (response) {
      clearPoll();
      notifyReady(true);
      resolvePending?.(response);
      resolvePending = null;
      rejectPending = null;
      pendingToken = null;
      return;
    }

    if (Date.now() - started > TOKEN_WAIT_MS) {
      clearPoll();
      const message =
        'Please complete the security check above, then try again.';
      rejectPending?.(new Error(message));
      resolvePending = null;
      rejectPending = null;
      pendingToken = null;
    }
  }, 200);

  return pendingToken;
}

/**
 * Obtain a Turnstile token for the next form POST.
 * Returns undefined when Turnstile is not configured (local/dev).
 */
export async function getTurnstileToken(): Promise<string | undefined> {
  if (!isTurnstileConfigured()) {
    return undefined;
  }

  if (mountPromise) {
    await mountPromise;
  }

  if (!window.turnstile || !widgetId) {
    throw new Error('Security check unavailable. Please refresh and try again.');
  }

  const existing = readToken();
  if (existing) {
    queueMicrotask(() => {
      try {
        window.turnstile?.reset(widgetId ?? undefined);
      } catch {
        renderWidget();
      }
      notifyReady(false);
    });
    return existing;
  }

  return waitForToken();
}

export function withTurnstileToken<T extends Record<string, unknown>>(
  body: T,
  token: string | undefined,
): T & { turnstile_token?: string } {
  if (!token) return body;
  return { ...body, turnstile_token: token };
}

/** Start a deferred Turnstile challenge after the user taps the widget. */
export function triggerTurnstileChallenge(): void {
  if (!window.turnstile || !widgetId || !deferExecution) return;
  try {
    window.turnstile.execute(widgetId);
  } catch {
    // Widget may still be initialising.
  }
}

/** @deprecated Use mountTurnstileIn via TurnstileWidget instead. */
export async function prefetchTurnstile(): Promise<void> {
  if (!isTurnstileConfigured() || !hostElement) return;
  try {
    await ensureRendered();
  } catch {
    // Non-fatal — submit will surface errors.
  }
}
