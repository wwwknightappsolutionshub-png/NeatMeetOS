import { NextResponse } from 'next/server';

type Params = { params: Promise<{ tenantSlug: string }> };

export async function GET(_request: Request, { params }: Params) {
  const { tenantSlug } = await params;
  const startUrl = `/member/${encodeURIComponent(tenantSlug)}`;

  const manifest = {
    name: 'Salon Membership',
    short_name: 'Member',
    description: 'Check in, loyalty points, memberships and bookings',
    start_url: startUrl,
    scope: '/',
    display: 'standalone',
    orientation: 'portrait-primary',
    background_color: '#f5f1e8',
    theme_color: '#2f5a45',
    icons: [
      {
        src: '/member-icons/icon-192.svg',
        sizes: '192x192',
        type: 'image/svg+xml',
        purpose: 'any',
      },
      {
        src: '/member-icons/icon-512.svg',
        sizes: '512x512',
        type: 'image/svg+xml',
        purpose: 'any maskable',
      },
    ],
  };

  return NextResponse.json(manifest, {
    headers: {
      'Content-Type': 'application/manifest+json',
      'Cache-Control': 'public, max-age=3600',
    },
  });
}
