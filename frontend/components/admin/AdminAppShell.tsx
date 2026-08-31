'use client';

import dynamic from 'next/dynamic';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { AdminTopBar } from '@/components/admin/AdminTopBar';
import { NeatMeetLogo } from '@/components/brand/NeatMeetLogo';
import { api, getStoredTenantSlug, getStoredToken } from '@/lib/api-client';
import type { ModuleUpgradePayload, ShellStatus } from '@/lib/types';
import { fetchShell, signOutToLogin } from '@/services/auth.service';
import { fetchActiveStaffSosAlerts } from '@/services/staff-sos.service';

const AdminPwaPrompt = dynamic(
  () => import('@/components/admin/AdminPwaPrompt').then((m) => m.AdminPwaPrompt),
  { ssr: false },
);
const AdminReferralNudge = dynamic(
  () => import('@/components/admin/AdminReferralNudge').then((m) => m.AdminReferralNudge),
  { ssr: false },
);
const AvailabilitySetupModal = dynamic(
  () =>
    import('@/components/admin/AvailabilitySetupModal').then((m) => m.AvailabilitySetupModal),
  { ssr: false },
);
const StaffSosOverlay = dynamic(
  () => import('@/components/admin/StaffSosOverlay').then((m) => m.StaffSosOverlay),
  { ssr: false },
);
const ModuleUpgradeGate = dynamic(
  () => import('@/components/admin/ModuleUpgradeGate').then((m) => m.ModuleUpgradeGate),
  { ssr: false },
);

interface AdminAppShellProps {
  children: ReactNode;
}

type NavLink = {
  href: string;
  label: string;
  match: (p: string) => boolean;
  feature?: string;
  /** When set, nav item is hidden unless the shell permissions include this slug. */
  permission?: string;
  /** Show live new-booking SOS count badge ahead of the label. */
  newBookingBadge?: boolean;
};

type NavGroupId = 'front_desk' | 'commerce' | 'experience' | 'growth' | 'settings';

type NavGroup = {
  id: NavGroupId;
  label: string;
  links: NavLink[];
};

const navGroups: NavGroup[] = [
  {
    id: 'front_desk',
    label: 'Front desk',
    links: [
      {
        href: '/admin/dashboard',
        label: 'Dashboard',
        match: (p) => p === '/admin/dashboard',
        newBookingBadge: true,
      },
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
        newBookingBadge: true,
      },
      {
        href: '/admin/clients',
        label: 'Customers',
        match: (p) => p.startsWith('/admin/clients'),
        feature: 'crm',
      },
      {
        href: '/admin/messages',
        label: 'Messages',
        match: (p) => p.startsWith('/admin/messages'),
        feature: 'crm',
      },
      { href: '/admin/staff', label: 'Staffs', match: (p) => p.startsWith('/admin/staff') },
    ],
  },
  {
    id: 'commerce',
    label: 'Commerce',
    links: [
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
        href: '/admin/money',
        label: 'My Finance So Far',
        match: (p) => p.startsWith('/admin/money'),
        feature: 'money',
        permission: 'money.view',
      },
      {
        href: '/admin/ecommerce',
        label: 'Shop',
        match: (p) => p.startsWith('/admin/ecommerce'),
        feature: 'ecommerce',
      },
    ],
  },
  {
    id: 'experience',
    label: 'Experience',
    links: [
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
    ],
  },
  {
    id: 'growth',
    label: 'Growth',
    links: [
      {
        href: '/admin/memberships',
        label: 'Client rewards',
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
        // WhatsApp scan moved to Settings (not plan-gated).
        match: (p) =>
          p.startsWith('/admin/integrations') && !p.startsWith('/admin/integrations/whatsapp'),
        feature: 'integrations',
      },
    ],
  },
  {
    id: 'settings',
    label: 'Settings',
    links: [
      {
        href: '/admin/settings/account',
        label: 'Account',
        match: (p) => p.startsWith('/admin/settings/account'),
      },
      {
        href: '/admin/settings/branding',
        label: 'Branding',
        match: (p) => p.startsWith('/admin/settings/branding'),
      },
      {
        href: '/admin/settings/whatsapp',
        label: 'Salon WhatsApp',
        match: (p) => p.startsWith('/admin/settings/whatsapp'),
      },
      {
        href: '/admin/settings/booking-qr',
        label: 'Booking QR',
        match: (p) => p.startsWith('/admin/settings/booking-qr'),
      },
      {
        href: '/admin/settings/crm-join-qr',
        label: 'Customer QR',
        match: (p) => p.startsWith('/admin/settings/crm-join-qr'),
      },
      {
        href: '/admin/settings/locations',
        label: 'Locations',
        match: (p) => p.startsWith('/admin/settings/locations'),
      },
      {
        href: '/admin/settings/workspaces',
        label: 'Workspaces',
        match: (p) => p.startsWith('/admin/settings/workspaces'),
      },
      {
        href: '/admin/settings/team',
        label: 'Team',
        match: (p) => p.startsWith('/admin/settings/team'),
      },
      {
        href: '/admin/settings/access',
        label: 'Access',
        match: (p) => p.startsWith('/admin/settings/access'),
      },
      {
        href: '/admin/settings/subscription',
        label: 'Subscription',
        match: (p) => p.startsWith('/admin/settings/subscription'),
      },
      {
        href: '/admin/settings/referrals',
        label: 'Refer & reward',
        match: (p) => p.startsWith('/admin/settings/referrals'),
      },
    ],
  },
];

const allOperationLinks = navGroups
  .filter((g) => g.id !== 'settings')
  .flatMap((g) => g.links);

function activeNavGroupId(pathname: string): NavGroupId {
  if (pathname.startsWith('/admin/settings')) return 'settings';
  for (const group of navGroups) {
    if (group.id === 'settings') continue;
    if (group.links.some((link) => link.match(pathname))) return group.id;
  }
  return 'front_desk';
}

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
  const match = allOperationLinks.find((link) => link.feature && link.match(pathname));
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
  const [signingOut, setSigningOut] = useState(false);
  const [availabilityModalOpen, setAvailabilityModalOpen] = useState(false);
  const [staffPath, setStaffPath] = useState('/admin/staff');
  const [newBookingCount, setNewBookingCount] = useState(0);
  const routeGroupId = activeNavGroupId(pathname);
  const [openGroupId, setOpenGroupId] = useState<NavGroupId | null>(routeGroupId);

  useEffect(() => {
    if (!getStoredToken()) return;

    const refreshNewBookingBadge = () => {
      void fetchActiveStaffSosAlerts()
        .then((items) => {
          setNewBookingCount(items.filter((a) => a.kind === 'new_booking').length);
        })
        .catch(() => {
          /* badge is best-effort */
        });
    };

    refreshNewBookingBadge();
    const onSos = (event: Event) => {
      const detail = (event as CustomEvent<{ items?: { kind: string }[] }>).detail;
      if (Array.isArray(detail?.items)) {
        setNewBookingCount(detail.items.filter((a) => a.kind === 'new_booking').length);
        return;
      }
      refreshNewBookingBadge();
    };
    window.addEventListener('neatmeet:staff-sos', onSos);
    const id = window.setInterval(refreshNewBookingBadge, 30_000);

    return () => {
      window.removeEventListener('neatmeet:staff-sos', onSos);
      window.clearInterval(id);
    };
  }, []);

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
        const path = shell.onboarding?.staff_path || '/admin/staff';
        setStaffPath(path);
        const dismissed =
          typeof window !== 'undefined' &&
          sessionStorage.getItem('nm_availability_nudge_dismissed') === '1';
        if (shell.onboarding && !shell.onboarding.availability_set && !dismissed) {
          setAvailabilityModalOpen(true);
        }
      })
      .catch(() => {
        /* keep nav visible if shell fails transiently */
      });
  }, [router]);

  function dismissAvailabilityModal() {
    sessionStorage.setItem('nm_availability_nudge_dismissed', '1');
    setAvailabilityModalOpen(false);
  }

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
    // Auto-collapse: only the category for the current page stays open.
    setOpenGroupId(routeGroupId);
  }, [pathname, routeGroupId]);

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
          allOperationLinks.find((l) => l.feature === requiredFeature)?.label ??
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

  function toggleGroup(id: NavGroupId) {
    // Accordion: one open section at a time; tap again to collapse.
    setOpenGroupId((current) => (current === id ? null : id));
  }

  async function handleSignOut() {
    setSigningOut(true);
    await signOutToLogin();
  }

  const analyticsLocked = features !== undefined && !featureEnabled(features, 'analytics');
  const analyticsUpgrade = useMemo(() => {
    if (!analyticsLocked) return null;
    return (
      lockedModules.find((m) => m.module === 'analytics') ?? {
        module: 'analytics',
        module_label: 'Analytics',
        available_on: [
          { slug: 'pro', name: 'Pro' },
          { slug: 'diamond', name: 'Diamond' },
        ],
        suggested_plan_slug: 'pro',
        upgrade_href: '/admin/settings/subscription',
      }
    );
  }, [analyticsLocked, lockedModules]);

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
        <nav className="flex-1 overflow-y-auto px-2.5 pb-[calc(1rem+env(safe-area-inset-bottom))] pt-3">
          <div className="space-y-1">
            {navGroups.map((group) => {
              const links = group.links.filter((link) => {
                if (!link.permission) return true;
                if (permissions === null) return true;
                return permissions.includes(link.permission);
              });
              if (links.length === 0) return null;

              const open = openGroupId === group.id;
              const containsActive = group.links.some((link) => link.match(pathname));

              return (
                <div key={group.id}>
                  <button
                    type="button"
                    onClick={() => toggleGroup(group.id)}
                    aria-expanded={open}
                    className={[
                      'flex w-full items-center justify-between rounded-lg px-2.5 py-2 text-left transition',
                      containsActive
                        ? 'bg-white/10 text-white'
                        : 'text-white/55 hover:bg-white/5 hover:text-white/80',
                    ].join(' ')}
                  >
                    <span className="text-[10px] font-semibold uppercase tracking-[0.16em]">
                      {group.label}
                    </span>
                    <ChevronIcon open={open} />
                  </button>
                  {open ? (
                    <ul className="mt-0.5 space-y-0.5 pb-1">
                      {links.map((link) => {
                        const locked = !featureEnabled(features, link.feature);
                        const badge =
                          link.newBookingBadge && newBookingCount > 0 ? newBookingCount : 0;
                        return (
                          <li key={link.href}>
                            <Link
                              href={link.href}
                              onClick={() => setNavOpen(false)}
                              className={navClass(link.match(pathname), locked)}
                            >
                              <span className="flex items-center justify-between gap-2">
                                <span className="flex min-w-0 items-center gap-2">
                                  {badge > 0 ? (
                                    <span
                                      className="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1.5 text-[10px] font-bold leading-none text-white"
                                      aria-label={`${badge} new booking${badge === 1 ? '' : 's'}`}
                                    >
                                      {badge > 99 ? '99+' : badge}
                                    </span>
                                  ) : null}
                                  <span>{link.label}</span>
                                </span>
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
                  ) : null}
                </div>
              );
            })}
          </div>

          <div className="mt-3 border-t border-white/10 pt-3">
            <p className="mb-1.5 px-2.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-white/40">
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
          </div>

          {analyticsUpgrade ? (
            <div className="mt-3 rounded-xl border border-amber-400/30 bg-gradient-to-b from-amber-500/15 to-transparent p-3">
              <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-amber-200/80">
                Upgrade to unlock
              </p>
              <p className="mt-1 text-sm font-semibold text-white">
                Analytics is ready when you are
              </p>
              <p className="mt-1 text-xs leading-relaxed text-white/55">
                See booking trends, revenue, and performance dashboards. Included on{' '}
                {analyticsUpgrade.available_on.map((p) => p.name).join(' and ')}.
              </p>
              <div className="mt-3 flex flex-col gap-1.5">
                <Link
                  href={analyticsUpgrade.upgrade_href || '/admin/settings/subscription'}
                  onClick={() => setNavOpen(false)}
                  className="inline-flex items-center justify-center rounded-lg bg-[var(--admin-accent)] px-3 py-2 text-xs font-semibold text-white hover:brightness-110"
                >
                  Upgrade to{' '}
                  {analyticsUpgrade.available_on.find(
                    (p) => p.slug === analyticsUpgrade.suggested_plan_slug,
                  )?.name ?? 'Pro'}
                </Link>
                <Link
                  href="/admin/settings/subscription"
                  onClick={() => setNavOpen(false)}
                  className="text-center text-xs font-medium text-white/60 underline-offset-2 hover:text-white hover:underline"
                >
                  Compare plans
                </Link>
              </div>
            </div>
          ) : null}
        </nav>
        <div className="border-t border-white/10 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
          <button
            type="button"
            disabled={signingOut}
            onClick={() => void handleSignOut()}
            className="flex w-full items-center justify-center rounded-lg border border-white/15 bg-white/5 px-3 py-2.5 text-sm font-semibold text-white hover:bg-white/10 disabled:opacity-50"
          >
            {signingOut ? 'Signing out…' : 'Sign out'}
          </button>
        </div>
      </aside>
      <div className="flex min-w-0 flex-1 flex-col">
        <AdminTopBar onMenuClick={() => setNavOpen(true)} />
        <AdminPwaPrompt vapidPublicKey={vapidPublicKey} />
        <StaffSosOverlay />
        <AdminReferralNudge />
        <main className="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8">
          {routeLocked && lockedUpgrade ? (
            <ModuleUpgradeGate upgrade={lockedUpgrade} />
          ) : (
            children
          )}
        </main>
      </div>
      <AvailabilitySetupModal
        open={availabilityModalOpen}
        staffPath={staffPath}
        onDismiss={dismissAvailabilityModal}
      />
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

function ChevronIcon({ open }: { open: boolean }) {
  return (
    <svg
      width="14"
      height="14"
      viewBox="0 0 24 24"
      fill="none"
      aria-hidden
      className={[
        'shrink-0 text-current/70 transition-transform duration-200',
        open ? 'rotate-180' : '',
      ].join(' ')}
    >
      <path
        d="M6 9l6 6 6-6"
        stroke="currentColor"
        strokeWidth="1.75"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}
