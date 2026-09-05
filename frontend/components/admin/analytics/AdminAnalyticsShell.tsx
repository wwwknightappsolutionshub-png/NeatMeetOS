'use client';

import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { AdminModuleChrome } from '@/components/admin/AdminModuleChrome';

interface AdminAnalyticsShellProps {
  title: string;
  children: ReactNode;
}

const links = [
  { href: '/admin/analytics', label: 'Overview', match: (p: string) => p === '/admin/analytics' },
  {
    href: '/admin/analytics/intelligence',
    label: 'Intelligence',
    match: (p: string) => p.startsWith('/admin/analytics/intelligence'),
  },
  {
    href: '/admin/analytics/bookings',
    label: 'Bookings',
    match: (p: string) => p.startsWith('/admin/analytics/bookings'),
  },
  {
    href: '/admin/analytics/revenue',
    label: 'Revenue',
    match: (p: string) => p.startsWith('/admin/analytics/revenue'),
  },
  {
    href: '/admin/analytics/clients',
    label: 'Clients',
    match: (p: string) => p.startsWith('/admin/analytics/clients'),
  },
  {
    href: '/admin/analytics/inventory',
    label: 'Inventory',
    match: (p: string) => p.startsWith('/admin/analytics/inventory'),
  },
  {
    href: '/admin/analytics/communications',
    label: 'Communications',
    match: (p: string) => p.startsWith('/admin/analytics/communications'),
  },
  {
    href: '/admin/analytics/reports',
    label: 'Reports',
    match: (p: string) => p.startsWith('/admin/analytics/reports'),
  },
  {
    href: '/admin/analytics/exports',
    label: 'Exports',
    match: (p: string) => p.startsWith('/admin/analytics/exports'),
  },
];

export function AdminAnalyticsShell({ title, children }: AdminAnalyticsShellProps) {
  const pathname = usePathname();

  return (
    <AdminModuleChrome
      eyebrow="Analytics"
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
