'use client';

import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { AdminModuleChrome } from '@/components/admin/AdminModuleChrome';

interface AdminPaymentsShellProps {
  title: string;
  children: ReactNode;
}

export function AdminPaymentsShell({ title, children }: AdminPaymentsShellProps) {
  const pathname = usePathname();

  return (
    <AdminModuleChrome
      eyebrow="Payments"
      title={title}
      links={[
        {
          href: '/admin/payments',
          label: 'Transactions',
          active:
            pathname === '/admin/payments' ||
            (pathname.startsWith('/admin/payments/') &&
              !pathname.startsWith('/admin/payments/failed') &&
              !pathname.startsWith('/admin/payments/documents') &&
              !pathname.startsWith('/admin/payments/settings')),
        },
        {
          href: '/admin/payments/documents',
          label: 'Payment documents',
          active: pathname.startsWith('/admin/payments/documents'),
        },
        {
          href: '/admin/payments/settings',
          label: 'Bank details',
          active: pathname.startsWith('/admin/payments/settings'),
        },
        {
          href: '/admin/payments/failed',
          label: 'Failed',
          active: pathname === '/admin/payments/failed',
        },
      ]}
    >
      {children}
    </AdminModuleChrome>
  );
}
