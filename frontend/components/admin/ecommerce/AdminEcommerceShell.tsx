'use client';

import type { ReactNode } from 'react';
import { usePathname } from 'next/navigation';
import { AdminModuleChrome } from '@/components/admin/AdminModuleChrome';

const links = [
  { href: '/admin/ecommerce', label: 'Products' },
  { href: '/admin/ecommerce/orders', label: 'Orders' },
];

export function AdminEcommerceShell({
  title,
  children,
}: {
  title: string;
  children: ReactNode;
}) {
  const pathname = usePathname();

  return (
    <AdminModuleChrome
      eyebrow="Shop"
      title={title}
      links={links.map((link) => ({
        href: link.href,
        label: link.label,
        active:
          link.href === '/admin/ecommerce'
            ? pathname === '/admin/ecommerce'
            : pathname.startsWith(link.href),
      }))}
    >
      {children}
    </AdminModuleChrome>
  );
}
