'use client';

import { FormEvent, useCallback, useEffect, useState } from 'react';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import { TenantModulesPanel } from '@/components/platform/TenantModulesPanel';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { PlatformTenantRow } from '@/lib/types';
import {
  fetchPlatformProfile,
  fetchPlatformTenants,
  pokeTenant,
  purgePlatformTenant,
  unlockTenantTiers,
  type PlatformStaffUser,
} from '@/services/platform.service';

function statusClass(status: string): string {
  switch (status) {
    case 'active':
      return 'bg-emerald-500/15 text-emerald-300';
    case 'trial':
      return 'bg-amber-500/15 text-amber-200';
    case 'pending_activation':
      return 'bg-sky-500/15 text-sky-200';
    case 'suspended':
    case 'inactive':
      return 'bg-red-500/15 text-red-300';
    default:
      return 'bg-white/10 text-stone-300';
  }
}

function formatDate(iso: string | null | undefined): string {
  if (!iso) return '—';
  try {
    return new Date(iso).toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  } catch {
    return iso;
  }
}

function formatLastSeen(iso: string | null | undefined): string {
  if (!iso) return 'Never';
  try {
    return new Date(iso).toLocaleString(undefined, {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch {
    return iso;
  }
}

function TrashIcon({ className }: { className?: string }) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      className={className}
      aria-hidden
    >
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M4.5 7.5h15M9.5 7.5V6a1.5 1.5 0 0 1 1.5-1.5h2A1.5 1.5 0 0 1 14.5 6v1.5m2 0V18a1.5 1.5 0 0 1-1.5 1.5h-6A1.5 1.5 0 0 1 7.5 18V7.5m2 3.5v6m3-6v6"
      />
    </svg>
  );
}

export default function PlatformTenantsPage() {
  const [tenants, setTenants] = useState<PlatformTenantRow[]>([]);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [unlockingId, setUnlockingId] = useState<string | null>(null);
  const [pokingId, setPokingId] = useState<string | null>(null);
  const [pokeNotice, setPokeNotice] = useState<string | null>(null);
  const [modulesTenant, setModulesTenant] = useState<PlatformTenantRow | null>(
    null,
  );
  const [unlockPlanByTenant, setUnlockPlanByTenant] = useState<
    Record<string, 'basic' | 'pro' | 'diamond'>
  >({});
  const [profile, setProfile] = useState<PlatformStaffUser | null>(null);
  const [purgeTenant, setPurgeTenant] = useState<PlatformTenantRow | null>(null);
  const [purgeSlugConfirm, setPurgeSlugConfirm] = useState('');
  const [purging, setPurging] = useState(false);
  const [purgeNotice, setPurgeNotice] = useState<string | null>(null);

  const canPurge = profile?.platform_role === 'owner';

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setTenants(
        await fetchPlatformTenants({
          search: search.trim() || undefined,
          status: status || undefined,
        }),
      );
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load tenants');
    } finally {
      setLoading(false);
    }
  }, [search, status]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    fetchPlatformProfile()
      .then((data) => setProfile(data.user))
      .catch(() => setProfile(null));
  }, []);

  async function handleUnlock(tenant: PlatformTenantRow) {
    setUnlockingId(tenant.id);
    setError(null);
    try {
      const plan =
        unlockPlanByTenant[tenant.id] ??
        (tenant.desired_plan_slug === 'pro' ||
        tenant.desired_plan_slug === 'diamond'
          ? tenant.desired_plan_slug
          : 'pro');
      await unlockTenantTiers(tenant.id, plan);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Unlock failed');
    } finally {
      setUnlockingId(null);
    }
  }

  async function handlePoke(tenant: PlatformTenantRow) {
    setPokingId(tenant.id);
    setError(null);
    setPokeNotice(null);
    try {
      const result = await pokeTenant(tenant.id);
      setPokeNotice(
        `Poked ${tenant.trading_name || tenant.name}: ${result.notices} notice(s), ${result.emails} email(s), ${result.pushes} push(es).`,
      );
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Poke failed');
    } finally {
      setPokingId(null);
    }
  }

  async function handlePurge(e: FormEvent) {
    e.preventDefault();
    if (!purgeTenant) return;
    setPurging(true);
    setError(null);
    setPurgeNotice(null);
    try {
      const result = await purgePlatformTenant(purgeTenant.id, {
        confirmation_slug: purgeSlugConfirm.trim(),
        confirm: true,
      });
      setPurgeNotice(
        `Permanently deleted ${result.name} (${result.slug}) and all related data.`,
      );
      setPurgeTenant(null);
      setPurgeSlugConfirm('');
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Permanent delete failed');
    } finally {
      setPurging(false);
    }
  }

  return (
    <div className="mx-auto grid max-w-6xl gap-5">
      <div>
        <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-400/90">
          Platform
        </p>
        <h1 className="mt-1 text-2xl font-semibold tracking-tight text-white">Tenants</h1>
        <p className="mt-1 text-sm text-stone-400">
          All salons on the network. Unlock Pro/Diamond early or override modules per tenant.
        </p>
      </div>

      <Card className="border-white/10 bg-white/5">
        <form
          className="flex flex-col gap-3 sm:flex-row sm:items-end"
          onSubmit={(e) => {
            e.preventDefault();
            void load();
          }}
        >
          <label className="block flex-1 text-sm">
            <span className="mb-1 block text-stone-400">Search</span>
            <input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full rounded-lg border border-white/15 bg-stone-950/40 px-3 py-2 text-sm text-white outline-none focus:border-amber-500"
              placeholder="Name, slug, email…"
            />
          </label>
          <label className="block text-sm sm:w-44">
            <span className="mb-1 block text-stone-400">Status</span>
            <select
              value={status}
              onChange={(e) => setStatus(e.target.value)}
              className="w-full rounded-lg border border-white/15 bg-stone-950/40 px-3 py-2 text-sm text-white outline-none focus:border-amber-500"
            >
              <option value="">All</option>
              <option value="active">Active</option>
              <option value="trial">Trial</option>
              <option value="pending_activation">Pending activation</option>
              <option value="suspended">Suspended</option>
              <option value="inactive">Inactive</option>
            </select>
          </label>
          <Button type="submit" className="!bg-[var(--platform-accent)]">
            Filter
          </Button>
        </form>
      </Card>

      {error ? <ErrorAlert message={error} /> : null}
      {pokeNotice ? (
        <div className="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
          {pokeNotice}
        </div>
      ) : null}
      {purgeNotice ? (
        <div className="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
          {purgeNotice}
        </div>
      ) : null}
      {loading ? <LoadingState label="Loading tenants…" /> : null}

      {!loading ? (
        <Card className="overflow-hidden border-white/10 bg-white/5 p-0">
          {tenants.length === 0 ? (
            <p className="px-5 py-8 text-center text-sm text-stone-400">No tenants match.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="min-w-full text-left text-sm">
                <thead className="border-b border-white/10 text-[11px] uppercase tracking-[0.12em] text-stone-400">
                  <tr>
                    <th className="px-4 py-3 font-semibold">Tenant</th>
                    <th className="px-4 py-3 font-semibold">Presence</th>
                    <th className="px-4 py-3 font-semibold">Status</th>
                    <th className="px-4 py-3 font-semibold">Plan</th>
                    <th className="px-4 py-3 font-semibold">Desired</th>
                    <th className="px-4 py-3 font-semibold">Tiers</th>
                    <th className="px-4 py-3 font-semibold">Trial ends</th>
                    <th className="px-4 py-3 font-semibold">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {tenants.map((t) => (
                    <tr key={t.id} className="border-b border-white/5 last:border-0">
                      <td className="px-4 py-3">
                        <p className="font-semibold text-white">{t.trading_name || t.name}</p>
                        <p className="text-xs text-stone-400">
                          {t.slug}
                          {t.owner_whatsapp ? ` · ${t.owner_whatsapp}` : ''}
                          {typeof t.pwa_subscribers === 'number'
                            ? ` · ${t.pwa_subscribers} PWA`
                            : ''}
                        </p>
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={`inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold ${
                            t.online
                              ? 'bg-emerald-500/15 text-emerald-300'
                              : 'bg-white/10 text-stone-400'
                          }`}
                        >
                          <span
                            className={`h-1.5 w-1.5 rounded-full ${
                              t.online ? 'bg-emerald-400' : 'bg-stone-500'
                            }`}
                          />
                          {t.online ? 'Online' : 'Offline'}
                        </span>
                        <p className="mt-1 text-[11px] text-stone-500">
                          Last seen {formatLastSeen(t.admin_last_seen_at)}
                        </p>
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${statusClass(t.status)}`}
                        >
                          {t.status}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-stone-300">{t.plan_name ?? '—'}</td>
                      <td className="px-4 py-3 text-stone-300">
                        {t.desired_plan_slug ?? '—'}
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${
                            t.tier_unlocked
                              ? 'bg-emerald-500/15 text-emerald-300'
                              : 'bg-white/10 text-stone-400'
                          }`}
                        >
                          {t.tier_unlocked ? 'Unlocked' : 'Locked'}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-stone-300">
                        {formatDate(t.trial_ends_at)}
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:flex-wrap">
                          <Button
                            type="button"
                            disabled={pokingId === t.id}
                            className="!border !border-amber-500/40 !bg-amber-500/10 !px-2.5 !py-1.5 !text-xs !text-amber-200"
                            onClick={() => void handlePoke(t)}
                          >
                            {pokingId === t.id ? 'Poking…' : 'Poke'}
                          </Button>
                          <select
                            value={
                              unlockPlanByTenant[t.id] ??
                              (t.desired_plan_slug === 'pro' ||
                              t.desired_plan_slug === 'diamond'
                                ? t.desired_plan_slug
                                : 'pro')
                            }
                            onChange={(e) =>
                              setUnlockPlanByTenant((prev) => ({
                                ...prev,
                                [t.id]: e.target.value as 'basic' | 'pro' | 'diamond',
                              }))
                            }
                            className="rounded-lg border border-white/15 bg-stone-950/40 px-2 py-1.5 text-xs text-white outline-none focus:border-amber-500"
                          >
                            <option value="basic">Basic</option>
                            <option value="pro">Pro</option>
                            <option value="diamond">Diamond</option>
                          </select>
                          <Button
                            type="button"
                            disabled={unlockingId === t.id}
                            className="!bg-[var(--platform-accent)] !px-2.5 !py-1.5 !text-xs"
                            onClick={() => void handleUnlock(t)}
                          >
                            {unlockingId === t.id
                              ? 'Unlocking…'
                              : 'Unlock Pro/Diamond'}
                          </Button>
                          <Button
                            type="button"
                            className="!border !border-white/15 !bg-transparent !px-2.5 !py-1.5 !text-xs !text-stone-100"
                            onClick={() => setModulesTenant(t)}
                          >
                            Modules
                          </Button>
                          {canPurge ? (
                            <button
                              type="button"
                              title="Permanently delete tenant"
                              aria-label={`Permanently delete ${t.trading_name || t.name}`}
                              className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-red-500/40 bg-red-500/10 text-red-300 transition hover:bg-red-500/20"
                              onClick={() => {
                                setPurgeTenant(t);
                                setPurgeSlugConfirm('');
                                setError(null);
                              }}
                            >
                              <TrashIcon className="h-4 w-4" />
                            </button>
                          ) : null}
                          <a
                            href={`/book/${t.slug}`}
                            target="_blank"
                            rel="noreferrer"
                            className="text-xs text-amber-300 underline hover:text-amber-200"
                          >
                            Open book
                          </a>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      ) : null}

      {modulesTenant ? (
        <TenantModulesPanel
          tenantId={modulesTenant.id}
          tenantName={modulesTenant.trading_name || modulesTenant.name}
          onClose={() => setModulesTenant(null)}
        />
      ) : null}

      {purgeTenant ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-950/70 p-4 backdrop-blur-sm">
          <Card className="w-full max-w-md border-red-500/30 bg-[#1a1714] p-5">
            <h2 className="text-lg font-semibold text-white">Permanently delete tenant</h2>
            <p className="mt-2 text-sm text-stone-300">
              This permanently deletes{' '}
              <span className="font-semibold text-white">
                {purgeTenant.trading_name || purgeTenant.name}
              </span>{' '}
              (
              <span className="font-mono text-xs text-amber-200">{purgeTenant.slug}</span>
              ) and all related salon data from the database. This cannot be undone.
            </p>
            <form onSubmit={(e) => void handlePurge(e)} className="mt-4 space-y-3">
              <label className="block text-sm">
                <span className="mb-1 block text-stone-400">
                  Type slug “{purgeTenant.slug}” to confirm
                </span>
                <input
                  value={purgeSlugConfirm}
                  onChange={(e) => setPurgeSlugConfirm(e.target.value)}
                  className="w-full rounded-lg border border-white/15 bg-stone-950/40 px-3 py-2 text-sm text-white outline-none focus:border-red-400"
                  placeholder={purgeTenant.slug}
                  autoComplete="off"
                  autoFocus
                />
              </label>
              <div className="flex flex-wrap gap-2 pt-1">
                <Button
                  type="submit"
                  disabled={
                    purging ||
                    purgeSlugConfirm.trim().toLowerCase() !==
                      purgeTenant.slug.toLowerCase()
                  }
                  className="!bg-red-600 !px-3 !py-2 !text-sm hover:!bg-red-500"
                >
                  {purging ? 'Deleting…' : 'Delete forever'}
                </Button>
                <Button
                  type="button"
                  disabled={purging}
                  className="!border !border-white/15 !bg-transparent !px-3 !py-2 !text-sm !text-stone-100"
                  onClick={() => {
                    setPurgeTenant(null);
                    setPurgeSlugConfirm('');
                  }}
                >
                  Cancel
                </Button>
              </div>
            </form>
          </Card>
        </div>
      ) : null}
    </div>
  );
}
