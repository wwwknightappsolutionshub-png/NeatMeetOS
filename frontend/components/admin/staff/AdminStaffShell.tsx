'use client';

import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { AdminModuleChrome } from '@/components/admin/AdminModuleChrome';

interface AdminStaffShellProps {
  title: string;
  children: ReactNode;
}

export function AdminStaffShell({ title, children }: AdminStaffShellProps) {
  const pathname = usePathname();

  return (
    <AdminModuleChrome
      eyebrow="Staff"
      title={title}
      links={[
        {
          href: '/admin/staff',
          label: 'Providers',
          active: pathname === '/admin/staff' || pathname.startsWith('/admin/staff/'),
        },
      ]}
    >
      {children}
    </AdminModuleChrome>
  );
}
