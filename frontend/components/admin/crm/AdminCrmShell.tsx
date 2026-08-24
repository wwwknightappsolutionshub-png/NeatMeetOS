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
          active: pathname === '/admin/clients' || (pathname.startsWith('/admin/clients/') && !pathname.startsWith('/admin/clients/import') && !pathname.startsWith('/admin/clients/on-site')),
        },
        {
          href: '/admin/messages',
          label: 'Messages',
          active: pathname.startsWith('/admin/messages'),
        },
        {
          href: '/admin/clients/on-site',
          label: "Who's in",
          active: pathname.startsWith('/admin/clients/on-site'),
        },
        {
          href: '/admin/clients/import',
          label: 'Import CSV',
          active: pathname.startsWith('/admin/clients/import'),
        },
      ]}
    >
      {children}
    </AdminModuleChrome>
  );
}
