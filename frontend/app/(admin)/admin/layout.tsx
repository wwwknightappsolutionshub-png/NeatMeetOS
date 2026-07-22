import type { Metadata } from 'next';
import type { ReactNode } from 'react';
import { AdminAppShell } from '@/components/admin/AdminAppShell';

export const metadata: Metadata = {
  title: 'NeatMeet OS Admin',
  manifest: '/admin/manifest.webmanifest',
  themeColor: '#2f5a45',
  appleWebApp: {
    capable: true,
    title: 'NeatMeet',
    statusBarStyle: 'default',
  },
  icons: {
    icon: '/admin-icons/icon-192.svg',
    apple: '/admin-icons/icon-192.svg',
  },
};

export default function AdminLayout({ children }: { children: ReactNode }) {
  return <AdminAppShell>{children}</AdminAppShell>;
}
