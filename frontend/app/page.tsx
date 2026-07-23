import type { Metadata } from 'next';
import { Suspense } from 'react';
import { MarketingLanding } from '@/components/marketing/MarketingLanding';

/** Public site origin for absolute OG/Twitter URLs (WhatsApp link previews). */
const SITE_URL = (
  process.env.NEXT_PUBLIC_SITE_URL ||
  'https://neatmeet.prohost.cloud'
).replace(/\/$/, '');

const OG_TITLE = 'NeatMeet OS — Salon operating system';
const OG_DESCRIPTION =
  'One OS for bookings, clients, till, memberships, and follow-up. Start a 30-day free trial.';
/** JPEG under ~100KB — WhatsApp often drops large PNG previews. */
const OG_IMAGE_PATH = '/brand/og-landing.jpg';
const OG_IMAGE_URL = `${SITE_URL}${OG_IMAGE_PATH}`;

export const metadata: Metadata = {
  metadataBase: new URL(SITE_URL),
  title: OG_TITLE,
  description: OG_DESCRIPTION,
  openGraph: {
    type: 'website',
    locale: 'en_GB',
    url: SITE_URL,
    siteName: 'NeatMeet OS',
    title: OG_TITLE,
    description: OG_DESCRIPTION,
    images: [
      {
        url: OG_IMAGE_URL,
        secureUrl: OG_IMAGE_URL,
        width: 1200,
        height: 630,
        alt: 'NeatMeet OS — salon operating system. Start a 30-day free trial.',
        type: 'image/jpeg',
      },
    ],
  },
  twitter: {
    card: 'summary_large_image',
    title: OG_TITLE,
    description: OG_DESCRIPTION,
    images: [OG_IMAGE_URL],
  },
};

export default function HomePage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-screen items-center justify-center bg-[#f3f1ec] text-sm text-stone-500">
          Loading NeatMeet OS…
        </div>
      }
    >
      <MarketingLanding />
    </Suspense>
  );
}
