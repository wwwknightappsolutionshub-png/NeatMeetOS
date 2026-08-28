'use client';

import { useEffect, useState } from 'react';
import {
  PlatformButton,
  PlatformErrorAlert,
  PlatformLoadingState,
  PlatformModalBackdrop,
  PlatformModalPanel,
  platformSelectClass,
} from '@/components/platform/ui';
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
    <PlatformModalBackdrop>
      <PlatformModalPanel
        title="Tenant modules"
        subtitle={`${tenantName} · Plan ${state?.plan_slug ?? '…'}`}
        onClose={onClose}
      >
        {error ? <PlatformErrorAlert message={error} /> : null}

        {loading || !state ? (
          <PlatformLoadingState label="Loading module overrides…" />
        ) : (
          <ul className="space-y-2">
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
                  className="rounded-md border border-[var(--platform-line-subtle)] bg-[var(--platform-surface-elevated)] px-3 py-2.5"
                >
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="text-sm font-semibold text-white">{mod.label}</p>
                      <p className="text-xs text-[var(--platform-muted)]">
                        Plan default: {planOn ? 'On' : 'Off'} · Effective:{' '}
                        {effective ? 'On' : 'Off'}
                      </p>
                      {isAiHairstyle ? (
                        <p className="mt-1 text-xs text-[var(--platform-muted)]">
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
                          [mod.key]: v === 'inherit' ? null : v === 'on',
                        }));
                      }}
                      className={`${platformSelectClass} w-auto text-xs`}
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
          <PlatformButton variant="ghost" onClick={onClose}>
            Cancel
          </PlatformButton>
          <PlatformButton disabled={saving || loading} onClick={() => void save()}>
            {saving ? 'Saving…' : 'Save overrides'}
          </PlatformButton>
        </div>
      </PlatformModalPanel>
    </PlatformModalBackdrop>
  );
}
