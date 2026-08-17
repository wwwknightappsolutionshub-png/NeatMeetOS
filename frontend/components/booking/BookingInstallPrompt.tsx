'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';

type BeforeInstallPromptEvent = Event & {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

function isStandalone(): boolean {
  if (typeof window === 'undefined') return false;
  return (
    window.matchMedia('(display-mode: standalone)').matches ||
    Boolean((navigator as Navigator & { standalone?: boolean }).standalone)
  );
}

interface BookingInstallPromptProps {
  salonName: string;
  tenantSlug: string;
  active: boolean;
}

export function BookingInstallPrompt({
  salonName,
  tenantSlug,
  active,
}: BookingInstallPromptProps) {
  const router = useRouter();
  const [visible, setVisible] = useState(false);
  const [installEvent, setInstallEvent] = useState<BeforeInstallPromptEvent | null>(null);

  useEffect(() => {
    const onBeforeInstall = (event: Event) => {
      event.preventDefault();
      setInstallEvent(event as BeforeInstallPromptEvent);
    };
    window.addEventListener('beforeinstallprompt', onBeforeInstall);
    return () => window.removeEventListener('beforeinstallprompt', onBeforeInstall);
  }, []);

  useEffect(() => {
    if (!active || isStandalone()) {
      setVisible(false);
      return;
    }

    const id = window.setTimeout(() => setVisible(true), 5_000);
    return () => window.clearTimeout(id);
  }, [active]);

  if (!visible) {
    return null;
  }

  async function handleInstall() {
    if (installEvent) {
      try {
        await installEvent.prompt();
        await installEvent.userChoice;
        setVisible(false);
        return;
      } catch {
        // Fall through to the salon membership app, which is the tenant PWA.
      }
    }
    router.push(`/member/${tenantSlug}`);
  }

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center">
      <div className="w-full max-w-md rounded-2xl border border-[var(--book-line)] bg-white p-5 shadow-[var(--book-shadow)] sm:p-6">
        <h3 className="book-display text-2xl font-bold text-[var(--book-ink)]">
          Install My {salonName} Salon App
        </h3>
        <p className="mt-2 text-sm leading-relaxed text-[var(--book-muted)]">
          Experience more benefits and features when you install this salon&apos;s App
        </p>
        <div className="mt-5 flex flex-col gap-2 sm:flex-row">
          <button
            type="button"
            className="inline-flex flex-1 items-center justify-center rounded-md bg-[var(--book-moss)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--book-moss-deep)]"
            onClick={() => void handleInstall()}
          >
            Install Now
          </button>
          <button
            type="button"
            className="inline-flex flex-1 items-center justify-center rounded-md border border-[var(--book-line)] bg-white px-5 py-2.5 text-sm font-semibold text-[var(--book-ink)] hover:bg-[var(--book-wash)]"
            onClick={() => setVisible(false)}
          >
            Not now
          </button>
        </div>
      </div>
    </div>
  );
}
