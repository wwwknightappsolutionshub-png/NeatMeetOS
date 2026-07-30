'use client';

import type { CSSProperties } from 'react';

interface Props {
  salonName: string;
  brandPrimary?: string;
  brandLogo?: string | null;
  onStart: () => void;
  onSkip: () => void;
}

export function AiHairstyleLandingGate({
  salonName,
  brandPrimary,
  brandLogo,
  onStart,
  onSkip,
}: Props) {
  return (
    <div
      className="fixed inset-0 z-[60] flex min-h-full flex-col bg-[var(--book-wash,#f6f4f1)] text-[var(--book-ink,#18181b)]"
      style={
        brandPrimary
          ? ({
              ['--book-moss' as string]: brandPrimary,
              ['--book-moss-deep' as string]: brandPrimary,
            } as CSSProperties)
          : undefined
      }
      role="dialog"
      aria-modal="true"
      aria-labelledby="ai-hairstyle-landing-title"
    >
      <div className="mx-auto flex w-full max-w-lg flex-1 flex-col justify-center px-5 py-10 sm:px-8">
        <div className="flex items-center gap-3">
          {brandLogo ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={brandLogo} alt="" className="h-10 w-10 rounded-md object-cover" />
          ) : (
            <span className="flex h-10 w-10 items-center justify-center rounded-md bg-[var(--book-moss,#3f5d4a)] text-sm font-bold text-white">
              {salonName.slice(0, 1).toUpperCase()}
            </span>
          )}
          <p className="text-sm font-semibold text-[var(--book-ink)]">{salonName}</p>
        </div>

        <h1
          id="ai-hairstyle-landing-title"
          className="book-display mt-10 text-4xl font-bold leading-[1.08] tracking-tight sm:text-5xl"
        >
          How would you like to look today?
        </h1>
        <p className="mt-4 max-w-md text-base leading-relaxed text-[var(--book-muted,#71717a)]">
          Preview a new style before you book — or skip straight to appointments.
        </p>

        <div className="mt-10 flex flex-col gap-3">
          <button
            type="button"
            onClick={onStart}
            className="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-[var(--book-moss,#3f5d4a)] px-5 text-base font-semibold text-white transition hover:opacity-95"
          >
            Try an AI look
          </button>
          <button
            type="button"
            onClick={onSkip}
            className="inline-flex min-h-12 w-full items-center justify-center rounded-xl border border-[var(--book-line,#e4e4e7)] bg-white px-5 text-base font-semibold text-[var(--book-ink)] transition hover:bg-white/80"
          >
            Skip to booking
          </button>
        </div>
      </div>
    </div>
  );
}
