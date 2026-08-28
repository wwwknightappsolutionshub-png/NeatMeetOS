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
import type { PlatformReferralSettings } from '@/lib/types';
import {
  fetchPlatformReferralSettings,
  updatePlatformReferralSettings,
} from '@/services/platform.service';

const GOAL_LABEL: Record<string, string> = {
  referred_tenant_activated: 'Referred salon activates account',
  referred_tenant_first_paid_period: 'Referred salon completes first paid period',
};

const REWARD_LABEL: Record<string, string> = {
  account_credit_cents: 'Account credit (pence)',
  subscription_days: 'Extra subscription days',
};

export default function PlatformReferralsPage() {
  const [settings, setSettings] = useState<PlatformReferralSettings | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setSettings(await fetchPlatformReferralSettings());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load referral settings');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  async function save() {
    if (!settings) return;
    setSaving(true);
    setError(null);
    setMessage(null);
    try {
      const updated = await updatePlatformReferralSettings({
        enabled: settings.enabled,
        reward_type: settings.reward_type,
        reward_amount: settings.reward_amount,
        qualification_goal: settings.qualification_goal,
        qualification_days: settings.qualification_days,
        share_headline: settings.share_headline,
        share_body: settings.share_body,
      });
      setSettings(updated);
      setMessage('Referral programme settings saved.');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  if (loading) {
    return <PlatformLoadingState label="Loading referral settings…" />;
  }
  if (!settings) return error ? <PlatformErrorAlert message={error} /> : null;

  return (
    <PlatformPage width="2xl">
      <PlatformPageIntro
        title="Refer & get rewarded"
        description="Define the salon-to-salon referral reward and when it qualifies. Tenants share a link from Settings → Refer & reward."
      />

      {error ? <PlatformErrorAlert message={error} /> : null}
      {message ? <PlatformSuccessAlert message={message} /> : null}

      <PlatformCard title="Programme">
        <div className="space-y-4 text-sm">
          <label className="flex items-center gap-2 font-medium text-[var(--platform-label)]">
            <input
              type="checkbox"
              className="h-4 w-4 rounded border-stone-500"
              checked={settings.enabled}
              onChange={(e) => setSettings({ ...settings, enabled: e.target.checked })}
            />
            Enable platform referral programme
          </label>
          <PlatformField label="Reward type">
            <select
              className={platformInputClass}
              value={settings.reward_type}
              onChange={(e) => setSettings({ ...settings, reward_type: e.target.value })}
            >
              {(settings.reward_types ?? Object.keys(REWARD_LABEL)).map((t) => (
                <option key={t} value={t}>
                  {REWARD_LABEL[t] ?? t}
                </option>
              ))}
            </select>
          </PlatformField>
          <PlatformField
            label="Reward amount"
            hint="Pence for account credit, or days for subscription extension."
          >
            <input
              type="number"
              min={1}
              className={platformInputClass}
              value={settings.reward_amount}
              onChange={(e) =>
                setSettings({ ...settings, reward_amount: Number(e.target.value) || 0 })
              }
            />
          </PlatformField>
          <PlatformField label="Qualification goal">
            <select
              className={platformInputClass}
              value={settings.qualification_goal}
              onChange={(e) => setSettings({ ...settings, qualification_goal: e.target.value })}
            >
              {(settings.qualification_goals ?? Object.keys(GOAL_LABEL)).map((g) => (
                <option key={g} value={g}>
                  {GOAL_LABEL[g] ?? g}
                </option>
              ))}
            </select>
          </PlatformField>
          <PlatformField label="Share headline">
            <input
              className={platformInputClass}
              value={settings.share_headline ?? ''}
              onChange={(e) => setSettings({ ...settings, share_headline: e.target.value })}
            />
          </PlatformField>
          <PlatformField label="Share body">
            <textarea
              rows={3}
              className={platformInputClass}
              value={settings.share_body ?? ''}
              onChange={(e) => setSettings({ ...settings, share_body: e.target.value })}
            />
          </PlatformField>
          <PlatformButton disabled={saving} onClick={() => void save()}>
            {saving ? 'Saving…' : 'Save settings'}
          </PlatformButton>
        </div>
      </PlatformCard>
    </PlatformPage>
  );
}
