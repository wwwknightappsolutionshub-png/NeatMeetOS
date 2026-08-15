'use client';

import { useEffect, useState } from 'react';

const HIGHLIGHTS = [
  {
    title: 'Verified availability',
    body: 'Live slots from the salon schedule',
  },
  {
    title: 'Instant confirmation',
    body: 'Your appointment is held immediately',
  },
  {
    title: 'Secure & private',
    body: 'Details used only for this booking',
  },
] as const;

const INTERVAL_MS = 4200;

export function BookingTrustCarousel() {
  const [index, setIndex] = useState(0);
  const [paused, setPaused] = useState(false);

  useEffect(() => {
    if (paused || HIGHLIGHTS.length <= 1) return;
    const id = window.setInterval(() => {
      setIndex((prev) => (prev + 1) % HIGHLIGHTS.length);
    }, INTERVAL_MS);
    return () => window.clearInterval(id);
  }, [paused]);

  const active = HIGHLIGHTS[index] ?? HIGHLIGHTS[0];

  return (
    <div
      className="border-b border-[var(--book-line)] bg-white"
      onMouseEnter={() => setPaused(true)}
      onMouseLeave={() => setPaused(false)}
      onFocusCapture={() => setPaused(true)}
      onBlurCapture={(e) => {
        if (!e.currentTarget.contains(e.relatedTarget as Node | null)) {
          setPaused(false);
        }
      }}
    >
      <div className="mx-auto flex max-w-5xl flex-col gap-3 px-4 py-4 sm:px-6">
        <div
          className="relative min-h-[3.25rem] overflow-hidden"
          aria-live="polite"
          aria-atomic="true"
        >
          {HIGHLIGHTS.map((item, i) => (
            <div
              key={item.title}
              className={`flex items-start gap-3 transition-all duration-500 ease-out ${
                i === index
                  ? 'relative translate-y-0 opacity-100'
                  : 'pointer-events-none absolute inset-0 translate-y-2 opacity-0'
              }`}
              aria-hidden={i !== index}
            >
              <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-[var(--book-moss)]" />
              <div>
                <p className="font-semibold text-[var(--book-ink)]">{item.title}</p>
                <p className="text-sm text-[var(--book-muted)]">{item.body}</p>
              </div>
            </div>
          ))}
        </div>

        <div className="flex items-center justify-between gap-3">
          <div className="flex items-center gap-1.5" role="tablist" aria-label="Booking highlights">
            {HIGHLIGHTS.map((item, i) => (
              <button
                key={item.title}
                type="button"
                role="tab"
                aria-selected={i === index}
                aria-label={item.title}
                className={`h-1.5 rounded-full transition-all ${
                  i === index
                    ? 'w-6 bg-[var(--book-moss)]'
                    : 'w-1.5 bg-[var(--book-line)] hover:bg-[var(--book-muted)]'
                }`}
                onClick={() => setIndex(i)}
              />
            ))}
          </div>
          <p className="text-xs tabular-nums text-[var(--book-muted)]">
            {index + 1} / {HIGHLIGHTS.length}
            <span className="sr-only">: {active.title}</span>
          </p>
        </div>
      </div>
    </div>
  );
}
