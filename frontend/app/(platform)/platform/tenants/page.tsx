'use client';

import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import { TenantModulesPanel } from '@/components/platform/TenantModulesPanel';
import { TenantBookingPolicyPanel } from '@/components/platform/TenantBookingPolicyPanel';
import { Button } from '@/components/ui/Button';
import type { PlatformTenantRow } from '@/lib/types';
import {
  fetchPlatformProfile,
  fetchPlatformTenants,
  pokeTenant,
  purgePlatformTenant,
  unlockTenantTiers,
  updatePlatformTenantOwnerEmail,
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

function MoreIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" className={className} aria-hidden>
      <circle cx="5" cy="12" r="1.6" />
      <circle cx="12" cy="12" r="1.6" />
      <circle cx="19" cy="12" r="1.6" />
    </svg>
  );
}

const fieldClass =
  'w-full rounded-lg border border-white/20 bg-stone-950 px-3 py-2.5 text-sm text-white outline-none placeholder:text-stone-500 focus:border-amber-400';

const menuBtnClass =
  'block w-full px-3 py-2 text-left text-sm text-stone-100 hover:bg-white/10 disabled:opacity-50';

export default function PlatformTenantsPage() {
  const [tenants, setTenants] = useState<PlatformTenantRow[]>([]);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [unlockingId, setUnlockingId] = useState<string | null>(null);
  const [pokingId, setPokingId] = useState<string | null>(null);
  const [pokeNotice, setPokeNotice] = useState<string | null>(null);
  const [modulesTenant, setModulesTenant] = useState<PlatformTenantRow | null>(null);
  const [policyTenant, setPolicyTenant] = useState<PlatformTenantRow | null>(null);
  const [unlockPlanByTenant, setUnlockPlanByTenant] = useState<
    Record<string, 'basic' | 'pro' | 'diamond'>
  >({});
  const [profile, setProfile] = useState<PlatformStaffUser | null>(null);
  const [purgeTenant, setPurgeTenant] = useState<PlatformTenantRow | null>(null);
  const [purgeSlugConfirm, setPurgeSlugConfirm] = useState('');
  const [purging, setPurging] = useState(false);
  const [purgeNotice, setPurgeNotice] = useState<string | null>(null);
  const [emailTenant, setEmailTenant] = useState<PlatformTenantRow | null>(null);
  const [emailDraft, setEmailDraft] = useState('');
  const [savingEmail, setSavingEmail] = useState(false);
  const [emailNotice, setEmailNotice] = useState<string | null>(null);
  const [emailError, setEmailError] = useState<string | null>(null);
  const [menuOpenId, setMenuOpenId] = useState<string | null>(null);
  const menuRef = useRef<HTMLDivElement | null>(null);

  const canPurge =
    profile?.platform_role === 'owner' ||
    (profile?.is_platform_admin === true && !profile?.platform_role);

  const canChangeEmail =
    profile?.platform_role === 'owner' ||
    profile?.platform_role === 'manager' ||
    (profile?.is_platform_admin === true && !profile?.platform_role);

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

  useEffect(() => {
    if (!menuOpenId) return;
    function onDocClick(event: MouseEvent) {
      if (!menuRef.current?.contains(event.target as Node)) {
        setMenuOpenId(null);
      }
    }
    document.addEventListener('mousedown', onDocClick);
    return () => document.removeEventListener('mousedown', onDocClick);
  }, [menuOpenId]);

  async function handleUnlock(tenant: PlatformTenantRow) {
    setUnlockingId(tenant.id);
    setError(null);
    setMenuOpenId(null);
    try {
      const plan =
        unlockPlanByTenant[tenant.id] ??
        (tenant.desired_plan_slug === 'pro' || tenant.desired_plan_slug === 'diamond'
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
    setMenuOpenId(null);
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
    const targetId = purgeTenant.id;
    setPurging(true);
    setError(null);
    setPurgeNotice(null);
    try {
      const result = await purgePlatformTenant(targetId, {
        confirmation_slug: purgeSlugConfirm.trim(),
        confirm: true,
      });
      setTenants((prev) => prev.filter((t) => t.id !== targetId));
      setPurgeTenant(null);
      setPurgeSlugConfirm('');
      setPurgeNotice(
        `Permanently deleted ${result.name} (${result.slug}) and all related data.`,
      );
      try {
        await load();
      } catch {
        // List already updated optimistically
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Permanent delete failed');
    } finally {
      setPurging(false);
    }
  }

  async function handleChangeEmail(e: FormEvent) {
    e.preventDefault();
    if (!emailTenant) return;
    const nextEmail = emailDraft.trim().toLowerCase();
    if (!nextEmail) return;
    setSavingEmail(true);
    setEmailError(null);
    setEmailNotice(null);
    try {
      const result = await updatePlatformTenantOwnerEmail(emailTenant.id, nextEmail);
      setTenants((prev) =>
        prev.map((t) =>
          t.id === emailTenant.id
            ? {
                ...t,
                owner_email: result.owner_email,
                contact_email: result.contact_email,
              }
            : t,
        ),
      );
      setEmailNotice(
        `Updated login email for ${emailTenant.trading_name || emailTenant.name} to ${result.owner_email}.`,
      );
      setEmailTenant(null);
      setEmailDraft('');
      await load();
    } catch (err) {
      setEmailError(err instanceof Error ? err.message : 'Could not update email');
    } finally {
      setSavingEmail(false);
    }
  }

  return (
    <div className="mx-auto grid max-w-5xl gap-5">
      <div>
        <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-400/90">
          Platform
        </p>
        <h1 className="mt-1 text-2xl font-semibold tracking-tight text-white">Tenants</h1>
        <p className="mt-1 text-sm text-stone-400">
          Manage salons, unlock tiers, and update owner login emails.
        </p>
      </div>

      <section className="rounded-2xl border border-white/10 bg-white/[0.04] p-4">
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
              className={fieldClass}
              placeholder="Name, slug, email…"
            />
          </label>
          <label className="block text-sm sm:w-44">
            <span className="mb-1 block text-stone-400">Status</span>
            <select
              value={status}
              onChange={(e) => setStatus(e.target.value)}
              className={fieldClass}
            >
              <option value="">All</option>
              <option value="active">Active</option>
              <option value="trial">Trial</option>
              <option value="pending_activation">Pending activation</option>
              <option value="suspended">Suspended</option>
              <option value="inactive">Inactive</option>
            </select>
          </label>
          <Button type="submit" className="!bg-[var(--platform-accent)] !text-white">
            Filter
          </Button>
        </form>
      </section>

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
      {emailNotice ? (
        <div className="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
          {emailNotice}
        </div>
      ) : null}
      {loading ? <LoadingState label="Loading tenants…" /> : null}

      {!loading ? (
        <div className="space-y-3">
          {tenants.length === 0 ? (
            <p className="rounded-2xl border border-white/10 bg-white/[0.04] px-5 py-8 text-center text-sm text-stone-400">
              No tenants match.
            </p>
          ) : (
            tenants.map((t) => {
              const unlockPlan =
                unlockPlanByTenant[t.id] ??
                (t.desired_plan_slug === 'pro' || t.desired_plan_slug === 'diamond'
                  ? t.desired_plan_slug
                  : 'pro');
              const ownerEmail = t.owner_email || t.contact_email;

              return (
                <article
                  key={t.id}
                  className="rounded-2xl border border-white/10 bg-white/[0.04] px-4 py-3"
                >
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <h2 className="truncate text-base font-semibold text-white">
                          {t.trading_name || t.name}
                        </h2>
                        <span
                          className={`inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ${statusClass(t.status)}`}
                        >
                          {t.status}
                        </span>
                        <span
                          className={`inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold ${
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
                      </div>
                      <p className="mt-1 truncate text-xs text-stone-400">
                        {t.slug}
                        {ownerEmail ? ` · ${ownerEmail}` : ''}
                        {t.owner_whatsapp ? ` · ${t.owner_whatsapp}` : ''}
                      </p>
                      <p className="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-stone-500">
                        <span>Plan {t.plan_name ?? '—'}</span>
                        <span>Desired {t.desired_plan_slug ?? '—'}</span>
                        <span>{t.tier_unlocked ? 'Tiers unlocked' : 'Tiers locked'}</span>
                        <span>Trial {formatDate(t.trial_ends_at)}</span>
                        <span>Seen {formatLastSeen(t.admin_last_seen_at)}</span>
                        {typeof t.pwa_subscribers === 'number' ? (
                          <span>{t.pwa_subscribers} PWA</span>
                        ) : null}
                      </p>
                    </div>

                    <div className="flex shrink-0 items-center gap-2">
                      <a
                        href={`/book/${t.slug}`}
                        target="_blank"
                        rel="noreferrer"
                        className="rounded-lg border border-amber-500/30 px-2.5 py-1.5 text-xs font-semibold text-amber-200 hover:bg-amber-500/10"
                      >
                        Book
                      </a>
                      <div
                        className="relative"
                        ref={menuOpenId === t.id ? menuRef : undefined}
                      >
                        <button
                          type="button"
                          aria-label={`Actions for ${t.trading_name || t.name}`}
                          aria-expanded={menuOpenId === t.id}
                          className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-white/15 text-stone-200 hover:bg-white/10"
                          onClick={() =>
                            setMenuOpenId((current) => (current === t.id ? null : t.id))
                          }
                        >
                          <MoreIcon className="h-4 w-4" />
                        </button>
                        {menuOpenId === t.id ? (
                          <div className="absolute right-0 z-20 mt-1 w-52 overflow-hidden rounded-xl border border-white/15 bg-[#1c1917] py-1 shadow-xl">
                            <button
                              type="button"
                              className={menuBtnClass}
                              disabled={pokingId === t.id}
                              onClick={() => void handlePoke(t)}
                            >
                              {pokingId === t.id ? 'Poking…' : 'Poke'}
                            </button>
                            <div className="border-t border-white/10 px-3 py-2">
                              <label className="mb-1 block text-[11px] text-stone-400">
                                Unlock plan
                              </label>
                              <select
                                value={unlockPlan}
                                onChange={(e) =>
                                  setUnlockPlanByTenant((prev) => ({
                                    ...prev,
                                    [t.id]: e.target.value as 'basic' | 'pro' | 'diamond',
                                  }))
                                }
                                className="mb-2 w-full rounded-md border border-white/15 bg-stone-950 px-2 py-1.5 text-xs text-white"
                              >
                                <option value="basic">Basic</option>
                                <option value="pro">Pro</option>
                                <option value="diamond">Diamond</option>
                              </select>
                              <button
                                type="button"
                                className="w-full rounded-md bg-[var(--platform-accent)] px-2 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                                disabled={unlockingId === t.id}
                                onClick={() => void handleUnlock(t)}
                              >
                                {unlockingId === t.id ? 'Unlocking…' : 'Unlock tiers'}
                              </button>
                            </div>
                            <button
                              type="button"
                              className={menuBtnClass}
                              onClick={() => {
                                setModulesTenant(t);
                                setMenuOpenId(null);
                              }}
                            >
                              Modules
                            </button>
                            <button
                              type="button"
                              className={menuBtnClass}
                              onClick={() => {
                                setPolicyTenant(t);
                                setMenuOpenId(null);
                              }}
                            >
                              Booking policy
                            </button>
                            {canChangeEmail ? (
                              <button
                                type="button"
                                className={menuBtnClass}
                                onClick={() => {
                                  setEmailTenant(t);
                                  setEmailDraft(ownerEmail || '');
                                  setEmailError(null);
                                  setMenuOpenId(null);
                                }}
                              >
                                Change email
                              </button>
                            ) : null}
                            {canPurge ? (
                              <button
                                type="button"
                                className="block w-full px-3 py-2 text-left text-sm text-red-300 hover:bg-red-500/10"
                                onClick={() => {
                                  setPurgeTenant(t);
                                  setPurgeSlugConfirm('');
                                  setError(null);
                                  setMenuOpenId(null);
                                }}
                              >
                                Delete forever
                              </button>
                            ) : null}
                          </div>
                        ) : null}
                      </div>
                    </div>
                  </div>
                </article>
              );
            })
          )}
        </div>
      ) : null}

      {modulesTenant ? (
        <TenantModulesPanel
          tenantId={modulesTenant.id}
          tenantName={modulesTenant.trading_name || modulesTenant.name}
          onClose={() => setModulesTenant(null)}
        />
      ) : null}

      {policyTenant ? (
        <TenantBookingPolicyPanel
          tenantId={policyTenant.id}
          tenantName={policyTenant.trading_name || policyTenant.name}
          onClose={() => setPolicyTenant(null)}
        />
      ) : null}

      {purgeTenant ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-950/80 p-4 backdrop-blur-sm">
          <div className="w-full max-w-md rounded-2xl border border-red-500/40 bg-[#1c1917] p-5 text-stone-100 shadow-2xl">
            <h2 className="text-lg font-semibold text-white">Permanently delete tenant</h2>
            <p className="mt-2 text-sm text-stone-300">
              This permanently deletes{' '}
              <span className="font-semibold text-white">
                {purgeTenant.trading_name || purgeTenant.name}
              </span>{' '}
              (
              <span className="font-mono text-xs text-amber-200">{purgeTenant.slug}</span>
              ) and all related salon data. This cannot be undone.
            </p>
            <form onSubmit={(e) => void handlePurge(e)} className="mt-4 space-y-3">
              <label className="block text-sm">
                <span className="mb-1 block text-stone-300">
                  Type slug “{purgeTenant.slug}” to confirm
                </span>
                <input
                  value={purgeSlugConfirm}
                  onChange={(e) => setPurgeSlugConfirm(e.target.value)}
                  className={fieldClass}
                  placeholder={purgeTenant.slug}
                  autoComplete="off"
                  autoFocus
                />
              </label>
              <div className="flex flex-wrap gap-2 pt-1">
                <button
                  type="submit"
                  disabled={
                    purging ||
                    purgeSlugConfirm.trim().toLowerCase() !== purgeTenant.slug.toLowerCase()
                  }
                  className="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-500 disabled:opacity-50"
                >
                  {purging ? 'Deleting…' : 'Delete forever'}
                </button>
                <button
                  type="button"
                  disabled={purging}
                  className="rounded-lg border border-white/25 bg-transparent px-3 py-2 text-sm font-semibold text-stone-100 hover:bg-white/10 disabled:opacity-50"
                  onClick={() => {
                    setPurgeTenant(null);
                    setPurgeSlugConfirm('');
                  }}
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}

      {emailTenant ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-950/80 p-4 backdrop-blur-sm">
          <div className="w-full max-w-md rounded-2xl border border-sky-500/40 bg-[#1c1917] p-5 text-stone-100 shadow-2xl">
            <h2 className="text-lg font-semibold text-white">Change tenant email</h2>
            <p className="mt-2 text-sm text-stone-300">
              Updates the owner login email for{' '}
              <span className="font-semibold text-white">
                {emailTenant.trading_name || emailTenant.name}
              </span>{' '}
              and the salon contact email.
            </p>
            {emailError ? (
              <div className="mt-3 rounded-lg border border-red-400/40 bg-red-500/15 px-3 py-2 text-sm text-red-100">
                {emailError}
              </div>
            ) : null}
            <form onSubmit={(e) => void handleChangeEmail(e)} className="mt-4 space-y-3">
              <label className="block text-sm">
                <span className="mb-1 block text-stone-300">Owner email</span>
                <input
                  type="email"
                  required
                  value={emailDraft}
                  onChange={(e) => setEmailDraft(e.target.value)}
                  className={fieldClass}
                  placeholder="owner@salon.com"
                  autoComplete="off"
                  autoFocus
                />
              </label>
              <div className="flex flex-wrap gap-2 pt-1">
                <button
                  type="submit"
                  disabled={savingEmail || !emailDraft.trim()}
                  className="rounded-lg bg-[var(--platform-accent)] px-3 py-2 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-50"
                >
                  {savingEmail ? 'Saving…' : 'Save email'}
                </button>
                <button
                  type="button"
                  disabled={savingEmail}
                  className="rounded-lg border border-white/25 bg-transparent px-3 py-2 text-sm font-semibold text-stone-100 hover:bg-white/10 disabled:opacity-50"
                  onClick={() => {
                    setEmailTenant(null);
                    setEmailDraft('');
                    setEmailError(null);
                  }}
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </div>
  );
}
