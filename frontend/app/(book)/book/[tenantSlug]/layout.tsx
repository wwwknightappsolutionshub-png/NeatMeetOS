import type { Metadata, Viewport } from 'next';
import type { ReactNode } from 'react';
import { tenantCustomerManifestPath } from '@/lib/tenant-customer-pwa';

type Props = {
  children: ReactNode;
  params: Promise<{ tenantSlug: string }>;
};

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { tenantSlug } = await params;
  const manifestPath = tenantCustomerManifestPath(tenantSlug);

  return {
    applicationName: 'Salon app',
    appleWebApp: {
      capable: true,
      title: 'Salon app',
      statusBarStyle: 'default',
    },
    icons: {
      icon: [
        { url: '/member-icons/icon-192.png', sizes: '192x192', type: 'image/png' },
        { url: '/member-icons/icon-512.png', sizes: '512x512', type: 'image/png' },
      ],
      apple: '/member-icons/icon-192.png',
    },
    manifest: manifestPath,
  };
}

export const viewport: Viewport = {
  themeColor: '#2f5a45',
  width: 'device-width',
  initialScale: 1,
  maximumScale: 1,
};

export default function TenantBookLayout({ children }: Props) {
  return children;
}
