'use client';

import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { AdminModuleChrome } from '@/components/admin/AdminModuleChrome';

interface AdminInventoryShellProps {
  title: string;
  children: ReactNode;
}

export function AdminInventoryShell({ title, children }: AdminInventoryShellProps) {
  const pathname = usePathname();

  return (
    <AdminModuleChrome
      eyebrow="Inventory"
      title={title}
      links={[
        { href: '/admin/inventory', label: 'Items', active: pathname === '/admin/inventory' },
        {
          href: '/admin/inventory/suppliers',
          label: 'Suppliers',
          active: pathname.startsWith('/admin/inventory/suppliers'),
        },
        {
          href: '/admin/inventory/low-stock',
          label: 'Low stock',
          active: pathname === '/admin/inventory/low-stock',
        },
        {
          href: '/admin/inventory/movements',
          label: 'Movements',
          active: pathname === '/admin/inventory/movements',
        },
      ]}
    >
      {children}
    </AdminModuleChrome>
  );
}
