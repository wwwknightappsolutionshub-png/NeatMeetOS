import { NextResponse } from 'next/server';

type Params = { params: Promise<{ tenantSlug: string }> };

export async function GET(request: Request, { params }: Params) {
  const { tenantSlug } = await params;
  const startUrl = `/member/${encodeURIComponent(tenantSlug)}`;
  const origin = new URL(request.url).origin;
  const manifestUrl = `${origin}${startUrl}/manifest.webmanifest`;

  const manifest = {
    name: 'Salon Membership',
    short_name: 'Member',
    description: 'Check in, loyalty points, memberships and bookings',
    start_url: startUrl,
    scope: '/',
    display: 'standalone',
    related_applications: [
      {
        platform: 'webapp',
        url: manifestUrl,
      },
    ],
    orientation: 'portrait-primary',
    background_color: '#f5f1e8',
    theme_color: '#2f5a45',
    icons: [
      {
        src: '/member-icons/icon-192.png',
        sizes: '192x192',
        type: 'image/png',
        purpose: 'any',
      },
      {
        src: '/member-icons/icon-512.png',
        sizes: '512x512',
        type: 'image/png',
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
