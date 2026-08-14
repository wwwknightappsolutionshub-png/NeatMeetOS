'use client';

import { useEffect, useState } from 'react';
import { AdminMembershipsShell } from '@/components/admin/memberships/AdminMembershipsShell';
import { ErrorAlert, Field, inputClass } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import {
  fetchLoyaltyRedemptionSettings,
  updateLoyaltyRedemptionSettings,
  type LoyaltyRedemptionSettings,
} from '@/services/memberships.service';

export default function LoyaltyRedemptionSettingsPage() {
  const [settings, setSettings] = useState<LoyaltyRedemptionSettings | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    fetchLoyaltyRedemptionSettings()
      .then(setSettings)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load settings'));
  }, []);

  if (!settings) {
    return (
      <AdminMembershipsShell title="Settings">
        <p className="text-sm text-zinc-500">{error ?? 'Loading…'}</p>
      </AdminMembershipsShell>
    );
  }

  return (
    <AdminMembershipsShell title="Settings">
      {error ? <ErrorAlert message={error} /> : null}
      <Card title="How points work at POS">
        <form
          className="grid max-w-md gap-4"
          onSubmit={async (e) => {
            e.preventDefault();
            setSaving(true);
            setError(null);
            try {
              const updated = await updateLoyaltyRedemptionSettings(settings);
              setSettings(updated);
            } catch (err) {
              setError(err instanceof Error ? err.message : 'Save failed');
            } finally {
              setSaving(false);
            }
          }}
        >
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={settings.is_loyalty_redemption_enabled}
              onChange={(e) => setSettings({ ...settings, is_loyalty_redemption_enabled: e.target.checked })}
            />
            Allow clients to spend loyalty points at POS
          </label>
          <Field label="Points per block">
            <input
              type="number"
              min={1}
              className={inputClass}
              value={settings.points_per_redemption_block}
              onChange={(e) => setSettings({ ...settings, points_per_redemption_block: parseInt(e.target.value, 10) })}
            />
          </Field>
          <Field label="Value per block (Pound)">
            <input
              type="number"
              min={0.01}
              step={0.01}
              className={inputClass}
              value={(settings.value_cents_per_block / 100).toFixed(2)}
              onChange={(e) =>
                setSettings({
                  ...settings,
                  value_cents_per_block: Math.round(Number.parseFloat(e.target.value || '0') * 100) || 0,
                })
              }
            />
          </Field>
          <p className="text-xs text-zinc-500">
            Example: {settings.points_per_redemption_block} points = £{(settings.value_cents_per_block / 100).toFixed(2)}
          </p>
          <Field label="Points when a new client joins (CRM signup)">
            <input
              type="number"
              min={0}
              className={inputClass}
              value={settings.crm_join_signup_points ?? 300}
              onChange={(e) =>
                setSettings({
                  ...settings,
                  crm_join_signup_points: parseInt(e.target.value, 10) || 0,
                })
              }
            />
          </Field>
          <p className="text-xs text-zinc-500">
            Awarded once when a new client joins via the CRM signup form. Default is 300.
          </p>
          <Button type="submit" disabled={saving}>
            {saving ? 'Saving…' : 'Save settings'}
          </Button>
        </form>
      </Card>
    </AdminMembershipsShell>
  );
}
