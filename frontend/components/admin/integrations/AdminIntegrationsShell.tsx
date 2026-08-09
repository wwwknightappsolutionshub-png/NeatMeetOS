'use client';

import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { AdminModuleChrome } from '@/components/admin/AdminModuleChrome';

interface AdminIntegrationsShellProps {
  title: string;
  children: ReactNode;
}

const links = [
  {
    href: '/admin/integrations',
    label: 'Overview',
    match: (p: string) => p === '/admin/integrations',
  },
  {
    href: '/admin/integrations/whatsapp',
    label: 'Salon WhatsApp',
    match: (p: string) => p.startsWith('/admin/integrations/whatsapp'),
  },
  {
    href: '/admin/integrations/provider-accounts',
    label: 'Provider Accounts',
    match: (p: string) => p.startsWith('/admin/integrations/provider-accounts'),
  },
  {
    href: '/admin/integrations/provider-attempts',
    label: 'Delivery Attempts',
    match: (p: string) => p.startsWith('/admin/integrations/provider-attempts'),
  },
  {
    href: '/admin/integrations/provider-events',
    label: 'Webhook Events',
    match: (p: string) => p.startsWith('/admin/integrations/provider-events'),
  },
];

export function AdminIntegrationsShell({ title, children }: AdminIntegrationsShellProps) {
  const pathname = usePathname();

  return (
    <AdminModuleChrome
      eyebrow="Integrations"
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
