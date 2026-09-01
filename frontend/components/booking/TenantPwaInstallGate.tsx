'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { registerMemberServiceWorker } from '@/services/member-portal.service';
import {
  isIosDevice,
  isStandaloneDisplay,
  promptTenantCustomerPwaInstall,
  shouldSkipBookingInstallGate,
  tenantCustomerPwaInstallHint,
  tenantCustomerPwaPath,
  type BeforeInstallPromptEvent,
} from '@/lib/tenant-customer-pwa';

type Props = {
  salonName: string;
  tenantSlug: string;
};

type Phase = 'offer' | 'manual';

/**
 * Full-screen install flow for CRM welcome email and ?install=pwa deep links.
 * Android: native install prompt when available. iOS: Add to Home Screen steps.
 */
export function TenantPwaInstallGate({ salonName, tenantSlug }: Props) {
  const router = useRouter();
  const [ready, setReady] = useState(false);
  const [phase, setPhase] = useState<Phase>('offer');
  const [installEvent, setInstallEvent] = useState<BeforeInstallPromptEvent | null>(null);
  const [busy, setBusy] = useState(false);
  const autoPrompted = useRef(false);
  const ios = useMemo(() => isIosDevice(), []);
  const installHint = useMemo(() => tenantCustomerPwaInstallHint(), []);

  const dismiss = useCallback(() => {
    router.replace(`/book/${encodeURIComponent(tenantSlug)}`);
  }, [router, tenantSlug]);

  const handleInstall = useCallback(async () => {
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
        return;
      }
      setPhase('manual');
    } finally {
      setBusy(false);
    }
  }, [installEvent, router, tenantSlug]);

  useEffect(() => {
    const onBeforeInstall = (event: Event) => {
      event.preventDefault();
      setInstallEvent(event as BeforeInstallPromptEvent);
    };
    window.addEventListener('beforeinstallprompt', onBeforeInstall);
    void registerMemberServiceWorker();

    void (async () => {
      if (isStandaloneDisplay()) {
        router.replace(tenantCustomerPwaPath(tenantSlug));
        return;
      }
      const skip = await shouldSkipBookingInstallGate(tenantSlug);
      if (skip) {
        dismiss();
        return;
      }
      setReady(true);
    })();

    return () => window.removeEventListener('beforeinstallprompt', onBeforeInstall);
  }, [dismiss, router, tenantSlug]);

  useEffect(() => {
    if (!ready || ios || !installEvent || autoPrompted.current) return;
    autoPrompted.current = true;
    void handleInstall();
  }, [ready, ios, installEvent, handleInstall]);

  if (!ready) {
    return null;
  }

  return (
    <div
      className="fixed inset-0 z-[120] flex flex-col bg-[var(--book-wash)] text-[var(--book-ink)]"
      role="dialog"
      aria-modal="true"
      aria-label={`Install ${salonName} app`}
    >
      <div className="flex flex-1 flex-col items-center justify-center overflow-y-auto px-6 py-10">
        <div className="w-full max-w-md text-center">
          <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[var(--book-moss)]">
            {salonName}
          </p>
          <h1 className="book-display mt-3 text-3xl font-bold text-[var(--book-ink)] sm:text-4xl">
            Install the {salonName} app
          </h1>
          <p className="mt-4 text-base leading-relaxed text-[var(--book-muted)]">
            Install the app so we can validate your membership and send you updates about your next
            visit.
          </p>

          {ios || phase === 'manual' ? (
            <div className="mt-8 rounded-2xl border border-[var(--book-line)] bg-white p-5 text-left">
              <p className="text-sm font-semibold text-[var(--book-ink)]">Add to Home Screen</p>
              <p className="mt-2 text-sm leading-relaxed text-[var(--book-muted)]">{installHint}</p>
              {!ios && phase === 'manual' ? (
                <button
                  type="button"
                  className="mt-4 inline-flex w-full items-center justify-center rounded-md bg-[var(--book-moss)] px-5 py-3 text-sm font-semibold text-white hover:bg-[var(--book-moss-deep)]"
                  onClick={() => router.replace(tenantCustomerPwaPath(tenantSlug))}
                >
                  I installed it — open the app
                </button>
              ) : null}
            </div>
          ) : (
            <div className="mt-8 flex flex-col gap-3">
              <button
                type="button"
                disabled={busy}
                className="inline-flex w-full items-center justify-center rounded-md bg-[var(--book-moss)] px-5 py-3 text-sm font-semibold text-white hover:bg-[var(--book-moss-deep)] disabled:opacity-60"
                onClick={() => void handleInstall()}
              >
                {busy ? 'Installing…' : 'Install App'}
              </button>
              {installEvent ? null : (
                <p className="text-xs text-[var(--book-muted)]">
                  If nothing happens, use your browser menu to install or add this page to your home
                  screen.
                </p>
              )}
            </div>
          )}
        </div>
      </div>

      <div className="border-t border-[var(--book-line)] bg-white/90 px-6 py-4">
        <button
          type="button"
          className="mx-auto block text-sm font-semibold text-[var(--book-muted)] underline-offset-2 hover:text-[var(--book-ink)] hover:underline"
          onClick={dismiss}
        >
          Continue to {salonName} booking page
        </button>
      </div>
    </div>
  );
}
