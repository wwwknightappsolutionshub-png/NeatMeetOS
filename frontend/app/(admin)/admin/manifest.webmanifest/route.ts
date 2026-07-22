import { NextResponse } from 'next/server';

export async function GET() {
  const manifest = {
    name: 'NeatMeet OS Admin',
    short_name: 'NeatMeet',
    description: 'Tenant admin workspace for NeatMeet OS',
    start_url: '/admin/dashboard',
    scope: '/admin',
    display: 'standalone',
    orientation: 'any',
    background_color: '#f7f5f1',
    theme_color: '#2f5a45',
    icons: [
      {
        src: '/admin-icons/icon-192.svg',
        sizes: '192x192',
        type: 'image/svg+xml',
        purpose: 'any',
      },
      {
        src: '/admin-icons/icon-512.svg',
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
