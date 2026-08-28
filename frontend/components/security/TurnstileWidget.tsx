'use client';

import { useEffect, useRef, useState } from 'react';
import {
  isTurnstileConfigured,
  mountTurnstileIn,
  type TurnstileWidgetSize,
} from '@/lib/turnstile';

type Props = {
  className?: string;
  size?: TurnstileWidgetSize;
  hint?: string;
};

/**
 * Visible Cloudflare Turnstile widget. Mount once per page/section that POSTs.
 */
export function TurnstileWidget({
  className = '',
  size = 'normal',
  hint = 'Complete the security check before submitting.',
}: Props) {
  const hostRef = useRef<HTMLDivElement>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const host = hostRef.current;
    if (!host || !isTurnstileConfigured()) return;

    const cleanup = mountTurnstileIn(host, {
      size,
      onError: (message) => setError(message),
    });

    return cleanup;
  }, [size]);

  if (!isTurnstileConfigured()) {
    return null;
  }

  return (
    <div className={className}>
      <div
        ref={hostRef}
        className="flex min-h-[65px] items-center justify-start"
        aria-live="polite"
      />
      {error ? (
        <p className="mt-2 text-xs text-red-600">{error}</p>
      ) : (
        <p className="mt-2 text-xs text-stone-500">{hint}</p>
      )}
    </div>
  );
}
