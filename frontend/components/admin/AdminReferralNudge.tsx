'use client';

import Link from 'next/link';
import { useEffect, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';

const DELAY_MS = 300_000;
const STORAGE_KEY = 'nm_admin_referral_nudge_day';

function todayKey(): string {
  const d = new Date();
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function alreadyShownToday(): boolean {
  if (typeof window === 'undefined') return true;
  return window.localStorage.getItem(STORAGE_KEY) === todayKey();
}

function markShownToday(): void {
  window.localStorage.setItem(STORAGE_KEY, todayKey());
}

/**
 * Full-screen referral nudge after 300s on the admin app.
 * Shows at most once per calendar day (not recursive within the session).
 */
export function AdminReferralNudge() {
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const timerRef = useRef<number | null>(null);
  const firedRef = useRef(false);

  useEffect(() => {
    if (typeof window === 'undefined') return;
    if (alreadyShownToday()) return;

    timerRef.current = window.setTimeout(() => {
      if (firedRef.current || alreadyShownToday()) return;
      firedRef.current = true;
      markShownToday();
      setOpen(true);
    }, DELAY_MS);

    return () => {
      if (timerRef.current != null) window.clearTimeout(timerRef.current);
    };
  }, []);

  function dismiss() {
    markShownToday();
    setOpen(false);
  }

  function referNow() {
    markShownToday();
    setOpen(false);
    router.push('/admin/settings/referrals');
  }

  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-[80] flex items-center justify-center bg-stone-950/55 p-4 backdrop-blur-[2px]"
      role="dialog"
      aria-modal="true"
      aria-labelledby="admin-referral-nudge-title"
    >
      <div className="relative w-full max-w-md overflow-hidden rounded-2xl border border-[#e8d9b0] bg-gradient-to-br from-[#fffdf8] via-white to-[#f7eccf] p-6 shadow-2xl sm:p-8">
        <button
          type="button"
          onClick={dismiss}
          className="absolute right-3 top-3 rounded-lg px-2 py-1 text-sm text-stone-500 hover:bg-stone-100 hover:text-stone-800"
          aria-label="Dismiss"
        >
          ✕
        </button>
        <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#8a6a24]">
          Refer &amp; Reward
        </p>
        <h2
          id="admin-referral-nudge-title"
          className="mt-2 text-2xl font-semibold tracking-tight text-[#1c1917]"
        >
          Have you referred today
        </h2>
        <p className="mt-3 text-sm leading-relaxed text-[#57534e] sm:text-base">
          Extend your free trial for extra 30 Days when you refer others to NeatMeetOS
        </p>
        <div className="mt-6 flex flex-col gap-2 sm:flex-row sm:items-center">
          <button
            type="button"
            onClick={referNow}
            className="inline-flex items-center justify-center rounded-lg border-2 border-[#c4a35a] bg-[#6b4f12] px-4 py-2.5 text-sm font-bold text-white hover:brightness-110"
          >
            Refer Now
          </button>
          <button
            type="button"
            onClick={dismiss}
            className="inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-medium text-stone-600 hover:bg-stone-100"
          >
            Not now
          </button>
        </div>
        <p className="mt-4 text-xs text-stone-500">
          Or open{' '}
          <Link href="/admin/settings/referrals" onClick={dismiss} className="font-medium underline">
            Settings → Refer &amp; reward
          </Link>
          .
        </p>
      </div>
    </div>
  );
}
