'use client';

import { useCallback, useEffect, useState } from 'react';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { PlatformModulesIndex, PlatformPlanModules } from '@/lib/types';
import {
  fetchPlatformModules,
  updatePlanModules,
} from '@/services/platform.service';

export default function PlatformModulesPage() {
  const [data, setData] = useState<PlatformModulesIndex | null>(null);
  const [drafts, setDrafts] = useState<Record<string, PlatformPlanModules>>({});
  const [loading, setLoading] = useState(true);
  const [savingSlug, setSavingSlug] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetchPlatformModules();
      setData(res);
      const next: Record<string, PlatformPlanModules> = {};
      for (const plan of res.plans) {
        next[plan.id] = {
          ...plan,
          features: { ...plan.features },
          limits: { ...plan.limits },
        };
      }
      setDrafts(next);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load modules');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  function setFeature(planId: string, key: string, enabled: boolean) {
    setDrafts((prev) => {
      const plan = prev[planId];
      if (!plan) return prev;
      return {
        ...prev,
        [planId]: {
          ...plan,
          features: { ...plan.features, [key]: enabled },
        },
      };
    });
  }

  function setLimit(planId: string, key: string, value: number) {
    setDrafts((prev) => {
      const plan = prev[planId];
      if (!plan) return prev;
      return {
        ...prev,
        [planId]: {
          ...plan,
          limits: { ...plan.limits, [key]: value },
        },
      };
    });
  }

  async function savePlan(planId: string) {
    const draft = drafts[planId];
    if (!draft) return;
    setSavingSlug(draft.slug);
    setError(null);
    setMessage(null);
    try {
      await updatePlanModules(planId, {
        features: draft.features,
        limits: {
          max_locations: Number(draft.limits.max_locations ?? 1),
          max_staff: Number(draft.limits.max_staff ?? 5),
          max_workspaces: Number(draft.limits.max_workspaces ?? 10),
        },
      });
      setMessage(`${draft.name} modules saved.`);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed');
    } finally {
      setSavingSlug(null);
    }
  }

  return (
    <div className="mx-auto grid max-w-6xl gap-5">
      <div>
        <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-400/90">
          Platform
        </p>
        <h1 className="mt-1 text-2xl font-semibold tracking-tight text-white">
          Modules &amp; plans
        </h1>
        <p className="mt-1 text-sm text-stone-400">
          Control which product modules each subscription tier includes. Per-tenant
          overrides are available on the Tenants page.
        </p>
      </div>

      {error ? <ErrorAlert message={error} /> : null}
      {message ? (
        <p className="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-200">
          {message}
        </p>
      ) : null}
      {loading && !data ? <LoadingState label="Loading modules…" /> : null}

      {data
        ? data.plans.map((plan) => {
            const draft = drafts[plan.id] ?? plan;
            return (
              <Card
                key={plan.id}
                className="border-white/10 bg-white/5 text-stone-100"
                title={`${plan.name} (${plan.slug})`}
              >
                <p className="mb-4 text-sm text-stone-400">{plan.description}</p>
                <div className="grid gap-2 sm:grid-cols-2">
                  {data.catalogue.map((mod) => (
                    <label
                      key={mod.key}
                      className="flex cursor-pointer items-start gap-3 rounded-lg border border-white/10 bg-stone-950/30 px-3 py-2.5"
                    >
                      <input
                        type="checkbox"
                        className="mt-1"
                        checked={Boolean(draft.features[mod.key])}
                        onChange={(e) =>
                          setFeature(plan.id, mod.key, e.target.checked)
                        }
                      />
                      <span>
                        <span className="block text-sm font-semibold text-white">
                          {mod.label}
                        </span>
                        <span className="block text-xs text-stone-400">
                          {mod.description}
                        </span>
                      </span>
                    </label>
                  ))}
                </div>

                <div className="mt-4 grid gap-3 sm:grid-cols-3">
                  {(
                    [
                      ['max_locations', 'Max locations'],
                      ['max_staff', 'Max staff'],
                      ['max_workspaces', 'Max workspaces'],
                    ] as const
                  ).map(([key, label]) => (
                    <label key={key} className="block text-sm">
                      <span className="mb-1 block text-stone-400">{label}</span>
                      <input
                        type="number"
                        min={1}
                        value={Number(draft.limits[key] ?? 1)}
                        onChange={(e) =>
                          setLimit(plan.id, key, Number(e.target.value) || 1)
                        }
                        className="w-full rounded-lg border border-white/15 bg-stone-950/40 px-3 py-2 text-sm text-white outline-none focus:border-amber-500"
                      />
                    </label>
                  ))}
                </div>

                <div className="mt-4">
                  <Button
                    type="button"
                    className="!bg-[var(--platform-accent)]"
                    disabled={savingSlug === plan.slug}
                    onClick={() => void savePlan(plan.id)}
                  >
                    {savingSlug === plan.slug ? 'Saving…' : `Save ${plan.name}`}
                  </Button>
                </div>
              </Card>
            );
          })
        : null}
    </div>
  );
}
