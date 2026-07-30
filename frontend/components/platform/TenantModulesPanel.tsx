'use client';

import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/Button';
import type { TenantModulesState } from '@/lib/types';
import {
  fetchTenantModules,
  updateTenantModules,
} from '@/services/platform.service';

interface Props {
  tenantId: string;
  tenantName: string;
  onClose: () => void;
}

type OverrideDraft = Record<string, boolean | null>;

export function TenantModulesPanel({ tenantId, tenantName, onClose }: Props) {
  const [state, setState] = useState<TenantModulesState | null>(null);
  const [draft, setDraft] = useState<OverrideDraft>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);
    void fetchTenantModules(tenantId)
      .then((res) => {
        if (cancelled) return;
        setState(res);
        const next: OverrideDraft = {};
        for (const mod of res.catalogue) {
          next[mod.key] = Object.prototype.hasOwnProperty.call(res.overrides, mod.key)
            ? res.overrides[mod.key]
            : null;
        }
        setDraft(next);
      })
      .catch((e) => {
        if (!cancelled) {
          setError(e instanceof Error ? e.message : 'Failed to load modules');
        }
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [tenantId]);

  async function save() {
    setSaving(true);
    setError(null);
    try {
      const res = await updateTenantModules(tenantId, draft);
      setState(res);
      onClose();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-4 sm:items-center">
      <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-white/15 bg-stone-950 p-5 text-stone-100 shadow-xl">
        <div className="flex items-start justify-between gap-3">
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-400/90">
              Tenant modules
            </p>
            <h2 className="mt-1 text-lg font-semibold text-white">{tenantName}</h2>
            <p className="mt-1 text-xs text-stone-400">
              Plan: {state?.plan_slug ?? '…'} — override individual modules or leave
              as “Inherit”.
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg border border-white/15 px-2 py-1 text-sm text-stone-300 hover:bg-white/10"
          >
            Close
          </button>
        </div>

        {error ? (
          <p className="mt-3 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-200">
            {error}
          </p>
        ) : null}

        {loading || !state ? (
          <p className="py-8 text-center text-sm text-stone-400">Loading…</p>
        ) : (
          <ul className="mt-4 space-y-2">
            {state.catalogue.map((mod) => {
              const value = draft[mod.key];
              const planOn = Boolean(state.plan_features[mod.key]);
              const effective = Boolean(state.effective[mod.key]);
              const isAiHairstyle = mod.key === 'ai_hairstyle';
              const trialEnds = state.ai_hairstyle_trial_ends_at
                ? new Date(state.ai_hairstyle_trial_ends_at)
                : null;
              const trialLabel =
                trialEnds && !Number.isNaN(trialEnds.getTime())
                  ? trialEnds.toLocaleDateString(undefined, {
                      year: 'numeric',
                      month: 'short',
                      day: 'numeric',
                    })
                  : null;
              return (
                <li
                  key={mod.key}
                  className="rounded-lg border border-white/10 bg-white/5 px-3 py-2.5"
                >
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="text-sm font-semibold text-white">{mod.label}</p>
                      <p className="text-xs text-stone-400">
                        Plan default: {planOn ? 'On' : 'Off'} · Effective:{' '}
                        {effective ? 'On' : 'Off'}
                      </p>
                      {isAiHairstyle ? (
                        <p className="mt-1 text-xs text-stone-400">
                          {state.ai_hairstyle_eligible
                            ? 'Eligible business type'
                            : 'Not eligible (needs barbershop / barber / boutique / chain / spa)'}
                          {trialLabel ? ` · Trial ends ${trialLabel}` : ' · No trial started'}
                        </p>
                      ) : null}
                    </div>
                    <select
                      value={value === null || value === undefined ? 'inherit' : value ? 'on' : 'off'}
                      onChange={(e) => {
                        const v = e.target.value;
                        setDraft((prev) => ({
                          ...prev,
                          [mod.key]:
                            v === 'inherit' ? null : v === 'on',
                        }));
                      }}
                      className="rounded-lg border border-white/15 bg-stone-950/60 px-2 py-1.5 text-xs text-white outline-none focus:border-amber-500"
                    >
                      <option value="inherit">Inherit</option>
                      <option value="on">Force on</option>
                      <option value="off">Force off</option>
                    </select>
                  </div>
                </li>
              );
            })}
          </ul>
        )}

        <div className="mt-4 flex justify-end gap-2">
          <Button
            type="button"
            className="!bg-transparent !text-stone-200 border border-white/15"
            onClick={onClose}
          >
            Cancel
          </Button>
          <Button
            type="button"
            className="!bg-[var(--platform-accent)]"
            disabled={saving || loading}
            onClick={() => void save()}
          >
            {saving ? 'Saving…' : 'Save overrides'}
          </Button>
        </div>
      </div>
    </div>
  );
}
