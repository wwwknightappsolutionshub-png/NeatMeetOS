export type BeforeInstallPromptEvent = Event & {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

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

/**
 * Prompt the tenant customer (membership) PWA install when Chrome has deferred
 * beforeinstallprompt; otherwise open the membership app, which is the install surface.
 */
export async function promptTenantCustomerPwaInstall(
  tenantSlug: string,
  installEvent: BeforeInstallPromptEvent | null,
  navigate: (path: string) => void,
): Promise<void> {
  const memberPath = tenantCustomerPwaPath(tenantSlug);
  if (isStandaloneDisplay()) {
    navigate(memberPath);
    return;
  }
  if (installEvent) {
    try {
      await installEvent.prompt();
      await installEvent.userChoice;
      return;
    } catch {
      // Fall through to the membership app.
    }
  }
  navigate(memberPath);
}
