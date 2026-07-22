'use client';

import { useEffect, useState } from 'react';
import { AdminMarketingShell } from '@/components/admin/marketing/AdminMarketingShell';
import { ErrorAlert, Field, inputClass } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { MarketingAutomationSettings } from '@/lib/marketing-types';
import { fetchMarketingSettings, updateMarketingSettings } from '@/services/marketing.service';

export default function MarketingSettingsPage() {
  const [settings, setSettings] = useState<MarketingAutomationSettings | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    fetchMarketingSettings()
      .then(setSettings)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load settings'));
  }, []);

  if (!settings) {
    return (
      <AdminMarketingShell title="Automation settings">
        <p className="text-sm text-zinc-500">{error ?? 'Loading…'}</p>
      </AdminMarketingShell>
    );
  }

  function updateNumber(key: keyof MarketingAutomationSettings, value: string) {
    const parsed = parseInt(value, 10);
    setSettings((prev) => (prev ? { ...prev, [key]: Number.isFinite(parsed) ? parsed : 0 } : prev));
  }

  return (
    <AdminMarketingShell title="Automation settings">
      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}
      {notice ? (
        <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{notice}</div>
      ) : null}

      <Card title="Timing & consent">
        <form
          className="grid max-w-xl gap-4"
          onSubmit={async (e) => {
            e.preventDefault();
            setSaving(true);
            setError(null);
            setNotice(null);
            try {
              const updated = await updateMarketingSettings(settings);
              setSettings(updated);
              setNotice('Settings saved.');
            } catch (err) {
              setError(err instanceof Error ? err.message : 'Save failed');
            } finally {
              setSaving(false);
            }
          }}
        >
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Booking reminder — hours before">
              <input
                type="number"
                min={0}
                max={2160}
                className={inputClass}
                value={settings.booking_reminder_hours_before}
                onChange={(e) => updateNumber('booking_reminder_hours_before', e.target.value)}
              />
            </Field>
            <Field label="Review request — delay hours">
              <input
                type="number"
                min={0}
                max={2160}
                className={inputClass}
                value={settings.review_request_delay_hours}
                onChange={(e) => updateNumber('review_request_delay_hours', e.target.value)}
              />
            </Field>
            <Field label="Rebooking window — days">
              <input
                type="number"
                min={0}
                max={365}
                className={inputClass}
                value={settings.rebooking_window_days}
                onChange={(e) => updateNumber('rebooking_window_days', e.target.value)}
              />
            </Field>
            <Field label="Win-back inactivity — days">
              <input
                type="number"
                min={0}
                max={3650}
                className={inputClass}
                value={settings.win_back_inactivity_days}
                onChange={(e) => updateNumber('win_back_inactivity_days', e.target.value)}
              />
            </Field>
          </div>

          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={settings.review_request_enabled}
              onChange={(e) => setSettings({ ...settings, review_request_enabled: e.target.checked })}
            />
            Review requests enabled
          </label>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={settings.auto_pause_on_consent_withdrawal}
              onChange={(e) => setSettings({ ...settings, auto_pause_on_consent_withdrawal: e.target.checked })}
            />
            Auto-pause messaging when a client withdraws consent
          </label>

          <div>
            <Button type="submit" disabled={saving}>
              {saving ? 'Saving…' : 'Save settings'}
            </Button>
          </div>
        </form>
      </Card>

      <p className="mt-6 text-sm text-zinc-500">
        These timings drive automated run generation. Actual delivery is simulated in Module 10A until transport
        providers are configured.
      </p>
    </AdminMarketingShell>
  );
}
