'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type {
  PlatformUpgradeCampaignSettings,
  PlatformUpgradeTemplate,
} from '@/lib/types';
import {
  fetchUpgradeCampaignSettings,
  fetchUpgradeCampaignTemplates,
  updateUpgradeCampaignSettings,
  updateUpgradeCampaignTemplate,
} from '@/services/platform.service';

const PATH_LABEL: Record<string, string> = {
  basic_to_pro: 'Basic → Pro',
  pro_to_diamond: 'Pro → Diamond',
};

const STEP_LABEL: Record<string, string> = {
  day_3: 'Day 3',
  day_7: 'Day 7',
  day_21: 'Day 21',
};

export default function PlatformUpgradeCampaignsPage() {
  const [settings, setSettings] = useState<PlatformUpgradeCampaignSettings | null>(null);
  const [templates, setTemplates] = useState<PlatformUpgradeTemplate[]>([]);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [draft, setDraft] = useState<PlatformUpgradeTemplate | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [s, t] = await Promise.all([
        fetchUpgradeCampaignSettings(),
        fetchUpgradeCampaignTemplates(),
      ]);
      setSettings(s);
      setTemplates(t);
      const first = t[0] ?? null;
      setSelectedId((prev) => prev ?? first?.id ?? null);
      if (first && !selectedId) setDraft({ ...first });
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load campaign settings');
    } finally {
      setLoading(false);
    }
  }, [selectedId]);

  useEffect(() => {
    void load();
    // eslint-disable-next-line react-hooks/exhaustive-deps -- initial load only
  }, []);

  useEffect(() => {
    const next = templates.find((t) => t.id === selectedId) ?? null;
    setDraft(next ? { ...next } : null);
  }, [selectedId, templates]);

  const grouped = useMemo(() => {
    const map = new Map<string, PlatformUpgradeTemplate[]>();
    for (const t of templates) {
      const key = `${t.path}:${t.step}`;
      const list = map.get(key) ?? [];
      list.push(t);
      map.set(key, list);
    }
    return Array.from(map.entries());
  }, [templates]);

  async function saveSettings() {
    if (!settings) return;
    setSaving(true);
    setError(null);
    setMessage(null);
    try {
      const next = await updateUpgradeCampaignSettings(settings);
      setSettings(next);
      setMessage('Campaign settings saved.');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to save settings');
    } finally {
      setSaving(false);
    }
  }

  async function saveTemplate() {
    if (!draft) return;
    setSaving(true);
    setError(null);
    setMessage(null);
    try {
      const next = await updateUpgradeCampaignTemplate(draft.id, {
        subject: draft.subject,
        headline: draft.headline,
        body_html: draft.body_html,
        body_text: draft.body_text,
        cta_label: draft.cta_label,
        image_path: draft.image_path,
        features: draft.features,
        use_cases: draft.use_cases,
        is_active: draft.is_active,
      });
      setTemplates((prev) => prev.map((t) => (t.id === next.id ? next : t)));
      setDraft(next);
      setMessage('Template saved.');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to save template');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="mx-auto grid max-w-6xl gap-5">
      <div>
        <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-400/90">
          Platform
        </p>
        <h1 className="mt-1 text-2xl font-semibold tracking-tight text-white">
          Upgrade campaigns
        </h1>
        <p className="mt-1 text-sm text-stone-400">
          Day 3 WhatsApp / in-app, day 7 email, and day 21 countdown + 5% claim.
          Edit copy and toggles here.
        </p>
      </div>

      {error ? <ErrorAlert message={error} /> : null}
      {message ? (
        <p className="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-200">
          {message}
        </p>
      ) : null}
      {loading && !settings ? <LoadingState label="Loading campaigns…" /> : null}

      {settings ? (
        <Card className="border-white/10 bg-white/5 p-4 text-stone-100 shadow-none">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 className="text-base font-semibold text-white">Drip settings</h2>
              <p className="text-sm text-stone-400">
                Scheduler runs hourly via platform:dispatch-upgrade-campaigns.
              </p>
            </div>
            <Button type="button" disabled={saving} onClick={() => void saveSettings()}>
              {saving ? 'Saving…' : 'Save settings'}
            </Button>
          </div>
          <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={settings.is_enabled}
                onChange={(e) =>
                  setSettings({ ...settings, is_enabled: e.target.checked })
                }
              />
              Campaign enabled
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={settings.channel_whatsapp}
                onChange={(e) =>
                  setSettings({ ...settings, channel_whatsapp: e.target.checked })
                }
              />
              WhatsApp (day 3)
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={settings.channel_in_app}
                onChange={(e) =>
                  setSettings({ ...settings, channel_in_app: e.target.checked })
                }
              />
              In-app (day 3)
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={settings.channel_email}
                onChange={(e) =>
                  setSettings({ ...settings, channel_email: e.target.checked })
                }
              />
              Email (day 7 / 21)
            </label>
            <label className="flex flex-col gap-1 text-sm sm:col-span-2">
              Day 21 discount %
              <input
                type="number"
                min={1}
                max={50}
                value={settings.discount_percent}
                onChange={(e) =>
                  setSettings({
                    ...settings,
                    discount_percent: Number(e.target.value || 5),
                  })
                }
                className="max-w-[8rem] rounded-lg border border-white/15 bg-stone-950 px-3 py-2 text-white"
              />
            </label>
          </div>
        </Card>
      ) : null}

      <div className="grid gap-4 lg:grid-cols-[240px_minmax(0,1fr)]">
        <Card className="border-white/10 bg-white/5 p-3 text-stone-100 shadow-none">
          <p className="mb-2 px-1 text-xs font-semibold uppercase tracking-[0.14em] text-stone-400">
            Templates
          </p>
          <ul className="space-y-3">
            {grouped.map(([key, items]) => {
              const [path, step] = key.split(':');
              return (
                <li key={key}>
                  <p className="px-1 text-[11px] font-semibold text-amber-300/90">
                    {PATH_LABEL[path] ?? path} · {STEP_LABEL[step] ?? step}
                  </p>
                  <ul className="mt-1 space-y-0.5">
                    {items.map((t) => (
                      <li key={t.id}>
                        <button
                          type="button"
                          onClick={() => setSelectedId(t.id)}
                          className={[
                            'w-full rounded-lg px-2 py-1.5 text-left text-sm',
                            selectedId === t.id
                              ? 'bg-[var(--platform-accent)] font-semibold text-white'
                              : 'text-stone-300 hover:bg-white/10',
                          ].join(' ')}
                        >
                          {t.channel}
                          {!t.is_active ? ' (off)' : ''}
                        </button>
                      </li>
                    ))}
                  </ul>
                </li>
              );
            })}
          </ul>
        </Card>

        {draft ? (
          <Card className="border-white/10 bg-white/5 p-4 text-stone-100 shadow-none">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 className="text-base font-semibold text-white">
                  {PATH_LABEL[draft.path] ?? draft.path} ·{' '}
                  {STEP_LABEL[draft.step] ?? draft.step} · {draft.channel}
                </h2>
                <p className="text-xs text-stone-400">Version {draft.version}</p>
              </div>
              <Button type="button" disabled={saving} onClick={() => void saveTemplate()}>
                {saving ? 'Saving…' : 'Save template'}
              </Button>
            </div>

            <div className="mt-4 grid gap-3">
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={draft.is_active}
                  onChange={(e) => setDraft({ ...draft, is_active: e.target.checked })}
                />
                Active
              </label>
              {draft.channel === 'email' ? (
                <Field
                  label="Subject"
                  value={draft.subject ?? ''}
                  onChange={(v) => setDraft({ ...draft, subject: v })}
                />
              ) : null}
              <Field
                label="Headline"
                value={draft.headline ?? ''}
                onChange={(v) => setDraft({ ...draft, headline: v })}
              />
              <Field
                label="CTA label"
                value={draft.cta_label ?? ''}
                onChange={(v) => setDraft({ ...draft, cta_label: v })}
              />
              <Field
                label="Image path"
                value={draft.image_path ?? ''}
                onChange={(v) => setDraft({ ...draft, image_path: v })}
              />
              {draft.channel === 'email' ? (
                <TextArea
                  label="Body HTML"
                  value={draft.body_html ?? ''}
                  onChange={(v) => setDraft({ ...draft, body_html: v })}
                  rows={8}
                />
              ) : null}
              <TextArea
                label="Body text"
                value={draft.body_text ?? ''}
                onChange={(v) => setDraft({ ...draft, body_text: v })}
                rows={4}
              />
              {draft.image_path ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img
                  src={draft.image_path}
                  alt="Campaign creative"
                  className="mt-1 max-h-56 w-auto rounded-lg border border-white/10"
                />
              ) : null}
              <p className="text-xs text-stone-500">
                Placeholders: {'{{salon_name}}'}, {'{{owner_first_name}}'},{' '}
                {'{{discount_percent}}'}, {'{{trial_ends_at}}'}
              </p>
            </div>
          </Card>
        ) : null}
      </div>
    </div>
  );
}

function Field({
  label,
  value,
  onChange,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
}) {
  return (
    <label className="grid gap-1 text-sm">
      <span className="text-stone-300">{label}</span>
      <input
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="rounded-lg border border-white/15 bg-stone-950 px-3 py-2 text-white"
      />
    </label>
  );
}

function TextArea({
  label,
  value,
  onChange,
  rows,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  rows: number;
}) {
  return (
    <label className="grid gap-1 text-sm">
      <span className="text-stone-300">{label}</span>
      <textarea
        value={value}
        rows={rows}
        onChange={(e) => onChange(e.target.value)}
        className="rounded-lg border border-white/15 bg-stone-950 px-3 py-2 font-mono text-xs text-white"
      />
    </label>
  );
}
