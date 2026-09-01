'use client';

import { useEffect, useState } from 'react';
import { isTurnstileConfigured, subscribeTurnstileReady } from '@/lib/turnstile';

/**
 * True when Turnstile is not configured, or after the visitor ticks the
 * security check box and Turnstile issues a token.
 */
export function useTurnstileReady(): boolean {
  const [ready, setReady] = useState(() => !isTurnstileConfigured());

  useEffect(() => {
    if (!isTurnstileConfigured()) {
      setReady(true);
      return;
    }

    return subscribeTurnstileReady(setReady);
  }, []);

  return ready;
}
