export type BeforeInstallPromptEvent = Event & {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

export type TenantCustomerPwaInstallResult =
  | 'accepted'
  | 'dismissed'
  | 'already_standalone'
  | 'manual';

export const INSTALL_GATE_REPROMPT_MS = 300_000;

export function isStandaloneDisplay(): boolean {
  if (typeof window === 'undefined') return false;
  return (
    window.matchMedia('(display-mode: standalone)').matches ||
    Boolean((navigator as Navigator & { standalone?: boolean }).standalone)
  );
}

export function memberJoinedStorageKey(tenantSlug: string): string {
  return `neatmeet_joined_${tenantSlug}`;
}

export function markMemberJoined(tenantSlug: string): void {
  if (typeof window === 'undefined') return;
  try {
    localStorage.setItem(memberJoinedStorageKey(tenantSlug), '1');
  } catch {
    // ignore
  }
}

export function hasMarkedMemberJoined(tenantSlug: string): boolean {
  if (typeof window === 'undefined') return false;
  try {
    return localStorage.getItem(memberJoinedStorageKey(tenantSlug)) === '1';
  } catch {
    return false;
  }
}

/**
 * Chrome Android: related web apps listed in the member manifest.
 * iOS Safari cannot report this — callers must also check standalone / session.
 */
export async function hasInstalledRelatedWebApp(): Promise<boolean> {
  if (typeof navigator === 'undefined') return false;
  const nav = navigator as Navigator & {
    getInstalledRelatedApps?: () => Promise<Array<{ platform?: string }>>;
  };
  if (typeof nav.getInstalledRelatedApps !== 'function') {
    return false;
  }
  try {
    const apps = await nav.getInstalledRelatedApps();
    return apps.some((app) => app.platform === 'webapp' || app.platform === 'play');
  } catch {
    return false;
  }
}

export async function shouldSkipBookingInstallGate(tenantSlug: string): Promise<boolean> {
  if (isStandaloneDisplay()) {
    return true;
  }
  if (typeof window !== 'undefined') {
    try {
      const raw = localStorage.getItem(`neatmeet_member_${tenantSlug}`);
      if (raw) {
        const parsed = JSON.parse(raw) as { token?: string };
        if (parsed?.token) {
          return true;
        }
      }
    } catch {
      // ignore
    }
  }
  return hasInstalledRelatedWebApp();
}

export function tenantCustomerPwaPath(tenantSlug: string): string {
  return `/member/${tenantSlug}`;
}

export function tenantCustomerManifestPath(tenantSlug: string): string {
  return `/member/${tenantSlug}/manifest.webmanifest`;
}

export function bookingPagePath(tenantSlug: string): string {
  return `/book/${tenantSlug}`;
}

export function membershipsPagePath(tenantSlug: string): string {
  return `/book/${tenantSlug}/memberships`;
}

export function isMemberAppEntry(from: string | null | undefined): boolean {
  return from === 'member';
}

/** Marks booking links opened from the member PWA so the book page stays on /book. */
export function withMemberBookingAttribution(href: string): string {
  if (/[?&]from=member(?:&|$)/.test(href)) return href;
  const separator = href.includes('?') ? '&' : '?';
  return `${href}${separator}from=member`;
}

export function resolveTenantPageUrl(href: string): string {
  if (typeof window === 'undefined') return href;
  if (/^https?:\/\//i.test(href)) return href;
  const path = href.startsWith('/') ? href : `/${href}`;
  return `${window.location.origin}${path}`;
}

/** Hard navigation — required when leaving the member PWA shell for /book routes. */
export function openTenantBookingPage(href: string): void {
  if (typeof window === 'undefined') return;
  window.location.replace(resolveTenantPageUrl(href));
}

function referralStorageKey(tenantSlug: string): string {
  return `neatmeet_ref_${tenantSlug}`;
}

function locationStorageKey(tenantSlug: string): string {
  return `neatmeet_join_location_${tenantSlug}`;
}

export function captureJoinAttribution(
  tenantSlug: string,
  referralCode?: string | null,
  locationId?: string | null,
): void {
  if (typeof window === 'undefined') return;
  try {
    if (referralCode?.trim()) {
      sessionStorage.setItem(referralStorageKey(tenantSlug), referralCode.trim());
    }
    if (locationId?.trim()) {
      sessionStorage.setItem(locationStorageKey(tenantSlug), locationId.trim());
    }
  } catch {
    // ignore
  }
}

export function readJoinReferralCode(tenantSlug: string): string | undefined {
  if (typeof window === 'undefined') return undefined;
  try {
    return sessionStorage.getItem(referralStorageKey(tenantSlug)) || undefined;
  } catch {
    return undefined;
  }
}

export function readJoinLocationId(tenantSlug: string): string | undefined {
  if (typeof window === 'undefined') return undefined;
  try {
    return sessionStorage.getItem(locationStorageKey(tenantSlug)) || undefined;
  } catch {
    return undefined;
  }
}

/** Platform-specific install steps when the browser has no native install prompt. */
export function tenantCustomerPwaInstallHint(): string {
  if (typeof navigator === 'undefined') {
    return 'Add this page to your home screen for quick access.';
  }
  const ua = navigator.userAgent;
  if (/iPhone|iPad|iPod/i.test(ua)) {
    return 'On iPhone: tap Share → Add to Home Screen to install this app.';
  }
  if (/Android/i.test(ua)) {
    return 'On Android: open the browser menu → Install app / Add to Home screen.';
  }
  return 'Use Install / Add to Home Screen to keep this app handy.';
}

/**
 * Prompt the tenant customer PWA install when Chrome has deferred beforeinstallprompt.
 * Does not open the membership login page — callers show manual steps when result is manual.
 */
export async function promptTenantCustomerPwaInstall(
  tenantSlug: string,
  installEvent: BeforeInstallPromptEvent | null,
  navigate?: (path: string) => void,
): Promise<TenantCustomerPwaInstallResult> {
  if (isStandaloneDisplay()) {
    navigate?.(tenantCustomerPwaPath(tenantSlug));
    return 'already_standalone';
  }

  if (installEvent) {
    try {
      await installEvent.prompt();
      const choice = await installEvent.userChoice;
      return choice.outcome === 'accepted' ? 'accepted' : 'dismissed';
    } catch {
      return 'manual';
    }
  }

  return 'manual';
}

/**
 * Best-effort exit for installed member PWAs. window.close() is ignored for
 * home-screen apps, so we blank the shell as a fallback.
 */
export function exitMemberApp(): void {
  if (typeof window === 'undefined') return;

  const blankShell = () => {
    try {
      window.location.replace('about:blank');
    } catch {
      window.location.href = 'about:blank';
    }
  };

  try {
    const selfWindow = window.open('', '_self');
    selfWindow?.close();
    window.close();
  } catch {
    // ignore
  }

  window.setTimeout(() => {
    if (typeof document !== 'undefined' && document.hidden) {
      return;
    }
    blankShell();
  }, 150);
}

/** @deprecated Prefer exitMemberApp */
export function attemptCloseMemberApp(onCloseFailed?: () => void): void {
  exitMemberApp();
  if (onCloseFailed) {
    window.setTimeout(onCloseFailed, 400);
  }
}
