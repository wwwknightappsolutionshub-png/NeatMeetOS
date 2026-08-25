'use client';

import type { ReactNode } from 'react';
import { openTenantBookingPage, withMemberBookingAttribution } from '@/lib/tenant-customer-pwa';

export function MemberBookingLink({
  href,
  className,
  children,
}: {
  href: string;
  className?: string;
  children: ReactNode;
}) {
  const destination = withMemberBookingAttribution(href);

  return (
    <button
      type="button"
      className={className}
      onClick={() => openTenantBookingPage(destination)}
    >
      {children}
    </button>
  );
}
