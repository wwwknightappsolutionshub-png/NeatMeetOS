export type BeforeInstallPromptEvent = Event & {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

export type TenantCustomerPwaInstallResult =
  | 'accepted'
  | 'dismissed'
  | 'already_standalone'
  | 'manual';

export function isStandaloneDisplay(): boolean {
  if (typeof window === 'undefined') return false;
  return (
    window.matchMedia('(display-mode: standalone)').matches ||
    Boolean((navigator as Navigator & { standalone?: boolean }).standalone)
  );
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
