'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { AdminModuleChrome } from '@/components/admin/AdminModuleChrome';

interface AdminMarketingShellProps {
  title: string;
  children: ReactNode;
}

/** Busy-tenant essentials first; advanced tools stay available but secondary. */
const essentialLinks = [
  { href: '/admin/marketing', label: 'Overview', match: (p: string) => p === '/admin/marketing' },
  {
    href: '/admin/marketing/templates',
    label: 'Templates',
    match: (p: string) => p.startsWith('/admin/marketing/templates'),
  },
  {
    href: '/admin/marketing/messages',
    label: 'Messages',
    match: (p: string) => p.startsWith('/admin/marketing/messages'),
  },
  {
    href: '/admin/marketing/settings',
    label: 'Settings',
    match: (p: string) => p.startsWith('/admin/marketing/settings'),
  },
];

const advancedLinks = [
  {
    href: '/admin/marketing/audiences',
    label: 'Audiences',
    match: (p: string) => p.startsWith('/admin/marketing/audiences'),
  },
  {
    href: '/admin/marketing/campaigns',
    label: 'Campaigns',
    match: (p: string) => p.startsWith('/admin/marketing/campaigns'),
  },
  {
    href: '/admin/marketing/runs',
    label: 'Runs',
    match: (p: string) => p.startsWith('/admin/marketing/runs'),
  },
  {
    href: '/admin/marketing/workflows',
    label: 'Workflows',
    match: (p: string) => p.startsWith('/admin/marketing/workflows'),
  },
  {
    href: '/admin/marketing/executions',
    label: 'Executions',
    match: (p: string) => p.startsWith('/admin/marketing/executions'),
  },
  {
    href: '/admin/marketing/suppressions',
    label: 'Suppressions',
    match: (p: string) => p.startsWith('/admin/marketing/suppressions'),
  },
];

export function AdminMarketingShell({ title, children }: AdminMarketingShellProps) {
  const pathname = usePathname();
  const showAdvanced = advancedLinks.some((link) => link.match(pathname));

  return (
    <AdminModuleChrome
      eyebrow="Marketing"
      title={title}
      links={essentialLinks.map((link) => ({
        href: link.href,
        label: link.label,
        active: link.match(pathname),
      }))}
    >
      <details className="mb-6 rounded-lg border border-zinc-200 bg-zinc-50/80 px-4 py-3" open={showAdvanced}>
        <summary className="cursor-pointer text-sm font-medium text-zinc-800">
          Advanced tools (audiences, campaigns, workflows…)
        </summary>
        <p className="mt-2 text-xs text-zinc-500">
          Most salons only need Templates + Settings. Automations already run on a schedule —
          open these when you need custom segments or journeys.
        </p>
        <nav className="mt-3 flex flex-wrap gap-3 text-sm">
          {advancedLinks.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              className={
                link.match(pathname)
                  ? 'font-medium text-zinc-900 underline'
                  : 'text-zinc-600 hover:underline'
              }
            >
              {link.label}
            </Link>
          ))}
        </nav>
      </details>
      {children}
    </AdminModuleChrome>
  );
}
