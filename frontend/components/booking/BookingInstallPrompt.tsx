'use client';

import { useEffect, useMemo, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { registerMemberServiceWorker } from '@/services/member-portal.service';
import {
  INSTALL_GATE_REPROMPT_MS,
  isStandaloneDisplay,
  promptTenantCustomerPwaInstall,
  shouldSkipBookingInstallGate,
  tenantCustomerPwaInstallHint,
  tenantCustomerPwaPath,
  type BeforeInstallPromptEvent,
} from '@/lib/tenant-customer-pwa';

interface BookingInstallPromptProps {
  salonName: string;
  tenantSlug: string;
  active: boolean;
}

type PromptPhase = 'offer' | 'manual';

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
  const [skipReady, setSkipReady] = useState(false);
  const repromptTimer = useRef<number | null>(null);

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
    return () => {
      if (repromptTimer.current != null) {
        window.clearTimeout(repromptTimer.current);
      }
    };
  }, []);

  useEffect(() => {
    if (!active) {
      setVisible(false);
      setSkipReady(false);
      return;
    }

    let cancelled = false;
    void registerMemberServiceWorker();

    void (async () => {
      const skip = await shouldSkipBookingInstallGate(tenantSlug);
      if (cancelled) return;
      if (skip || isStandaloneDisplay()) {
        router.replace(tenantCustomerPwaPath(tenantSlug));
        return;
      }
      setSkipReady(true);
      setPhase('offer');
      setVisible(true);
    })();

    return () => {
      cancelled = true;
    };
  }, [active, tenantSlug, router]);

  function scheduleReprompt() {
    if (repromptTimer.current != null) {
      window.clearTimeout(repromptTimer.current);
    }
    setVisible(false);
    setPhase('offer');
    repromptTimer.current = window.setTimeout(() => {
      setVisible(true);
      setPhase('offer');
    }, INSTALL_GATE_REPROMPT_MS);
  }

  async function handleInstall() {
    setBusy(true);
    try {
      const result = await promptTenantCustomerPwaInstall(
        tenantSlug,
        installEvent,
        (path) => router.replace(path),
      );

      if (result === 'accepted' || result === 'already_standalone') {
        router.replace(tenantCustomerPwaPath(tenantSlug));
        return;
      }
      if (result === 'dismissed') {
        scheduleReprompt();
        return;
      }
      setPhase('manual');
    } finally {
      setBusy(false);
    }
  }

  if (!active || !skipReady || !visible) {
    return null;
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 sm:items-center"
      role="dialog"
      aria-modal="true"
      aria-label={`Install ${salonName} app`}
    >
      <div className="w-full max-w-md rounded-2xl border border-[var(--book-line)] bg-white p-5 shadow-[var(--book-shadow)] sm:p-6">
        {phase === 'manual' ? (
          <>
            <h3 className="book-display text-2xl font-bold text-[var(--book-ink)]">
              Install my {salonName} app
            </h3>
            <p className="mt-2 text-sm leading-relaxed text-[var(--book-muted)]">
              Add the salon app to your home screen, then open it to join and log in.
            </p>
            <p className="mt-3 rounded-lg border border-[var(--book-line)] bg-[var(--book-wash)] px-3 py-2 text-sm text-[var(--book-ink)]">
              {installHint}
            </p>
            <button
              type="button"
              className="mt-5 inline-flex w-full items-center justify-center rounded-md bg-[var(--book-moss)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--book-moss-deep)]"
              onClick={() => router.replace(tenantCustomerPwaPath(tenantSlug))}
            >
              I installed it — open the app
            </button>
            <button
              type="button"
              className="mt-2 inline-flex w-full items-center justify-center rounded-md border border-[var(--book-line)] bg-white px-5 py-2.5 text-sm font-semibold text-[var(--book-ink)] hover:bg-[var(--book-wash)]"
              onClick={scheduleReprompt}
            >
              Not now
            </button>
          </>
        ) : (
          <>
            <h3 className="book-display text-2xl font-bold text-[var(--book-ink)]">
              Install my {salonName} app
            </h3>
            <p className="mt-2 text-sm leading-relaxed text-[var(--book-muted)]">
              Install the salon app for membership, check-in, and rewards. You can continue booking
              without installing — we&apos;ll ask again in a moment.
            </p>
            <div className="mt-5 flex flex-col gap-2 sm:flex-row">
              <button
                type="button"
                disabled={busy}
                className="inline-flex flex-1 items-center justify-center rounded-md bg-[var(--book-moss)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--book-moss-deep)] disabled:opacity-60"
                onClick={() => void handleInstall()}
              >
                {busy ? 'Installing…' : 'Install PWA'}
              </button>
              <button
                type="button"
                className="inline-flex flex-1 items-center justify-center rounded-md border border-[var(--book-line)] bg-white px-5 py-2.5 text-sm font-semibold text-[var(--book-ink)] hover:bg-[var(--book-wash)]"
                onClick={scheduleReprompt}
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
