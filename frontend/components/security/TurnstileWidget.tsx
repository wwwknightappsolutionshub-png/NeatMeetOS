'use client';

import { useEffect, useRef, useState } from 'react';
import {
  isTurnstileConfigured,
  mountTurnstileIn,
  triggerTurnstileChallenge,
  type TurnstileWidgetSize,
} from '@/lib/turnstile';

type Props = {
  className?: string;
  size?: TurnstileWidgetSize;
  hint?: string;
  /** Require the user to tap the widget before verification runs (no auto-pass). */
  deferExecution?: boolean;
};

/**
 * Visible Cloudflare Turnstile widget. Mount once per page/section that POSTs.
 */
export function TurnstileWidget({
  className = '',
  size = 'normal',
  hint = 'Complete the security check before submitting.',
  deferExecution = false,
}: Props) {
  const hostRef = useRef<HTMLDivElement>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const host = hostRef.current;
    if (!host || !isTurnstileConfigured()) return;

    const cleanup = mountTurnstileIn(host, {
      size,
      deferExecution,
      onError: (message) => setError(message),
    });

    return cleanup;
  }, [size, deferExecution]);

  if (!isTurnstileConfigured()) {
    return null;
  }

  return (
    <div className={className}>
      <div
        ref={hostRef}
        className={[
          'flex min-h-[65px] max-w-[200px] items-center justify-start',
          deferExecution ? 'cursor-pointer' : '',
        ].join(' ')}
        aria-live="polite"
        onClick={() => {
          if (deferExecution) triggerTurnstileChallenge();
        }}
        onKeyDown={(e) => {
          if (deferExecution && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            triggerTurnstileChallenge();
          }
        }}
        role={deferExecution ? 'button' : undefined}
        tabIndex={deferExecution ? 0 : undefined}
      />
      {error ? (
        <p className="mt-2 text-xs text-red-600">{error}</p>
      ) : (
        <p className="mt-2 text-xs text-stone-500">{hint}</p>
      )}
    </div>
  );
}
