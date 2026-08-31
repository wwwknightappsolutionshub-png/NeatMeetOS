'use client';

import { TurnstileWidget } from '@/components/security/TurnstileWidget';

/**
 * Inline Turnstile for public forms (booking, join, member login).
 * Not fixed — sits in document flow so it never covers page content.
 */
export function TurnstileFormGate({
  className = '',
  size = 'normal',
}: {
  className?: string;
  size?: 'normal' | 'compact';
}) {
  return (
    <TurnstileWidget
      className={className}
      size={size}
      hint="Complete this security check, then continue."
    />
  );
}
