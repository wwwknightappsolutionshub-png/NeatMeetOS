'use client';

import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { AdminModuleChrome } from '@/components/admin/AdminModuleChrome';

interface AdminMembershipsShellProps {
  title: string;
  children: ReactNode;
}

const links = [
  { href: '/admin/memberships', label: 'Summary', match: (p: string) => p === '/admin/memberships' },
  {
    href: '/admin/memberships/plans',
    label: 'Plans',
    match: (p: string) => p.startsWith('/admin/memberships/plans'),
  },
  {
    href: '/admin/memberships/packages',
    label: 'Packages',
    match: (p: string) => p.startsWith('/admin/memberships/packages'),
  },
  {
    href: '/admin/memberships/subscriptions',
    label: 'Subscriptions',
    match: (p: string) => p.startsWith('/admin/memberships/subscriptions'),
  },
  {
    href: '/admin/memberships/wallet',
    label: 'Wallet',
    match: (p: string) => p.startsWith('/admin/memberships/wallet'),
  },
  {
    href: '/admin/memberships/loyalty',
    label: 'Loyalty',
    match: (p: string) => p === '/admin/memberships/loyalty',
  },
  {
    href: '/admin/memberships/loyalty-settings',
    label: 'Loyalty redemption',
    match: (p: string) => p.startsWith('/admin/memberships/loyalty-settings'),
  },
  {
    href: '/admin/memberships/client-packages',
    label: 'Client packages',
    match: (p: string) => p.startsWith('/admin/memberships/client-packages'),
  },
];

export function AdminMembershipsShell({ title, children }: AdminMembershipsShellProps) {
  const pathname = usePathname();

  return (
    <AdminModuleChrome
      eyebrow="Memberships"
      title={title}
      links={links.map((link) => ({
        href: link.href,
        label: link.label,
        active: link.match(pathname),
      }))}
    >
      {children}
    </AdminModuleChrome>
  );
}
