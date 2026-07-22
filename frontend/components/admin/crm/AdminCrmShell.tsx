'use client';

import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { AdminModuleChrome } from '@/components/admin/AdminModuleChrome';

interface AdminCrmShellProps {
  title: string;
  children: ReactNode;
}

export function AdminCrmShell({ title, children }: AdminCrmShellProps) {
  const pathname = usePathname();

  return (
    <AdminModuleChrome
      eyebrow="CRM"
      title={title}
      links={[
        {
          href: '/admin/clients',
          label: 'Clients',
          active: pathname === '/admin/clients' || pathname.startsWith('/admin/clients/'),
        },
      ]}
    >
      {children}
    </AdminModuleChrome>
  );
}
