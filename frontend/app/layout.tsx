import type { Metadata } from 'next';
import { Anek_Latin, Geist_Mono } from 'next/font/google';
import './globals.css';

const anek = Anek_Latin({
  subsets: ['latin'],
  weight: ['400', '500', '600', '700'],
  variable: '--font-anek',
  display: 'swap',
});

const geistMono = Geist_Mono({
  variable: '--font-geist-mono',
  subsets: ['latin'],
});

const SITE_URL = (
  process.env.NEXT_PUBLIC_SITE_URL ||
  'https://neatmeet.prohost.cloud'
).replace(/\/$/, '');

export const metadata: Metadata = {
  metadataBase: new URL(SITE_URL),
  title: 'NeatMeet OS',
  description: 'Salon operating system — multi-tenant SaaS platform',
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html
      lang="en"
      className={`${anek.variable} ${geistMono.variable} h-full antialiased`}
    >
      <body className={`${anek.className} flex min-h-full flex-col`}>{children}</body>
    </html>
  );
}
