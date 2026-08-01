'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { AdminTopBar } from '@/components/admin/AdminTopBar';
import { AdminPwaPrompt } from '@/components/admin/AdminPwaPrompt';
import { AdminReferralNudge } from '@/components/admin/AdminReferralNudge';
import { ModuleUpgradeGate } from '@/components/admin/ModuleUpgradeGate';
import { NeatMeetLogo } from '@/components/brand/NeatMeetLogo';
import { api, getStoredTenantSlug, getStoredToken } from '@/lib/api-client';
import type { ModuleUpgradePayload, ShellStatus } from '@/lib/types';
import { fetchShell } from '@/services/auth.service';

interface AdminAppShellProps {
  children: ReactNode;
}

const operationLinks: {
  href: string;
  label: string;
  match: (p: string) => boolean;
  feature?: string;
  /** When set, nav item is hidden unless the shell permissions include this slug. */
  permission?: string;
}[] = [
  { href: '/admin/dashboard', label: 'Dashboard', match: (p) => p === '/admin/dashboard' },
  {
    href: '/admin/clients',
    label: 'Clients',
    match: (p) => p.startsWith('/admin/clients'),
    feature: 'crm',
  },
  { href: '/admin/staff', label: 'Staff', match: (p) => p.startsWith('/admin/staff') },
  {
    href: '/admin/bookings/services',
    label: 'Services',
    match: (p) =>
      p.startsWith('/admin/bookings/services') || p.startsWith('/admin/bookings/reviews'),
    feature: 'booking',
  },
  {
    href: '/admin/bookings',
    label: 'Bookings',
    match: (p) =>
      p === '/admin/bookings' ||
      p.startsWith('/admin/bookings/walk-ins') ||
      p.startsWith('/admin/bookings/waitlist'),
    feature: 'booking_board',
  },
  {
    href: '/admin/payments',
    label: 'Payments',
    match: (p) => p.startsWith('/admin/payments'),
    feature: 'payments',
  },
  {
    href: '/admin/inventory',
    label: 'Inventory',
    match: (p) => p.startsWith('/admin/inventory'),
    feature: 'inventory',
  },
  {
    href: '/admin/pos',
    label: 'POS',
    match: (p) => p.startsWith('/admin/pos'),
    feature: 'pos',
  },
  {
    href: '/admin/ecommerce',
    label: 'Shop',
    match: (p) => p.startsWith('/admin/ecommerce'),
    feature: 'ecommerce',
  },
  {
    href: '/admin/gallery',
    label: 'Gallery',
    match: (p) => p.startsWith('/admin/gallery'),
    feature: 'gallery',
  },
  {
    href: '/admin/lookbook',
    label: 'Lookbook',
    match: (p) => p.startsWith('/admin/lookbook'),
    feature: 'lookbook',
  },
  {
    href: '/admin/ai-hairstyle',
    label: 'Approved Looks',
    match: (p) => p.startsWith('/admin/ai-hairstyle'),
    feature: 'ai_hairstyle',
    permission: 'ai_hairstyle.view',
  },
  {
    href: '/admin/next-visit',
    label: 'Next visit',
    match: (p) => p.startsWith('/admin/next-visit'),
    feature: 'next_visit',
  },
  {
    href: '/admin/memberships',
    label: 'Memberships',
    match: (p) => p.startsWith('/admin/memberships'),
    feature: 'memberships',
  },
  {
    href: '/admin/marketing',
    label: 'Marketing',
    match: (p) => p.startsWith('/admin/marketing'),
    feature: 'marketing',
  },
  {
    href: '/admin/notifications',
    label: 'Notifications',
    match: (p) => p.startsWith('/admin/notifications'),
    feature: 'notifications',
  },
  {
    href: '/admin/analytics',
    label: 'Analytics',
    match: (p) => p.startsWith('/admin/analytics'),
    feature: 'analytics',
  },
  {
    href: '/admin/integrations',
    label: 'Integrations',
    match: (p) => p.startsWith('/admin/integrations'),
    feature: 'integrations',
  },
];

const settingsLinks = [
  { href: '/admin/settings/account', label: 'Account' },
  { href: '/admin/settings/branding', label: 'Branding' },
  { href: '/admin/settings/booking-qr', label: 'Booking QR' },
  { href: '/admin/settings/crm-join-qr', label: 'CRM join QR' },
  { href: '/admin/settings/locations', label: 'Locations' },
  { href: '/admin/settings/workspaces', label: 'Workspaces' },
  { href: '/admin/settings/team', label: 'Team' },
  { href: '/admin/settings/access', label: 'Access' },
  {
    href: '/admin/settings/subscription',
    label: 'Subscription',
  },
  {
    href: '/admin/settings/referrals',
    label: 'Refer & reward',
  },
];

function navClass(active: boolean, locked = false): string {
  return [
    'block rounded-lg px-2.5 py-1.5 text-sm transition',
    active
      ? 'bg-[var(--admin-accent)] font-semibold text-white'
      : locked
        ? 'text-[var(--admin-sidebar-text)]/45 hover:bg-white/5 hover:text-white/70'
        : 'text-[var(--admin-sidebar-text)]/80 hover:bg-white/10 hover:text-white',
  ].join(' ');
}

function featureEnabled(
  features: Record<string, boolean> | undefined,
  key?: string,
): boolean {
  if (!key) return true;
  if (!features) return true;
  return Boolean(features[key]);
}

function routeFeature(pathname: string): string | undefined {
  // Prefer the most specific nav match (Services before Bookings).
  const match = operationLinks.find((link) => link.feature && link.match(pathname));
  return match?.feature;
}

export function AdminAppShell({ children }: AdminAppShellProps) {
  const pathname = usePathname();
  const router = useRouter();
  const [bookSlug, setBookSlug] = useState('demo-salon');
  const [features, setFeatures] = useState<Record<string, boolean> | undefined>();
  const [permissions, setPermissions] = useState<string[] | null>(null);
  const [lockedModules, setLockedModules] = useState<ModuleUpgradePayload[]>([]);
  const [vapidPublicKey, setVapidPublicKey] = useState<string | null>(null);
  const [navOpen, setNavOpen] = useState(false);

  useEffect(() => {
    if (!getStoredToken()) {
      router.replace('/login');
      return;
    }
    setBookSlug(getStoredTenantSlug() ?? 'demo-salon');
    void fetchShell()
      .then((shell: ShellStatus) => {
        setFeatures(shell.features);
        setPermissions(shell.permissions ?? []);
        setLockedModules(shell.locked_modules ?? []);
        setVapidPublicKey(shell.vapid_public_key ?? null);
      })
      .catch(() => {
        /* keep nav visible if shell fails transiently */
      });
  }, [router]);

  useEffect(() => {
    if (!getStoredToken()) return;

    const beat = () => {
      void api('/admin/presence/heartbeat', { method: 'POST', auth: true }).catch(() => {});
    };

    beat();
    const id = window.setInterval(() => {
      if (document.visibilityState === 'visible') beat();
    }, 60_000);

    const onVis = () => {
      if (document.visibilityState === 'visible') beat();
    };
    document.addEventListener('visibilitychange', onVis);

    return () => {
      window.clearInterval(id);
      document.removeEventListener('visibilitychange', onVis);
    };
  }, []);

  useEffect(() => {
    setNavOpen(false);
  }, [pathname]);

  useEffect(() => {
    if (!navOpen) return;

    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setNavOpen(false);
    };
    document.addEventListener('keydown', onKey);

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = previousOverflow;
    };
  }, [navOpen]);

  const requiredFeature = routeFeature(pathname);
  const routeLocked =
    Boolean(requiredFeature) && !featureEnabled(features, requiredFeature);

  const lockedUpgrade = useMemo(() => {
    if (!requiredFeature || !routeLocked) return null;
    return (
      lockedModules.find((m) => m.module === requiredFeature) ?? {
        module: requiredFeature,
        module_label:
          operationLinks.find((l) => l.feature === requiredFeature)?.label ??
          requiredFeature,
        available_on: [
          { slug: 'pro', name: 'Pro' },
          { slug: 'diamond', name: 'Diamond' },
        ],
        suggested_plan_slug: 'pro',
        upgrade_href: '/admin/settings/subscription',
      }
    );
  }, [lockedModules, requiredFeature, routeLocked]);

  return (
    <div className="flex min-h-full bg-[linear-gradient(165deg,#f7f5f1_0%,#efebe4_48%,#f3f1ec_100%)] text-[var(--admin-ink)]">
      {navOpen ? (
        <button
          type="button"
          aria-label="Close navigation"
          onClick={() => setNavOpen(false)}
          className="fixed inset-0 z-30 bg-zinc-900/45 lg:hidden"
        />
      ) : null}
      <aside
        className={[
          'fixed inset-y-0 left-0 z-40 flex w-64 max-w-[82vw] shrink-0 flex-col bg-[var(--admin-sidebar)] text-[var(--admin-sidebar-text)] shadow-xl transition-transform duration-200 ease-out',
          navOpen ? 'translate-x-0' : '-translate-x-full',
          'lg:sticky lg:top-0 lg:z-auto lg:h-screen lg:w-60 lg:max-w-none lg:translate-x-0 lg:shadow-none',
        ].join(' ')}
      >
        <div className="border-b border-white/10 px-4 py-5">
          <div className="flex items-center gap-2.5">
            <NeatMeetLogo size={32} variant="onDark" />
            <div className="min-w-0">
              <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/50">
                NeatMeet OS
              </p>
              <p className="text-sm font-semibold text-white">Tenant admin</p>
            </div>
            <button
              type="button"
              onClick={() => setNavOpen(false)}
              aria-label="Close navigation"
              className="ml-auto inline-flex h-8 w-8 items-center justify-center rounded-lg text-white/70 hover:bg-white/10 hover:text-white lg:hidden"
            >
              <CloseIcon />
            </button>
          </div>
        </div>
        <nav className="flex-1 overflow-y-auto px-2.5 pb-[calc(1rem+env(safe-area-inset-bottom))] pt-4">
          <p className="mb-1.5 px-2.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-white/40">
            Operations
          </p>
          <ul className="space-y-0.5">
            {operationLinks
              .filter((link) => {
                if (!link.permission) return true;
                // Until shell loads, keep the item visible; after load, require the slug.
                if (permissions === null) return true;
                return permissions.includes(link.permission);
              })
              .map((link) => {
              const locked = !featureEnabled(features, link.feature);
              return (
                <li key={link.href}>
                  <Link
                    href={link.href}
                    onClick={() => setNavOpen(false)}
                    className={navClass(link.match(pathname), locked)}
                  >
                    <span className="flex items-center justify-between gap-2">
                      <span>{link.label}</span>
                      {locked ? (
                        <span className="rounded bg-white/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white/55">
                          Upgrade
                        </span>
                      ) : null}
                    </span>
                  </Link>
                </li>
              );
            })}
          </ul>
          <p className="mb-1.5 mt-5 px-2.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-white/40">
            Settings
          </p>
          <ul className="space-y-0.5">
            {settingsLinks.map((link) => (
              <li key={link.href}>
                <Link
                  href={link.href}
                  onClick={() => setNavOpen(false)}
                  className={navClass(pathname === link.href)}
                >
                  {link.label}
                </Link>
              </li>
            ))}
          </ul>
          <p className="mb-1.5 mt-5 px-2.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-white/40">
            Public
          </p>
          <Link
            href={`/book/${bookSlug}`}
            onClick={() => setNavOpen(false)}
            className={navClass(false)}
            target="_blank"
            rel="noreferrer"
          >
            Book online
          </Link>
        </nav>
      </aside>
      <div className="flex min-w-0 flex-1 flex-col">
        <AdminTopBar onMenuClick={() => setNavOpen(true)} />
        <AdminPwaPrompt vapidPublicKey={vapidPublicKey} />
        <AdminReferralNudge />
        <main className="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8">
          {routeLocked && lockedUpgrade ? (
            <ModuleUpgradeGate upgrade={lockedUpgrade} />
          ) : (
            children
          )}
        </main>
      </div>
    </div>
  );
}

function CloseIcon() {
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden>
      <path
        d="M6 6l12 12M18 6L6 18"
        stroke="currentColor"
        strokeWidth="1.75"
        strokeLinecap="round"
      />
    </svg>
  );
}
