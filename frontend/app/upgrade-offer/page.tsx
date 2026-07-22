'use client';

import Link from 'next/link';
import { Suspense, useEffect, useMemo, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { Button } from '@/components/ui/Button';
import { getStoredToken } from '@/lib/api-client';
import type { UpgradeOfferPreview } from '@/lib/types';
import { claimUpgradeOffer, fetchUpgradeOffer } from '@/services/upgrade-offer.service';

export default function UpgradeOfferPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-full items-center justify-center bg-stone-100 p-6 text-sm text-stone-500">
          Loading offer…
        </div>
      }
    >
      <UpgradeOfferInner />
    </Suspense>
  );
}

function UpgradeOfferInner() {
  const params = useSearchParams();
  const router = useRouter();
  const token = params.get('token') ?? '';
  const [offer, setOffer] = useState<UpgradeOfferPreview | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [claiming, setClaiming] = useState(false);
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    const id = window.setInterval(() => setNow(Date.now()), 1000);
    return () => window.clearInterval(id);
  }, []);

  useEffect(() => {
    if (!token) {
      setError('This upgrade link is missing a token.');
      return;
    }
    if (!getStoredToken()) {
      router.replace(`/login?next=${encodeURIComponent(`/upgrade-offer?token=${token}`)}`);
      return;
    }
    void fetchUpgradeOffer(token)
      .then(setOffer)
      .catch((e) => setError(e instanceof Error ? e.message : 'Offer unavailable'));
  }, [token, router]);

  const countdown = useMemo(() => {
    const end = offer?.trial_ends_at ? new Date(offer.trial_ends_at).getTime() : null;
    if (!end) return null;
    const ms = Math.max(0, end - now);
    const totalSec = Math.floor(ms / 1000);
    const days = Math.floor(totalSec / 86400);
    const hours = Math.floor((totalSec % 86400) / 3600);
    const minutes = Math.floor((totalSec % 3600) / 60);
    const seconds = totalSec % 60;
    return { days, hours, minutes, seconds, ended: ms <= 0 };
  }, [offer?.trial_ends_at, now]);

  async function onClaim() {
    if (!token) return;
    setClaiming(true);
    setError(null);
    setMessage(null);
    try {
      const result = await claimUpgradeOffer(token);
      setMessage(result.message);
      setOffer((prev) =>
        prev
          ? {
              ...prev,
              status: result.status,
              code: result.code,
            }
          : prev,
      );
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not claim discount');
    } finally {
      setClaiming(false);
    }
  }

  const target =
    offer?.path === 'pro_to_diamond' ? 'Diamond' : offer?.path === 'basic_to_pro' ? 'Pro' : 'next tier';

  return (
    <div className="relative flex min-h-full flex-col bg-[linear-gradient(165deg,#faf7f2_0%,#efe6d8_45%,#e7d5b8_100%)]">
      <div
        className="pointer-events-none absolute inset-0 opacity-40"
        style={{
          backgroundImage:
            'radial-gradient(circle at 20% 20%, rgba(120,53,15,0.08), transparent 40%), radial-gradient(circle at 80% 0%, rgba(28,25,23,0.08), transparent 35%)',
        }}
      />
      <main className="relative mx-auto flex w-full max-w-xl flex-1 flex-col justify-center px-4 py-12">
        <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-500">
          NeatMeet OS
        </p>
        <h1 className="mt-2 text-3xl font-semibold tracking-tight text-stone-900">
          Upgrade Within this time
        </h1>
        <p className="mt-2 text-sm leading-relaxed text-stone-600">
          {offer
            ? `Claim ${offer.percent}% off ${target} for ${offer.tenant.name} before the trial window closes.`
            : 'Open your trial upgrade offer and claim the discount before time runs out.'}
        </p>

        {error ? (
          <p className="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {error}
          </p>
        ) : null}
        {message ? (
          <p className="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            {message}
          </p>
        ) : null}

        {offer ? (
          <div className="mt-8 rounded-2xl border border-stone-200/80 bg-white/80 p-5 shadow-sm backdrop-blur">
            {countdown ? (
              <div className="grid grid-cols-4 gap-2 text-center">
                {[
                  ['Days', countdown.days],
                  ['Hours', countdown.hours],
                  ['Min', countdown.minutes],
                  ['Sec', countdown.seconds],
                ].map(([label, value]) => (
                  <div
                    key={String(label)}
                    className="rounded-xl bg-stone-900 px-2 py-3 text-white"
                  >
                    <p className="text-2xl font-semibold tabular-nums">{value}</p>
                    <p className="text-[10px] uppercase tracking-[0.14em] text-stone-400">
                      {label}
                    </p>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-sm text-stone-500">Trial end date unavailable.</p>
            )}

            <div className="mt-5 flex flex-wrap items-center justify-between gap-3">
              <div>
                <p className="text-xs uppercase tracking-[0.14em] text-stone-500">Offer code</p>
                <p className="font-mono text-lg font-semibold text-stone-900">{offer.code}</p>
              </div>
              <Button
                type="button"
                disabled={claiming || offer.status === 'redeemed' || offer.status === 'expired'}
                onClick={() => void onClaim()}
              >
                {claiming
                  ? 'Claiming…'
                  : offer.status === 'claimed'
                    ? 'Discount claimed'
                    : `Claim ${offer.percent}% Discount`}
              </Button>
            </div>

            <div className="mt-4 flex flex-wrap gap-3 text-sm">
              <Link
                href="/admin/settings/subscription"
                className="font-medium text-stone-800 underline underline-offset-2"
              >
                Open subscription
              </Link>
              <Link href="/admin/dashboard" className="text-stone-500 underline underline-offset-2">
                Back to admin
              </Link>
            </div>
          </div>
        ) : null}
      </main>
    </div>
  );
}
