'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { PlatformNotificationBell } from '@/components/platform/PlatformNotificationBell';
import { NeatMeetLogo } from '@/components/brand/NeatMeetLogo';
import { clearStoredSession, getStoredToken } from '@/lib/api-client';
import type { ShellStatus } from '@/lib/types';
import { fetchShell, logout } from '@/services/auth.service';

interface PlatformAppShellProps {
  children: ReactNode;
}

type NavLink = {
  href: string;
  label: string;
  code: string;
  match: (p: string) => boolean;
  ownerOnly?: boolean;
};

const navGroups: Array<{ title: string; links: NavLink[] }> = [
  {
    title: 'Command',
    links: [
      { href: '/platform', label: 'Overview', code: 'OV', match: (p) => p === '/platform' },
      {
        href: '/platform/tenants',
        label: 'Tenants',
        code: 'TN',
        match: (p) => p.startsWith('/platform/tenants'),
      },
      {
        href: '/platform/modules',
        label: 'Modules',
        code: 'MD',
        match: (p) => p.startsWith('/platform/modules'),
      },
      {
        href: '/platform/audit',
        label: 'Audit log',
        code: 'AU',
        match: (p) => p.startsWith('/platform/audit'),
      },
    ],
  },
  {
    title: 'Outreach',
    links: [
      {
        href: '/platform/signup-forms',
        label: 'Signup form',
        code: 'SF',
        match: (p) => p.startsWith('/platform/signup-forms'),
      },
      {
        href: '/platform/upgrade-campaigns',
        label: 'Upgrade drip',
        code: 'UD',
        match: (p) => p.startsWith('/platform/upgrade-campaigns'),
      },
      {
        href: '/platform/broadcasts',
        label: 'Broadcasts',
        code: 'BC',
        match: (p) => p.startsWith('/platform/broadcasts'),
      },
      {
        href: '/platform/pwa-users',
        label: 'PWA users',
        code: 'PW',
        match: (p) => p.startsWith('/platform/pwa-users'),
      },
      {
        href: '/platform/referrals',
        label: 'Referrals',
        code: 'RF',
        match: (p) => p.startsWith('/platform/referrals'),
      },
    ],
  },
  {
    title: 'System',
    links: [
      {
        href: '/platform/settings',
        label: 'Account',
        code: 'AC',
        match: (p) => p.startsWith('/platform/settings'),
      },
      {
        href: '/platform/staff',
        label: 'Staff',
        code: 'ST',
        match: (p) => p.startsWith('/platform/staff'),
        ownerOnly: true,
      },
    ],
  },
];

function roleLabel(role: string | null, isPlatformAdmin: boolean | undefined): string {
  if (role === 'manager') return 'Platform manager';
  if (role === 'support') return 'Platform support';
  if (role === 'owner' || isPlatformAdmin) return 'Super admin';
  return 'Operator';
}

function navItemClass(active: boolean): string {
  return [
    'group flex items-center gap-2.5 rounded-md border px-2.5 py-2 text-sm transition',
    active
      ? 'border-[var(--platform-accent)]/40 bg-[var(--platform-accent-soft)] font-semibold text-white shadow-[0_0_16px_-8px_var(--platform-glow)]'
      : 'border-transparent text-[var(--platform-label)] hover:border-[var(--platform-line-subtle)] hover:bg-white/[0.03] hover:text-white',
  ].join(' ');
}

export function PlatformAppShell({ children }: PlatformAppShellProps) {
  const pathname = usePathname();
  const router = useRouter();
  const [shell, setShell] = useState<ShellStatus | null>(null);
  const [signingOut, setSigningOut] = useState(false);
  const [clock, setClock] = useState('');

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

  useEffect(() => {
    const tick = () => {
      setClock(
        new Date().toLocaleTimeString('en-GB', {
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit',
        }),
      );
    };
    tick();
    const id = window.setInterval(tick, 1000);
    return () => window.clearInterval(id);
  }, []);

  const visibleGroups = useMemo(
    () =>
      navGroups
        .map((group) => ({
          ...group,
          links: group.links.filter((link) => !link.ownerOnly || isOwner),
        }))
        .filter((group) => group.links.length > 0),
    [isOwner],
  );

  const activeLink = navGroups
    .flatMap((g) => g.links)
    .find((link) => link.match(pathname));

  async function handleSignOut() {
    setSigningOut(true);
    try {
      await logout();
    } catch {
      clearStoredSession();
    }
    router.replace('/login');
  }

  return (
    <div className="flex min-h-full bg-[var(--platform-ink)] text-[var(--platform-fg)]">
      <aside className="sticky top-0 flex h-screen w-64 shrink-0 flex-col border-r border-[var(--platform-line-subtle)] bg-[var(--platform-sidebar)]">
        <div className="border-b border-[var(--platform-line-subtle)] px-4 py-4">
          <div className="flex items-center gap-3">
            <div className="flex h-9 w-9 items-center justify-center rounded-md border border-[var(--platform-line)] bg-[var(--platform-surface)] shadow-[0_0_20px_-10px_var(--platform-glow)]">
              <NeatMeetLogo size={22} variant="onDark" />
            </div>
            <div className="min-w-0">
              <p className="font-mono text-[10px] font-semibold uppercase tracking-[0.22em] text-[var(--platform-accent)]">
                NeatMeet OS
              </p>
              <p className="truncate text-sm font-semibold text-white">Ops console</p>
            </div>
          </div>
          <div className="mt-3 flex items-center gap-2 rounded-md border border-[var(--platform-line-subtle)] bg-[var(--platform-surface)] px-2.5 py-2">
            <span className="h-2 w-2 animate-pulse rounded-full bg-[var(--platform-success)] shadow-[0_0_8px_var(--platform-success)]" />
            <span className="font-mono text-[10px] uppercase tracking-[0.14em] text-[var(--platform-label)]">
              {roleLabel(role, shell?.user?.is_platform_admin)}
            </span>
          </div>
        </div>

        <nav className="flex-1 overflow-y-auto px-2.5 py-4">
          {visibleGroups.map((group) => (
            <div key={group.title} className="mb-5 last:mb-0">
              <p className="mb-2 px-2 font-mono text-[10px] font-semibold uppercase tracking-[0.2em] text-[var(--platform-muted)]">
                {group.title}
              </p>
              <ul className="space-y-1">
                {group.links.map((link) => {
                  const active = link.match(pathname);
                  return (
                    <li key={link.href}>
                      <Link href={link.href} className={navItemClass(active)}>
                        <span
                          className={[
                            'flex h-6 w-6 shrink-0 items-center justify-center rounded border font-mono text-[9px] font-bold tracking-wide',
                            active
                              ? 'border-[var(--platform-accent)]/50 bg-[var(--platform-accent)]/15 text-[var(--platform-accent)]'
                              : 'border-[var(--platform-line-subtle)] bg-[#06080b] text-[var(--platform-muted)] group-hover:text-[var(--platform-accent)]',
                          ].join(' ')}
                        >
                          {link.code}
                        </span>
                        <span className="truncate">{link.label}</span>
                      </Link>
                    </li>
                  );
                })}
              </ul>
            </div>
          ))}

          <div className="mt-6 border-t border-[var(--platform-line-subtle)] pt-4">
            <p className="mb-2 px-2 font-mono text-[10px] font-semibold uppercase tracking-[0.2em] text-[var(--platform-muted)]">
              Exit
            </p>
            <Link href="/admin/dashboard" className={navItemClass(false)}>
              <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded border border-[var(--platform-line-subtle)] bg-[#06080b] font-mono text-[9px] font-bold text-[var(--platform-muted)]">
                AD
              </span>
              <span>Tenant admin</span>
            </Link>
          </div>
        </nav>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-[var(--platform-line-subtle)] bg-[#06080b]/90 px-4 backdrop-blur-md sm:px-6">
          <div className="min-w-0">
            <p className="truncate font-mono text-[10px] uppercase tracking-[0.18em] text-[var(--platform-muted)]">
              {activeLink ? `${activeLink.code} · ${activeLink.label}` : 'Platform'}
            </p>
            <p className="truncate text-sm font-medium text-white">
              {shell?.user?.email ?? 'Authenticating…'}
            </p>
          </div>
          <div className="flex items-center gap-2 sm:gap-3">
            <span className="hidden font-mono text-xs tabular-nums text-[var(--platform-accent)] sm:inline">
              {clock}
            </span>
            <Link
              href="/platform/settings"
              className="rounded-md border border-[var(--platform-line-subtle)] bg-[var(--platform-surface)] px-3 py-1.5 text-xs font-semibold text-[var(--platform-label)] hover:border-[var(--platform-line)] hover:text-white"
            >
              Profile
            </Link>
            <PlatformNotificationBell />
            <button
              type="button"
              disabled={signingOut}
              onClick={() => void handleSignOut()}
              className="rounded-md border border-[var(--platform-line-subtle)] bg-[var(--platform-surface)] px-3 py-1.5 text-xs font-semibold text-[var(--platform-label)] hover:border-[var(--platform-danger)]/40 hover:text-[#ffb4af] disabled:opacity-50"
            >
              {signingOut ? '…' : 'Sign out'}
            </button>
          </div>
        </header>
        <main className="platform-ops-grid relative min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8">
          <div className="relative">{children}</div>
        </main>
      </div>
    </div>
  );
}
