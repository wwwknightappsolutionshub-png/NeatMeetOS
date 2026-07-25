'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect, useState, type ReactNode } from 'react';
import { PlatformNotificationBell } from '@/components/platform/PlatformNotificationBell';
import { NeatMeetLogo } from '@/components/brand/NeatMeetLogo';
import { clearStoredSession, getStoredToken } from '@/lib/api-client';
import type { ShellStatus } from '@/lib/types';
import { fetchShell, logout } from '@/services/auth.service';

interface PlatformAppShellProps {
  children: ReactNode;
}

const links = [
  { href: '/platform', label: 'Overview', match: (p: string) => p === '/platform' },
  {
    href: '/platform/tenants',
    label: 'Tenants',
    match: (p: string) => p.startsWith('/platform/tenants'),
  },
  {
    href: '/platform/modules',
    label: 'Modules',
    match: (p: string) => p.startsWith('/platform/modules'),
  },
  {
    href: '/platform/audit',
    label: 'Audit log',
    match: (p: string) => p.startsWith('/platform/audit'),
  },
  {
    href: '/platform/signup-forms',
    label: 'Signup form',
    match: (p: string) => p.startsWith('/platform/signup-forms'),
  },
  {
    href: '/platform/upgrade-campaigns',
    label: 'Upgrade drip',
    match: (p: string) => p.startsWith('/platform/upgrade-campaigns'),
  },
  {
    href: '/platform/broadcasts',
    label: 'Broadcasts',
    match: (p: string) => p.startsWith('/platform/broadcasts'),
  },
  {
    href: '/platform/pwa-users',
    label: 'PWA users',
    match: (p: string) => p.startsWith('/platform/pwa-users'),
  },
  {
    href: '/platform/referrals',
    label: 'Referrals',
    match: (p: string) => p.startsWith('/platform/referrals'),
  },
  {
    href: '/platform/settings',
    label: 'Account',
    match: (p: string) => p.startsWith('/platform/settings'),
  },
  {
    href: '/platform/staff',
    label: 'Staff',
    match: (p: string) => p.startsWith('/platform/staff'),
    ownerOnly: true,
  },
];

function navClass(active: boolean): string {
  return [
    'block rounded-lg px-2.5 py-1.5 text-sm transition',
    active
      ? 'bg-[var(--platform-accent)] font-semibold text-white'
      : 'text-stone-300 hover:bg-white/10 hover:text-white',
  ].join(' ');
}

export function PlatformAppShell({ children }: PlatformAppShellProps) {
  const pathname = usePathname();
  const router = useRouter();
  const [shell, setShell] = useState<ShellStatus | null>(null);
  const [signingOut, setSigningOut] = useState(false);

  const role = shell?.user?.platform_role ?? null;
  const isOwner = role === 'owner' || (shell?.user?.is_platform_admin && !role);

  useEffect(() => {
    if (!getStoredToken()) {
      router.replace('/login');
      return;
    }
    void fetchShell()
      .then((data) => {
        setShell(data);
        if (!data.user?.is_platform_admin) {
          router.replace('/admin/dashboard');
        }
      })
      .catch(() => {
        router.replace('/login');
      });
  }, [router]);

  async function handleSignOut() {
    setSigningOut(true);
    try {
      await logout();
    } catch {
      clearStoredSession();
    }
    router.replace('/login');
  }

  const visibleLinks = links.filter((link) => !('ownerOnly' in link && link.ownerOnly) || isOwner);

  return (
    <div className="flex min-h-full bg-[linear-gradient(160deg,#1c1917_0%,#292524_42%,#44403c_100%)] text-stone-100">
      <aside className="sticky top-0 flex h-screen w-60 shrink-0 flex-col border-r border-white/10 bg-[var(--platform-sidebar)]">
        <div className="border-b border-white/10 px-4 py-5">
          <div className="flex items-center gap-2.5">
            <NeatMeetLogo size={32} variant="onDark" />
            <div>
              <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/45">
                NeatMeet OS
              </p>
              <p className="text-sm font-semibold text-white">
                {role === 'manager'
                  ? 'Platform manager'
                  : role === 'support'
                    ? 'Platform support'
                    : 'Super admin'}
              </p>
            </div>
          </div>
        </div>
        <nav className="flex-1 overflow-y-auto px-2.5 py-4">
          <p className="mb-1.5 px-2.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-white/40">
            Platform
          </p>
          <ul className="space-y-0.5">
            {visibleLinks.map((link) => (
              <li key={link.href}>
                <Link href={link.href} className={navClass(link.match(pathname))}>
                  {link.label}
                </Link>
              </li>
            ))}
          </ul>
          <p className="mb-1.5 mt-5 px-2.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-white/40">
            Escape
          </p>
          <Link href="/admin/dashboard" className={navClass(false)}>
            Tenant admin
          </Link>
        </nav>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-white/10 bg-stone-950/70 px-4 backdrop-blur sm:px-6">
          <div className="min-w-0">
            <p className="truncate text-sm font-semibold text-white">Platform control</p>
            <p className="truncate text-xs text-stone-300">
              {shell?.user?.email ?? 'Loading…'}
            </p>
          </div>
          <div className="flex items-center gap-2">
            <Link
              href="/platform/settings"
              className="rounded-lg border border-white/15 bg-white/5 px-3 py-1.5 text-sm font-medium text-stone-100 hover:bg-white/10"
            >
              Profile
            </Link>
            <PlatformNotificationBell />
            <button
              type="button"
              disabled={signingOut}
              onClick={() => void handleSignOut()}
              className="rounded-lg border border-white/15 bg-white/5 px-3 py-1.5 text-sm font-medium text-stone-100 hover:bg-white/10 disabled:opacity-50"
            >
              {signingOut ? 'Signing out…' : 'Sign out'}
            </button>
          </div>
        </header>
        <main className="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8">{children}</main>
      </div>
    </div>
  );
}
