'use client';

import { useEffect, useRef, useState } from 'react';
import { api } from '@/lib/api-client';

const SNOOZE_KEY = 'nm_admin_pwa_snooze_until';
const INSTALLED_KEY = 'nm_admin_pwa_installed';
const SNOOZE_MS = 240_000;

function urlBase64ToUint8Array(base64String: string): BufferSource {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = window.atob(base64);
  const output = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; i += 1) {
    output[i] = raw.charCodeAt(i);
  }
  return output.buffer.slice(
    output.byteOffset,
    output.byteOffset + output.byteLength,
  ) as ArrayBuffer;
}

async function registerAdminServiceWorker(): Promise<ServiceWorkerRegistration | null> {
  if (typeof window === 'undefined' || !('serviceWorker' in navigator)) return null;
  try {
    return await navigator.serviceWorker.register('/admin/sw.js', { scope: '/admin' });
  } catch {
    return null;
  }
}

async function subscribeOwnerPush(vapidKey: string): Promise<void> {
  if (!('PushManager' in window) || !('serviceWorker' in navigator)) return;
  const reg = await navigator.serviceWorker.ready;
  let sub = await reg.pushManager.getSubscription();
  if (!sub) {
    sub = await reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(vapidKey),
    });
  }
  const json = sub.toJSON();
  await api('/admin/owner-push-subscriptions', {
    method: 'POST',
    auth: true,
    body: JSON.stringify({
      endpoint: json.endpoint,
      keys: json.keys,
    }),
  });
}

function isStandalone(): boolean {
  if (typeof window === 'undefined') return false;
  return (
    window.matchMedia('(display-mode: standalone)').matches ||
    // iOS Safari
    Boolean((navigator as Navigator & { standalone?: boolean }).standalone)
  );
}

interface AdminPwaPromptProps {
  vapidPublicKey?: string | null;
}

/**
 * Install prompt for tenant admin workspace.
 * "Not now" snoozes for 240s then reappears until the tenant installs.
 */
export function AdminPwaPrompt({ vapidPublicKey }: AdminPwaPromptProps) {
  const [installPrompt, setInstallPrompt] = useState<{
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: string }>;
  } | null>(null);
  const [visible, setVisible] = useState(false);
  const [enableNotifications, setEnableNotifications] = useState(true);
  const [busy, setBusy] = useState(false);
  const [installed, setInstalled] = useState(false);
  const askedRef = useRef(false);
  const snoozeTimerRef = useRef<number | null>(null);

  useEffect(() => {
    void registerAdminServiceWorker();
  }, []);

  useEffect(() => {
    if (typeof window === 'undefined') return;

    if (isStandalone() || window.localStorage.getItem(INSTALLED_KEY) === '1') {
      setInstalled(true);
      return;
    }

    const showWhenAllowed = () => {
      if (isStandalone()) {
        setInstalled(true);
        return;
      }
      const until = Number(window.localStorage.getItem(SNOOZE_KEY) || '0');
      const remaining = until - Date.now();
      if (remaining > 0) {
        if (snoozeTimerRef.current != null) window.clearTimeout(snoozeTimerRef.current);
        snoozeTimerRef.current = window.setTimeout(() => {
          setVisible(true);
        }, remaining);
        return;
      }
      setVisible(true);
    };

    const onBeforeInstall = (e: Event) => {
      e.preventDefault();
      const evt = e as Event & {
        prompt: () => Promise<void>;
        userChoice: Promise<{ outcome: string }>;
      };
      setInstallPrompt({ prompt: () => evt.prompt(), userChoice: evt.userChoice });
      showWhenAllowed();
    };
    window.addEventListener('beforeinstallprompt', onBeforeInstall);

    const initial = window.setTimeout(showWhenAllowed, 2500);

    return () => {
      window.removeEventListener('beforeinstallprompt', onBeforeInstall);
      window.clearTimeout(initial);
      if (snoozeTimerRef.current != null) window.clearTimeout(snoozeTimerRef.current);
    };
  }, []);

  useEffect(() => {
    if (!installed || askedRef.current || !enableNotifications) return;
    if (typeof Notification === 'undefined') return;
    askedRef.current = true;
    void (async () => {
      try {
        if (Notification.permission === 'default') {
          await Notification.requestPermission();
        }
        if (Notification.permission === 'granted' && vapidPublicKey) {
          await subscribeOwnerPush(vapidPublicKey);
        }
      } catch {
        // Permission or VAPID may fail locally without keys.
      }
    })();
  }, [installed, enableNotifications, vapidPublicKey]);

  async function handleInstall() {
    setBusy(true);
    try {
      if (installPrompt) {
        await installPrompt.prompt();
        const choice = await installPrompt.userChoice;
        if (choice.outcome === 'accepted') {
          window.localStorage.setItem(INSTALLED_KEY, '1');
          window.localStorage.removeItem(SNOOZE_KEY);
          setInstalled(true);
          setVisible(false);
          setInstallPrompt(null);
        }
      } else {
        // Manual path (iOS): treat acknowledge as installed intent and stop prompting.
        window.localStorage.setItem(INSTALLED_KEY, '1');
        window.localStorage.removeItem(SNOOZE_KEY);
        setInstalled(true);
        setVisible(false);
      }
    } finally {
      setBusy(false);
    }
  }

  function dismiss() {
    const until = Date.now() + SNOOZE_MS;
    window.localStorage.setItem(SNOOZE_KEY, String(until));
    setVisible(false);
    if (snoozeTimerRef.current != null) window.clearTimeout(snoozeTimerRef.current);
    snoozeTimerRef.current = window.setTimeout(() => {
      if (!isStandalone() && window.localStorage.getItem(INSTALLED_KEY) !== '1') {
        setVisible(true);
      }
    }, SNOOZE_MS);
  }

  if (!visible || installed) return null;

  return (
    <div className="fixed bottom-4 right-4 z-50 max-w-sm rounded-xl border border-stone-200 bg-white p-4 shadow-lg">
      <p className="text-sm font-semibold text-stone-900">Install NeatMeet workspace</p>
      <p className="mt-1 text-xs text-stone-500">
        Add the admin app to your device for faster access and platform reminders.
      </p>
      <label className="mt-3 flex items-start gap-2 text-xs text-stone-700">
        <input
          type="checkbox"
          className="mt-0.5"
          checked={enableNotifications}
          onChange={(e) => setEnableNotifications(e.target.checked)}
        />
        <span>Enable push notifications after install (recommended)</span>
      </label>
      <div className="mt-3 flex gap-2">
        <button
          type="button"
          disabled={busy}
          onClick={() => void handleInstall()}
          className="rounded-lg bg-[var(--admin-accent)] px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
        >
          {installPrompt ? 'Install' : 'Got it'}
        </button>
        <button
          type="button"
          onClick={dismiss}
          className="rounded-lg border border-stone-200 px-3 py-1.5 text-xs font-medium text-stone-600"
        >
          Not now
        </button>
      </div>
    </div>
  );
}
