'use client';

import { useEffect, useRef, useState } from 'react';
import {
  isTurnstileConfigured,
  mountTurnstileIn,
  TURNSTILE_FORM_HINT,
  type TurnstileWidgetSize,
} from '@/lib/turnstile';

type Props = {
  className?: string;
  size?: TurnstileWidgetSize;
  hint?: string;
};

/**
 * Visible Cloudflare Turnstile checkbox. Mount once per page/section that POSTs.
 * Submit buttons should stay disabled until useTurnstileReady() is true.
 */
export function TurnstileWidget({
  className = '',
  size = 'normal',
  hint = TURNSTILE_FORM_HINT,
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
        className="flex min-h-[65px] max-w-[200px] items-center justify-start"
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
