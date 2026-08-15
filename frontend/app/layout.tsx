import type { Metadata } from 'next';
import localFont from 'next/font/local';
import './globals.css';

const anek = localFont({
  src: './fonts/AnekLatin-Variable.ttf',
  variable: '--font-anek',
  display: 'swap',
  weight: '100 800',
});

const geistMono = localFont({
  src: './fonts/GeistMono-Regular.woff2',
  variable: '--font-geist-mono',
  display: 'swap',
  weight: '400',
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
