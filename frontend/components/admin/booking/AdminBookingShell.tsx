'use client';

import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { AdminModuleChrome } from '@/components/admin/AdminModuleChrome';

interface AdminBookingShellProps {
  title: string;
  children: ReactNode;
}

export function AdminBookingShell({ title, children }: AdminBookingShellProps) {
  const pathname = usePathname();

  return (
    <AdminModuleChrome
      eyebrow="Booking"
      title={title}
      links={[
        { href: '/admin/bookings', label: 'Day board', active: pathname === '/admin/bookings' },
        {
          href: '/admin/bookings/walk-ins',
          label: 'Walk-ins',
          active: pathname === '/admin/bookings/walk-ins',
        },
        {
          href: '/admin/bookings/services',
          label: 'Services',
          active: pathname === '/admin/bookings/services',
        },
        {
          href: '/admin/bookings/reviews',
          label: 'Reviews',
          active: pathname === '/admin/bookings/reviews',
        },
        {
          href: '/admin/bookings/waitlist',
          label: 'Waitlist',
          active: pathname === '/admin/bookings/waitlist',
        },
      ]}
    >
      {children}
    </AdminModuleChrome>
  );
}
