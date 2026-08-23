'use client';

import { useEffect } from 'react';
import { isTurnstileConfigured, prefetchTurnstile } from '@/lib/turnstile';

/**
 * Invisible Turnstile warm-up. Mount once on public/auth pages that POST.
 */
export function TurnstileBootstrap() {
  useEffect(() => {
    if (!isTurnstileConfigured()) return;
    void prefetchTurnstile();
  }, []);

  return null;
}
