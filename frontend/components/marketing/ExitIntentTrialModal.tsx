'use client';

import { FormEvent, useEffect, useState } from 'react';
import { NeatMeetLogo } from '@/components/brand/NeatMeetLogo';
import { Button } from '@/components/ui/Button';
import { resolveReferralCode } from '@/lib/referral-cookie';
import { captureSignupLead } from '@/services/signup.service';

type Step = 'prompt' | 'form' | 'done';

type Props = {
  open: boolean;
  onClose: () => void;
  referralCode?: string | null;
  source?: 'exit' | 'cta';
};

const inputClass =
  'mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-2.5 text-sm text-stone-900 outline-none transition focus:border-[#2f5a45] focus:ring-2 focus:ring-[#2f5a45]/20';

const TRIAL_POINTS = [
  'Your salon workspace is ready in a few minutes',
  'Bookings, clients, till, and memberships included',
  'No bank card needed to start',
];

export function ExitIntentTrialModal({
  open,
  onClose,
  referralCode,
  source = 'exit',
}: Props) {
  const [step, setStep] = useState<Step>('prompt');
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [website, setWebsite] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [loginUrl, setLoginUrl] = useState<string | null>(null);

  useEffect(() => {
    if (open) {
      // Exit intent goes straight to capture for speed; CTA still shows the offer first.
      setStep(source === 'exit' ? 'form' : 'prompt');
      setError(null);
      setMessage(null);
      setLoginUrl(null);
    }
  }, [open, source]);

  if (!open) return null;

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      const ref = resolveReferralCode(referralCode);
      const result = await captureSignupLead({
        name: name.trim(),
        email: email.trim(),
        referral_code: ref,
        website,
      });
      setMessage(result.message);
      setLoginUrl(result.login_url);
      setStep('done');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not start your trial');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div
      className="fixed inset-0 z-[80] flex items-end justify-center bg-stone-950/55 p-4 backdrop-blur-[2px] sm:items-center"
      role="dialog"
      aria-modal="true"
      aria-labelledby="trial-modal-title"
      onClick={onClose}
    >
      <div
        className="flex w-full max-w-2xl overflow-hidden rounded-2xl border border-stone-200 bg-[#f3f1ec] shadow-2xl"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Brand panel */}
        <div className="hidden w-[42%] flex-col justify-between bg-[#2f5a45] p-6 text-white sm:flex">
          <div>
            <NeatMeetLogo size={40} variant="color" />
            <p className="mt-6 text-[11px] font-semibold uppercase tracking-[0.18em] text-white/70">
              Enterprise trial
            </p>
            <p className="mt-2 text-xl font-semibold leading-snug tracking-tight">
              Run the salon from one operating system.
            </p>
          </div>
          <ul className="mt-8 space-y-3 text-sm text-white/85">
            {TRIAL_POINTS.map((point) => (
              <li key={point} className="flex gap-2.5">
                <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white/15 text-[10px] font-bold">
                  ✓
                </span>
                <span>{point}</span>
              </li>
            ))}
          </ul>
        </div>

        {/* Content */}
        <div className="relative flex flex-1 flex-col p-6 sm:p-7">
          <button
            type="button"
            onClick={onClose}
            className="absolute right-4 top-4 text-stone-400 hover:text-stone-700"
            aria-label="Close dialog"
          >
            ✕
          </button>
          <div className="mb-5 flex items-center pr-8 sm:hidden">
            <NeatMeetLogo size={32} withWordmark variant="color" />
          </div>

          {step === 'prompt' ? (
            <>
              <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#2f5a45]">
                {source === 'cta' ? '30-day free trial' : "Don't leave empty-handed"}
              </p>
              <h2
                id="trial-modal-title"
                className="mt-2 text-2xl font-semibold tracking-tight text-stone-900 sm:text-[1.7rem]"
              >
                Don&apos;t Go Yet!
              </h2>
              <p className="mt-2 text-sm leading-relaxed text-stone-600">
                We&apos;re rewarding you <strong className="font-semibold text-stone-800">30 days free trial</strong> —
                claim access now and finish Creating Your Workspace when you&apos;re ready.
              </p>
              <ul className="mt-5 space-y-2 border-y border-stone-200/90 py-4 sm:hidden">
                {TRIAL_POINTS.map((point) => (
                  <li key={point} className="flex gap-2 text-sm text-stone-600">
                    <span className="font-semibold text-[#2f5a45]">✓</span>
                    {point}
                  </li>
                ))}
              </ul>
              <div className="mt-6 flex flex-col gap-2 sm:flex-row">
                <Button
                  type="button"
                  className="flex-1 !bg-[#2f5a45]"
                  onClick={() => setStep('form')}
                >
                  Start now
                </Button>
                <Button type="button" variant="secondary" onClick={onClose}>
                  Close
                </Button>
              </div>
            </>
          ) : null}

          {step === 'form' ? (
            <>
              <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#2f5a45]">
                {source === 'cta' ? '30-day free trial' : "Don't leave yet"}
              </p>
              <h2
                id="trial-modal-title"
                className="mt-2 text-xl font-semibold text-stone-900"
              >
                {source === 'exit' ? "Don't Go Yet! — claim 30 days free" : 'Claim your trial'}
              </h2>
              <p className="mt-1 text-sm text-stone-600">
                Enter your name and email. We will send a login link and a temporary password.
              </p>
              <form onSubmit={handleSubmit} className="mt-5 space-y-3">
                <label className="block text-sm">
                  <span className="font-medium text-stone-700">Name</span>
                  <input
                    className={inputClass}
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    required
                    minLength={2}
                    autoComplete="name"
                  />
                </label>
                <label className="block text-sm">
                  <span className="font-medium text-stone-700">Work email</span>
                  <input
                    type="email"
                    className={inputClass}
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    required
                    autoComplete="email"
                  />
                </label>
                <label className="absolute left-[-9999px] h-0 w-0 overflow-hidden" aria-hidden>
                  Website
                  <input
                    tabIndex={-1}
                    autoComplete="off"
                    value={website}
                    onChange={(e) => setWebsite(e.target.value)}
                  />
                </label>
                {error ? <p className="text-sm text-red-600">{error}</p> : null}
                <div className="flex flex-col gap-2 pt-1 sm:flex-row">
                  <Button type="submit" disabled={loading} className="flex-1 !bg-[#2f5a45]">
                    {loading ? 'Sending…' : 'Claim my trial'}
                  </Button>
                  <Button type="button" variant="secondary" onClick={onClose}>
                    Cancel
                  </Button>
                </div>
                <p className="text-[11px] leading-relaxed text-stone-400">
                  By continuing you agree to receive your trial login credentials by email.
                </p>
              </form>
            </>
          ) : null}

          {step === 'done' ? (
            <>
              <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#2f5a45]">
                Almost there
              </p>
              <h2
                id="trial-modal-title"
                className="mt-2 text-xl font-semibold text-stone-900"
              >
                Check your inbox
              </h2>
              <p className="mt-2 text-sm leading-relaxed text-stone-600">
                {message ||
                  'We’ve sent your NeatMeet OS login details. Use them to finish Creating Your Workspace.'}
              </p>
              <div className="mt-6 flex flex-col gap-2 sm:flex-row">
                {loginUrl ? (
                  <a
                    href={loginUrl}
                    className="inline-flex flex-1 items-center justify-center rounded-lg bg-[#2f5a45] px-4 py-2.5 text-sm font-semibold text-white"
                  >
                    Go to login
                  </a>
                ) : null}
                <Button type="button" variant="secondary" onClick={onClose}>
                  Close
                </Button>
              </div>
            </>
          ) : null}
        </div>
      </div>
    </div>
  );
}
