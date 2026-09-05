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
    icon: [
      { url: '/admin-icons/icon-192.png', sizes: '192x192', type: 'image/png' },
      { url: '/admin-icons/icon-512.png', sizes: '512x512', type: 'image/png' },
    ],
    apple: '/admin-icons/icon-192.png',
  },
};

export default function AdminLayout({ children }: { children: ReactNode }) {
  return <AdminAppShell>{children}</AdminAppShell>;
}
