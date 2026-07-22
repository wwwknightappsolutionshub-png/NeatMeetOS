'use client';

import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { AdminModuleChrome } from '@/components/admin/AdminModuleChrome';

interface AdminNotificationsShellProps {
  title: string;
  children: ReactNode;
}

const links = [
  {
    href: '/admin/notifications',
    label: 'Overview',
    match: (p: string) => p === '/admin/notifications',
  },
  {
    href: '/admin/notifications/messages',
    label: 'Messages',
    match: (p: string) => p.startsWith('/admin/notifications/messages'),
  },
  {
    href: '/admin/notifications/templates',
    label: 'Templates',
    match: (p: string) => p.startsWith('/admin/notifications/templates'),
  },
  {
    href: '/admin/notifications/preferences',
    label: 'Preferences',
    match: (p: string) => p.startsWith('/admin/notifications/preferences'),
  },
  {
    href: '/admin/notifications/settings',
    label: 'Settings',
    match: (p: string) => p.startsWith('/admin/notifications/settings'),
  },
];

export function AdminNotificationsShell({ title, children }: AdminNotificationsShellProps) {
  const pathname = usePathname();

  return (
    <AdminModuleChrome
      eyebrow="Notifications"
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
