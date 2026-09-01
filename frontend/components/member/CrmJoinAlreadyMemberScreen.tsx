'use client';

import { useEffect, useMemo, useState } from 'react';
import { useRouter } from 'next/navigation';
import { registerMemberServiceWorker } from '@/services/member-portal.service';
import {
  isStandaloneDisplay,
  promptTenantCustomerPwaInstall,
  shouldSkipBookingInstallGate,
  tenantCustomerPwaInstallHint,
  tenantCustomerPwaPath,
  type BeforeInstallPromptEvent,
} from '@/lib/tenant-customer-pwa';

type Props = {
  tenantSlug: string;
  salonName: string;
  onLogin: () => void;
};

type Phase = 'main' | 'manual';

export function CrmJoinAlreadyMemberScreen({ tenantSlug, salonName, onLogin }: Props) {
  const router = useRouter();
  const [phase, setPhase] = useState<Phase>('main');
  const [installEvent, setInstallEvent] = useState<BeforeInstallPromptEvent | null>(null);
  const [busy, setBusy] = useState(false);
  const [pwaReady, setPwaReady] = useState(false);
  const [pwaInstalled, setPwaInstalled] = useState(false);

  const installHint = useMemo(() => tenantCustomerPwaInstallHint(), []);

  useEffect(() => {
    const onBeforeInstall = (event: Event) => {
      event.preventDefault();
      setInstallEvent(event as BeforeInstallPromptEvent);
    };
    window.addEventListener('beforeinstallprompt', onBeforeInstall);
    void registerMemberServiceWorker();

    void (async () => {
      const installed = await shouldSkipBookingInstallGate(tenantSlug);
      setPwaInstalled(installed || isStandaloneDisplay());
      setPwaReady(true);
    })();

    return () => window.removeEventListener('beforeinstallprompt', onBeforeInstall);
  }, [tenantSlug]);

  async function handleInstallApp() {
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

      if (result === 'manual') {
        setPhase('manual');
        return;
      }
    } finally {
      setBusy(false);
    }
  }

  function handleLogin() {
    if (pwaInstalled || isStandaloneDisplay()) {
      router.replace(tenantCustomerPwaPath(tenantSlug));
      return;
    }
    onLogin();
  }

  return (
    <div
      className="fixed inset-0 z-[100] flex flex-col bg-[var(--book-wash)] text-[var(--book-ink)]"
      role="dialog"
      aria-modal="true"
      aria-label="Already on member list"
    >
      <div className="flex flex-1 flex-col items-center justify-center overflow-y-auto px-6 py-10">
        <div className="w-full max-w-md text-center">
          <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[var(--book-moss)]">
            Welcome back
          </p>
          <h1 className="book-display mt-3 text-3xl font-bold text-[var(--book-ink)] sm:text-4xl">
            Oh, you are already on our member list
          </h1>
          <p className="mt-5 text-base leading-relaxed text-[var(--book-muted)]">
            Login instead to see more benefits from {salonName}.
          </p>

          {!pwaReady ? (
            <p className="mt-6 text-sm text-[var(--book-muted)]">Checking your app…</p>
          ) : (
            <div className="mt-8 flex flex-col gap-3">
              <button
                type="button"
                className="inline-flex w-full items-center justify-center rounded-md bg-[var(--book-moss)] px-5 py-3 text-sm font-semibold text-white hover:bg-[var(--book-moss-deep)]"
                onClick={handleLogin}
              >
                Log in
              </button>

              {!pwaInstalled ? (
                phase === 'manual' ? (
                  <div className="space-y-3 text-left">
                    <p className="rounded-lg border border-[var(--book-line)] bg-white px-4 py-3 text-sm text-[var(--book-ink)]">
                      {installHint}
                    </p>
                    <button
                      type="button"
                      className="inline-flex w-full items-center justify-center rounded-md border border-[var(--book-line)] bg-white px-5 py-3 text-sm font-semibold text-[var(--book-ink)] hover:bg-white/80"
                      onClick={() => router.replace(tenantCustomerPwaPath(tenantSlug))}
                    >
                      I installed it — open the app
                    </button>
                  </div>
                ) : (
                  <button
                    type="button"
                    disabled={busy}
                    className="inline-flex w-full items-center justify-center rounded-md border border-[var(--book-line)] bg-white px-5 py-3 text-sm font-semibold text-[var(--book-ink)] hover:bg-white/80 disabled:opacity-60"
                    onClick={() => void handleInstallApp()}
                  >
                    {busy ? 'Installing…' : 'Install App'}
                  </button>
                )
              ) : null}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
