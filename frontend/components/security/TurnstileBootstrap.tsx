'use client';

import { TurnstileWidget } from '@/components/security/TurnstileWidget';

/**
 * Inline Turnstile for public forms (booking, join, member login).
 * Not fixed — sits in document flow so it never covers page content.
 */
export function TurnstileFormGate({
  className = '',
  size = 'normal',
  deferExecution = false,
}: {
  className?: string;
  size?: 'normal' | 'compact';
  deferExecution?: boolean;
}) {
  return (
    <TurnstileWidget
      className={className}
      size={size}
      deferExecution={deferExecution}
      hint={
        deferExecution
          ? 'Tap the security check box to verify, then continue.'
          : 'Complete this security check, then continue.'
      }
    />
  );
}
