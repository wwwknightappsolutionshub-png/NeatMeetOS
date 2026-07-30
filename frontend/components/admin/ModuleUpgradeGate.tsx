'use client';

import Link from 'next/link';
import type { ModuleUpgradePayload } from '@/lib/types';

const BENEFITS: Record<string, string> = {
  booking: 'Run your day board, services, walk-ins, and waitlist in one place.',
  crm: 'Keep client profiles, notes, consents, and history organised.',
  payments: 'Track payments, refunds, and collection reporting.',
  pos: 'Check out clients at the chair with retail, gift cards, and tips.',
  inventory: 'Manage stock levels, suppliers, and low-stock alerts.',
  memberships: 'Sell plans, packages, wallet credit, and loyalty rewards.',
  marketing: 'Send campaigns, reminders, and automated workflows.',
  notifications: 'Coordinate salon notifications across channels.',
  analytics: 'See booking trends, revenue, and performance dashboards.',
  integrations: 'Connect providers and sync events with the outside world.',
  ecommerce: 'Sell products online with your branded shop.',
  gallery: 'Showcase finished work in an Instagram-style grid on your booking page.',
  lookbook: 'Present editorial looks and seasonal inspiration on your public book page.',
  ai_hairstyle: 'Review customer-approved AI hairstyle previews and accept looks for the chair.',
  next_visit: 'Prompt members to schedule their next visit after check-in and nudge them before it.',
};

function planList(availableOn: ModuleUpgradePayload['available_on']): string {
  const names = availableOn.map((p) => p.name).filter(Boolean);
  if (names.length === 0) return 'a higher plan';
  if (names.length === 1) return names[0];
  if (names.length === 2) return `${names[0]} and ${names[1]}`;
  return `${names.slice(0, -1).join(', ')}, and ${names[names.length - 1]}`;
}

interface ModuleUpgradeGateProps {
  upgrade: ModuleUpgradePayload;
  compact?: boolean;
}

export function ModuleUpgradeGate({ upgrade, compact = false }: ModuleUpgradeGateProps) {
  const benefit =
    BENEFITS[upgrade.module] ??
    `Unlock ${upgrade.module_label} to grow your salon operations.`;
  const suggested =
    upgrade.available_on.find((p) => p.slug === upgrade.suggested_plan_slug)?.name ??
    upgrade.available_on[0]?.name ??
    'Pro';
  const href = upgrade.upgrade_href || '/admin/settings/subscription';

  return (
    <div
      className={`rounded-2xl border border-[#2f5a45]/20 bg-gradient-to-br from-[#f4faf6] via-white to-[#faf7f2] ${
        compact ? 'p-4' : 'p-6 sm:p-8'
      }`}
    >
      <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#2f5a45]/80">
        Upgrade to unlock
      </p>
      <h2
        className={`mt-2 font-semibold tracking-tight text-[#1c1917] ${
          compact ? 'text-lg' : 'text-2xl'
        }`}
      >
        {upgrade.module_label} is ready when you are
      </h2>
      <p className={`mt-2 max-w-xl text-[#57534e] ${compact ? 'text-sm' : 'text-sm sm:text-base'}`}>
        {benefit} Included on {planList(upgrade.available_on)}.
      </p>
      <div className="mt-5 flex flex-wrap items-center gap-3">
        <Link
          href={href}
          className="inline-flex rounded-lg bg-[#2f5a45] px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110"
        >
          Upgrade to {suggested}
        </Link>
        <Link
          href={href}
          className="text-sm font-medium text-[#2f5a45] underline-offset-2 hover:underline"
        >
          Compare plans
        </Link>
      </div>
    </div>
  );
}

interface SmartErrorAlertProps {
  message: string;
  upgrade?: ModuleUpgradePayload | null;
}

/** Renders an upgrade gate for module locks; otherwise a standard error alert. */
export function SmartErrorAlert({ message, upgrade }: SmartErrorAlertProps) {
  if (upgrade) {
    return <ModuleUpgradeGate upgrade={upgrade} compact />;
  }

  const looksLikeUpgrade =
    /upgrade your plan|available on|not enabled for your subscription/i.test(message);

  if (looksLikeUpgrade) {
    return (
      <ModuleUpgradeGate
        upgrade={{
          module: 'feature',
          module_label: 'This feature',
          available_on: [
            { slug: 'pro', name: 'Pro' },
            { slug: 'diamond', name: 'Diamond' },
          ],
          suggested_plan_slug: 'pro',
          upgrade_href: '/admin/settings/subscription',
        }}
        compact
      />
    );
  }

  return (
    <div className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
      {message}
    </div>
  );
}
