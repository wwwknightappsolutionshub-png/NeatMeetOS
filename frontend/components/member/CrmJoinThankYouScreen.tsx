'use client';

import Link from 'next/link';
import { useEffect, useMemo, useState } from 'react';
import { useRouter } from 'next/navigation';
import { fireConfettiBurst } from '@/lib/confetti-burst';
import { registerMemberServiceWorker } from '@/services/member-portal.service';
import {
  isStandaloneDisplay,
  markCrmInstallNudgeSession,
  promptTenantCustomerPwaInstall,
  tenantCustomerPwaInstallHint,
  tenantCustomerPwaPath,
  type BeforeInstallPromptEvent,
} from '@/lib/tenant-customer-pwa';

type Props = {
  tenantSlug: string;
  salonName: string;
  customerName: string;
  luckyPosition: number;
  luckyCap: number;
  luckyEligible: boolean;
};

type Phase = 'main' | 'manual';

export function CrmJoinThankYouScreen({
  tenantSlug,
  salonName,
  customerName,
  luckyPosition,
  luckyCap,
  luckyEligible,
}: Props) {
  const router = useRouter();
  const [phase, setPhase] = useState<Phase>('main');
  const [installEvent, setInstallEvent] = useState<BeforeInstallPromptEvent | null>(null);
  const [busy, setBusy] = useState(false);
  const [notificationHint, setNotificationHint] = useState<string | null>(null);

  const installHint = useMemo(() => tenantCustomerPwaInstallHint(), []);
  const showLuckyMessage = luckyEligible && luckyPosition > 0 && luckyPosition <= luckyCap;
  const bookingHref = `/book/${encodeURIComponent(tenantSlug)}`;
  const displayName = customerName.trim() || 'there';

  useEffect(() => {
    fireConfettiBurst();
  }, []);

  useEffect(() => {
    const onBeforeInstall = (event: Event) => {
      event.preventDefault();
      setInstallEvent(event as BeforeInstallPromptEvent);
    };
    window.addEventListener('beforeinstallprompt', onBeforeInstall);
    void registerMemberServiceWorker();
    return () => window.removeEventListener('beforeinstallprompt', onBeforeInstall);
  }, []);

  async function requestNotifications(): Promise<void> {
    if (typeof Notification === 'undefined') {
      return;
    }
    if (Notification.permission === 'granted') {
      return;
    }
    try {
      const result = await Notification.requestPermission();
      if (result === 'denied') {
        setNotificationHint(
          'Notifications are blocked in your browser settings. You can enable them later in the app.',
        );
      }
    } catch {
      // ignore
    }
  }

  async function handleInstallApp() {
    setBusy(true);
    try {
      const result = await promptTenantCustomerPwaInstall(
        tenantSlug,
        installEvent,
        (path) => router.replace(path),
      );

      if (result === 'accepted' || result === 'already_standalone') {
        await requestNotifications();
        router.replace(tenantCustomerPwaPath(tenantSlug));
        return;
      }

      if (result === 'manual') {
        setPhase('manual');
        return;
      }

      await requestNotifications();
    } finally {
      setBusy(false);
    }
  }

  async function handleManualInstalled() {
    await requestNotifications();
    router.replace(tenantCustomerPwaPath(tenantSlug));
  }

  return (
    <div
      className="fixed inset-0 z-[100] flex flex-col bg-[var(--book-wash)] text-[var(--book-ink)]"
      role="dialog"
      aria-modal="true"
      aria-label={`Welcome ${displayName}`}
    >
      <div className="flex flex-1 flex-col items-center justify-center overflow-y-auto px-6 py-10">
        <div className="w-full max-w-md text-center">
          <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[var(--book-moss)]">
            Welcome &ldquo;{displayName}&rdquo;
          </p>
          <h1 className="book-display mt-3 text-3xl font-bold text-[var(--book-ink)] sm:text-4xl">
            Thank You For Joining Our Customer List!
          </h1>

          {showLuckyMessage ? (
            <p className="mt-5 text-base leading-relaxed text-[var(--book-muted)]">
              You are{' '}
              <span className="font-bold text-[var(--book-moss)]">
                {luckyPosition} / {luckyCap}
              </span>{' '}
              lucky customer.
            </p>
          ) : null}

          <p className="mt-4 text-base leading-relaxed text-[var(--book-muted)]">
            {showLuckyMessage ? (
              <>
                We are glad to inform you that your next visit to &ldquo;{salonName}&rdquo; will be
                discounted. This is our way of saying &ldquo;Thank You&rdquo;. Please note that you
                need to install our mobile app so we can validate you at your next visit.
              </>
            ) : (
              <>
                We are glad you joined &ldquo;{salonName}&rdquo;. Please install our mobile app so we
                can validate you at your next visit.
              </>
            )}
          </p>

          <p className="mt-4 text-sm leading-relaxed text-[var(--book-muted)]">
            Click the button below to install the app and enable notifications.
          </p>

          {notificationHint ? (
            <p className="mt-4 rounded-lg border border-[var(--book-line)] bg-white px-3 py-2 text-sm text-[var(--book-muted)]">
              {notificationHint}
            </p>
          ) : null}

          {phase === 'manual' ? (
            <div className="mt-8 space-y-3 text-left">
              <p className="rounded-lg border border-[var(--book-line)] bg-white px-4 py-3 text-sm text-[var(--book-ink)]">
                {installHint}
              </p>
              <button
                type="button"
                className="inline-flex w-full items-center justify-center rounded-md bg-[var(--book-moss)] px-5 py-3 text-sm font-semibold text-white hover:bg-[var(--book-moss-deep)]"
                onClick={() => void handleManualInstalled()}
              >
                I installed it — open the app
              </button>
            </div>
          ) : (
            <div className="mt-8 flex flex-col gap-3">
              <button
                type="button"
                disabled={busy}
                className="inline-flex w-full items-center justify-center rounded-md bg-[var(--book-moss)] px-5 py-3 text-sm font-semibold text-white hover:bg-[var(--book-moss-deep)] disabled:opacity-60"
                onClick={() => void handleInstallApp()}
              >
                {busy ? 'Installing…' : 'Install App'}
              </button>
              {!isStandaloneDisplay() ? (
                <button
                  type="button"
                  className="inline-flex w-full items-center justify-center rounded-md border border-[var(--book-line)] bg-white px-5 py-3 text-sm font-semibold text-[var(--book-ink)] hover:bg-white/80"
                  onClick={() => void requestNotifications()}
                >
                  Enable notifications
                </button>
              ) : null}
            </div>
          )}
        </div>
      </div>

      <div className="border-t border-[var(--book-line)] bg-white/90 px-6 py-4">
        <Link
          href={bookingHref}
          onClick={() => markCrmInstallNudgeSession(tenantSlug)}
          className="mx-auto block text-center text-sm font-semibold text-[var(--book-muted)] underline-offset-2 hover:text-[var(--book-ink)] hover:underline"
        >
          Continue without installing
        </Link>
      </div>
    </div>
  );
}
