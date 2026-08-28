'use client';

import { useCallback, useEffect, useState } from 'react';
import {
  PlatformButton,
  PlatformCard,
  PlatformErrorAlert,
  PlatformField,
  PlatformLoadingState,
  PlatformPage,
  PlatformPageIntro,
  PlatformSuccessAlert,
  platformInputClass,
} from '@/components/platform/ui';
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
    <PlatformPage>
      <PlatformPageIntro
        title="Modules & plans"
        description="Control which product modules each subscription tier includes. Per-tenant overrides live on the Tenants page."
      />

      {error ? <PlatformErrorAlert message={error} /> : null}
      {message ? <PlatformSuccessAlert message={message} /> : null}
      {loading && !data ? <PlatformLoadingState label="Loading module catalogue…" /> : null}

      {data
        ? data.plans.map((plan) => {
            const draft = drafts[plan.id] ?? plan;
            return (
              <PlatformCard key={plan.id} title={`${plan.name} · ${plan.slug}`}>
                <p className="mb-4 text-sm text-[var(--platform-muted)]">{plan.description}</p>
                <div className="grid gap-2 sm:grid-cols-2">
                  {data.catalogue.map((mod) => (
                    <label
                      key={mod.key}
                      className="flex cursor-pointer items-start gap-3 rounded-md border border-[var(--platform-line-subtle)] bg-[var(--platform-surface-elevated)] px-3 py-2.5 transition hover:border-[var(--platform-line)]"
                    >
                      <input
                        type="checkbox"
                        className="mt-1 accent-[var(--platform-accent)]"
                        checked={Boolean(draft.features[mod.key])}
                        onChange={(e) => setFeature(plan.id, mod.key, e.target.checked)}
                      />
                      <span>
                        <span className="block text-sm font-semibold text-white">{mod.label}</span>
                        <span className="block text-xs text-[var(--platform-muted)]">
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
                    <PlatformField key={key} label={label}>
                      <input
                        type="number"
                        min={1}
                        value={Number(draft.limits[key] ?? 1)}
                        onChange={(e) => setLimit(plan.id, key, Number(e.target.value) || 1)}
                        className={platformInputClass}
                      />
                    </PlatformField>
                  ))}
                </div>

                <div className="mt-4">
                  <PlatformButton
                    disabled={savingSlug === plan.slug}
                    onClick={() => void savePlan(plan.id)}
                  >
                    {savingSlug === plan.slug ? 'Saving…' : `Save ${plan.name}`}
                  </PlatformButton>
                </div>
              </PlatformCard>
            );
          })
        : null}
    </PlatformPage>
  );
}
