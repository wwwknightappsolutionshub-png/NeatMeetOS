'use client';

import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { AdminModuleChrome } from '@/components/admin/AdminModuleChrome';

interface AdminPosShellProps {
  title: string;
  children: ReactNode;
}

export function AdminPosShell({ title, children }: AdminPosShellProps) {
  const pathname = usePathname();

  return (
    <AdminModuleChrome
      eyebrow="POS"
      title={title}
      links={[
        {
          href: '/admin/pos',
          label: 'Checkouts',
          active: pathname === '/admin/pos' || pathname.startsWith('/admin/pos/'),
        },
      ]}
    >
      {children}
    </AdminModuleChrome>
  );
}
