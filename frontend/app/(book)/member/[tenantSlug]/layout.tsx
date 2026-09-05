import type { Metadata, Viewport } from 'next';
import type { ReactNode } from 'react';

type Props = {
  children: ReactNode;
  params: Promise<{ tenantSlug: string }>;
};

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { tenantSlug } = await params;
  const manifestPath = `/member/${tenantSlug}/manifest.webmanifest`;

  return {
    title: 'Membership app',
    description: 'Check in, loyalty, memberships and bookings',
    applicationName: 'Membership app',
    appleWebApp: {
      capable: true,
      title: 'Membership',
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

export default function MemberLayout({ children }: Props) {
  return children;
}
