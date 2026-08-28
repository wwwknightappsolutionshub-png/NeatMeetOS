'use client';

import { TurnstileWidget } from '@/components/security/TurnstileWidget';

/**
 * Shared visible Turnstile for public booking pages (compact, bottom-right).
 */
export function TurnstileBootstrap() {
  return (
    <TurnstileWidget
      className="fixed bottom-3 right-3 z-50 max-w-[320px] rounded-lg border border-stone-200 bg-white/95 p-2 shadow-lg backdrop-blur sm:bottom-4 sm:right-4"
      size="compact"
      hint="Complete this check before booking."
    />
  );
}
