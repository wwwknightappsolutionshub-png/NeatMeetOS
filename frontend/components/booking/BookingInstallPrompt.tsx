'use client';

import { useEffect, useMemo, useState } from 'react';
import { useRouter } from 'next/navigation';
import { registerMemberServiceWorker } from '@/services/member-portal.service';
import {
  isStandaloneDisplay,
  promptTenantCustomerPwaInstall,
  tenantCustomerPwaInstallHint,
  type BeforeInstallPromptEvent,
} from '@/lib/tenant-customer-pwa';

interface BookingInstallPromptProps {
  salonName: string;
  tenantSlug: string;
  active: boolean;
}

type PromptPhase = 'offer' | 'manual' | 'installed';

export function BookingInstallPrompt({
  salonName,
  tenantSlug,
  active,
}: BookingInstallPromptProps) {
  const router = useRouter();
  const [visible, setVisible] = useState(false);
  const [phase, setPhase] = useState<PromptPhase>('offer');
  const [installEvent, setInstallEvent] = useState<BeforeInstallPromptEvent | null>(null);
  const [busy, setBusy] = useState(false);

  const installHint = useMemo(() => tenantCustomerPwaInstallHint(), []);

  useEffect(() => {
    const onBeforeInstall = (event: Event) => {
      event.preventDefault();
      setInstallEvent(event as BeforeInstallPromptEvent);
    };
    window.addEventListener('beforeinstallprompt', onBeforeInstall);
    return () => window.removeEventListener('beforeinstallprompt', onBeforeInstall);
  }, []);

  useEffect(() => {
    if (!active || isStandaloneDisplay()) {
      setVisible(false);
      setPhase('offer');
      return;
    }

    void registerMemberServiceWorker();

    const id = window.setTimeout(() => {
      setVisible(true);
      setPhase('offer');
    }, 5_000);
    return () => window.clearTimeout(id);
  }, [active]);

  if (!visible) {
    return null;
  }

  async function handleInstall() {
    setBusy(true);
    try {
      const result = await promptTenantCustomerPwaInstall(
        tenantSlug,
        installEvent,
        (path) => router.push(path),
      );

      if (result === 'accepted' || result === 'already_standalone') {
        setPhase('installed');
        return;
      }
      if (result === 'manual') {
        setPhase('manual');
        return;
      }
      setVisible(false);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center">
      <div className="w-full max-w-md rounded-2xl border border-[var(--book-line)] bg-white p-5 shadow-[var(--book-shadow)] sm:p-6">
        {phase === 'installed' ? (
          <>
            <h3 className="book-display text-2xl font-bold text-[var(--book-ink)]">
              App installed
            </h3>
            <p className="mt-2 text-sm leading-relaxed text-[var(--book-muted)]">
              Open the {salonName} app from your home screen when you&apos;re ready. You can log in
              there to unlock member pricing and loyalty.
            </p>
            <button
              type="button"
              className="mt-5 inline-flex w-full items-center justify-center rounded-md bg-[var(--book-moss)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--book-moss-deep)]"
              onClick={() => setVisible(false)}
            >
              Done
            </button>
          </>
        ) : phase === 'manual' ? (
          <>
            <h3 className="book-display text-2xl font-bold text-[var(--book-ink)]">
              Install My {salonName} Salon App
            </h3>
            <p className="mt-2 text-sm leading-relaxed text-[var(--book-muted)]">
              Follow these steps to add the app to your home screen. You can log in after it&apos;s
              installed.
            </p>
            <p className="mt-3 rounded-lg border border-[var(--book-line)] bg-[var(--book-wash)] px-3 py-2 text-sm text-[var(--book-ink)]">
              {installHint}
            </p>
            <button
              type="button"
              className="mt-5 inline-flex w-full items-center justify-center rounded-md border border-[var(--book-line)] bg-white px-5 py-2.5 text-sm font-semibold text-[var(--book-ink)] hover:bg-[var(--book-wash)]"
              onClick={() => setVisible(false)}
            >
              Got it
            </button>
          </>
        ) : (
          <>
            <h3 className="book-display text-2xl font-bold text-[var(--book-ink)]">
              Install My {salonName} Salon App
            </h3>
            <p className="mt-2 text-sm leading-relaxed text-[var(--book-muted)]">
              Experience more benefits and features when you install this salon&apos;s app — no login
              needed yet.
            </p>
            <div className="mt-5 flex flex-col gap-2 sm:flex-row">
              <button
                type="button"
                disabled={busy}
                className="inline-flex flex-1 items-center justify-center rounded-md bg-[var(--book-moss)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--book-moss-deep)] disabled:opacity-60"
                onClick={() => void handleInstall()}
              >
                {busy ? 'Installing…' : 'Install now'}
              </button>
              <button
                type="button"
                className="inline-flex flex-1 items-center justify-center rounded-md border border-[var(--book-line)] bg-white px-5 py-2.5 text-sm font-semibold text-[var(--book-ink)] hover:bg-[var(--book-wash)]"
                onClick={() => setVisible(false)}
              >
                Not now
              </button>
            </div>
          </>
        )}
      </div>
    </div>
  );
}
