'use client';

import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { AdminModuleChrome } from '@/components/admin/AdminModuleChrome';

interface AdminMembershipsShellProps {
  title: string;
  children: ReactNode;
}

/**
 * Simplified IA for busy salonists:
 * Overview → Offers (create) → Client benefits (allocate/deduct/renew) → Settings
 * Old deep links (plans, packages, subscriptions, etc.) still work; nav highlights the parent step.
 */
const links = [
  {
    href: '/admin/memberships',
    label: 'Overview',
    match: (p: string) => p === '/admin/memberships',
  },
  {
    href: '/admin/memberships/offers',
    label: '1. Offers',
    match: (p: string) =>
      p.startsWith('/admin/memberships/offers') ||
      p.startsWith('/admin/memberships/plans') ||
      p.startsWith('/admin/memberships/packages'),
  },
  {
    href: '/admin/memberships/clients',
    label: '2. Client benefits',
    match: (p: string) =>
      p.startsWith('/admin/memberships/clients') ||
      p.startsWith('/admin/memberships/subscriptions') ||
      p.startsWith('/admin/memberships/client-packages') ||
      p.startsWith('/admin/memberships/wallet') ||
      p === '/admin/memberships/loyalty',
  },
  {
    href: '/admin/memberships/loyalty-settings',
    label: 'Settings',
    match: (p: string) => p.startsWith('/admin/memberships/loyalty-settings'),
  },
];

export function AdminMembershipsShell({ title, children }: AdminMembershipsShellProps) {
  const pathname = usePathname();

  return (
    <AdminModuleChrome
      eyebrow="Client rewards"
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
