'use client';

import { useEffect, useState } from 'react';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import { api } from '@/lib/api-client';

interface TenantReferralShare {
  enabled: boolean;
  code: string | null;
  share_url: string | null;
  headline: string | null;
  body: string | null;
  reward_type: string;
  reward_amount: number;
  qualification_goal: string;
  conversions_count: number;
}

function buildShareText(data: TenantReferralShare): string {
  const intro =
    data.body ||
    'Try NeatMeet OS — bookings, clients, and payments in one place. Start a free trial:';
  return data.share_url ? `${intro}\n\n${data.share_url}` : intro;
}

export default function AdminReferralsPage() {
  const [data, setData] = useState<TenantReferralShare | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    api<TenantReferralShare>('/admin/platform-referral', { auth: true })
      .then(setData)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load referral programme'));
  }, []);

  const shareText = data ? buildShareText(data) : '';

  async function copyLink() {
    if (!data?.share_url) return;
    await navigator.clipboard.writeText(data.share_url);
    setCopied(true);
    window.setTimeout(() => setCopied(false), 2000);
  }

  async function shareNative() {
    if (!data?.share_url) return;
    if (typeof navigator.share === 'function') {
      try {
        await navigator.share({
          title: data.headline || 'NeatMeet OS',
          text: data.body || shareText,
          url: data.share_url,
        });
        return;
      } catch {
        // Fall through to clipboard
      }
    }
    await copyLink();
  }

  function shareWhatsApp() {
    if (!data?.share_url) return;
    const url = `https://wa.me/?text=${encodeURIComponent(shareText)}`;
    window.open(url, '_blank', 'noopener,noreferrer');
  }

  function shareEmail() {
    if (!data?.share_url) return;
    const subject = encodeURIComponent(data.headline || 'Try NeatMeet OS');
    const body = encodeURIComponent(shareText);
    window.location.href = `mailto:?subject=${subject}&body=${body}`;
  }

  if (!data && !error) return <LoadingState label="Loading referral programme…" />;

  return (
    <div className="mx-auto max-w-xl space-y-4">
      <div>
        <p className="text-xs font-semibold uppercase tracking-[0.16em] text-zinc-500">Settings</p>
        <h1 className="mt-1 text-2xl font-semibold text-zinc-900">Refer &amp; get rewarded</h1>
        <p className="mt-1 text-sm text-zinc-500">
          Share your invite link to the NeatMeet OS landing page. When a new business signs
          up with your code and activates, you earn the platform reward.
        </p>
      </div>

      {error ? <ErrorAlert message={error} /> : null}

      {data && !data.enabled ? (
        <Card title="Programme paused">
          <p className="text-sm text-zinc-600">
            The platform referral programme is not active right now. Ask a platform admin to
            enable it under Platform → Referrals.
          </p>
        </Card>
      ) : null}

      {data?.enabled ? (
        <Card title="Your marketing invite link">
          <p className="text-sm font-medium text-zinc-800">{data.headline}</p>
          <p className="mt-1 text-sm text-zinc-600">{data.body}</p>

          <div className="mt-4 rounded-lg border border-[#2f5a45]/20 bg-[#e8f0eb] px-3 py-2.5 text-sm text-[#1c1917]">
            This link opens the <strong>NeatMeet OS landing page</strong> with your referral
            code attached (<span className="font-mono">{data.code}</span>). Visitors can claim
            a 30-day trial; you get credited when they activate. WhatsApp and email shares use
            the same landing URL so link previews show the NeatMeet OS card.
          </div>

          <p className="mt-4 text-xs uppercase tracking-wide text-zinc-500">Code</p>
          <p className="font-mono text-lg font-semibold">{data.code}</p>

          <p className="mt-3 text-xs uppercase tracking-wide text-zinc-500">Landing page URL</p>
          <p className="mt-1 break-all rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-700">
            {data.share_url}
          </p>

          <div className="mt-3 flex flex-wrap gap-2">
            <button
              type="button"
              className="rounded-lg bg-[var(--admin-accent)] px-3 py-2 text-sm font-semibold text-white"
              onClick={() => void copyLink()}
            >
              {copied ? 'Copied' : 'Copy link'}
            </button>
            <button
              type="button"
              className="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-semibold text-zinc-800 hover:bg-zinc-50"
              onClick={() => {
                if (data.share_url) window.open(data.share_url, '_blank', 'noopener,noreferrer');
              }}
            >
              Open landing page
            </button>
            <button
              type="button"
              className="rounded-lg border border-[#25D366]/40 bg-[#25D366]/10 px-3 py-2 text-sm font-semibold text-[#128C7E] hover:bg-[#25D366]/15"
              onClick={shareWhatsApp}
            >
              Share on WhatsApp
            </button>
            <button
              type="button"
              className="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-semibold text-zinc-800 hover:bg-zinc-50"
              onClick={shareEmail}
            >
              Share by email
            </button>
            <button
              type="button"
              className="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-semibold text-zinc-800 hover:bg-zinc-50"
              onClick={() => void shareNative()}
            >
              Share…
            </button>
          </div>

          <p className="mt-4 text-xs text-zinc-500">
            Successful referrals: {data.conversions_count}. Reward:{' '}
            {data.reward_type === 'subscription_days'
              ? `${data.reward_amount} subscription day(s)`
              : `£${(data.reward_amount / 100).toFixed(2)} credit`}
            .
          </p>
        </Card>
      ) : null}
    </div>
  );
}
