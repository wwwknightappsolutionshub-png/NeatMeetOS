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
      icon: '/member-icons/icon-192.svg',
      apple: '/member-icons/icon-192.svg',
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
