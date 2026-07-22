'use client';

import type { ReactNode } from 'react';
import { AdminModuleChrome } from '@/components/admin/AdminModuleChrome';

interface AdminSettingsShellProps {
  title: string;
  children: ReactNode;
}

/** Settings pages use the sidebar for navigation — no duplicate page tabs. */
export function AdminSettingsShell({ title, children }: AdminSettingsShellProps) {
  return (
    <AdminModuleChrome eyebrow="Settings" title={title} links={[]}>
      {children}
    </AdminModuleChrome>
  );
}
