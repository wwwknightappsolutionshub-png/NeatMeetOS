'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminMarketingShell } from '@/components/admin/marketing/AdminMarketingShell';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import {
  ACTIVE_TRIGGER_TYPES,
  CAMPAIGN_STATUSES,
  channelLabel,
  humanizeToken,
  MARKETING_CHANNELS,
  statusTone,
  triggerLabel,
  type MarketingCampaign,
  type MarketingTemplate,
} from '@/lib/marketing-types';
import {
  createMarketingCampaign,
  fetchMarketingCampaigns,
  fetchMarketingTemplates,
  updateMarketingCampaignStatus,
} from '@/services/marketing.service';

interface FormState {
  name: string;
  campaign_type: string;
  channel: string;
  trigger_type: string;
  template_id: string;
  audience_name: string;
  notes: string;
}

const emptyForm: FormState = {
  name: '',
  campaign_type: 'broadcast',
  channel: 'email',
  trigger_type: 'booking_reminder',
  template_id: '',
  audience_name: '',
  notes: '',
};

export default function MarketingCampaignsPage() {
  const [campaigns, setCampaigns] = useState<MarketingCampaign[]>([]);
  const [templates, setTemplates] = useState<MarketingTemplate[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState<FormState>(emptyForm);

  const load = useCallback(() => {
    setLoading(true);
    fetchMarketingCampaigns()
      .then(setCampaigns)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load campaigns'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
    fetchMarketingTemplates({ is_active: true })
      .then(setTemplates)
      .catch(() => setTemplates([]));
  }, [load]);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    try {
      const isAutomation = form.campaign_type === 'automation';
      await createMarketingCampaign({
        name: form.name,
        campaign_type: form.campaign_type,
        channel: form.channel,
        trigger_type: isAutomation ? form.trigger_type : null,
        template_id: form.template_id || null,
        audience_name: form.audience_name || null,
        notes: form.notes || null,
      });
      setForm(emptyForm);
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Create failed');
    } finally {
      setSaving(false);
    }
  }

  async function changeStatus(id: string, status: string) {
    setError(null);
    try {
      await updateMarketingCampaignStatus(id, status);
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Status update failed');
    }
  }

  const channelTemplates = templates.filter((t) => String(t.channel) === form.channel);

  return (
    <AdminMarketingShell title="Campaigns">
      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}

      <Card title="New campaign">
        <form onSubmit={handleSubmit} className="grid gap-3 md:grid-cols-2">
          <Field label="Name">
            <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
          </Field>
          <Field label="Type">
            <select className={inputClass} value={form.campaign_type} onChange={(e) => setForm({ ...form, campaign_type: e.target.value })}>
              <option value="broadcast">Broadcast (one-off)</option>
              <option value="automation">Automation (triggered)</option>
            </select>
          </Field>
          <Field label="Channel">
            <select className={inputClass} value={form.channel} onChange={(e) => setForm({ ...form, channel: e.target.value, template_id: '' })}>
              {MARKETING_CHANNELS.map((c) => (
                <option key={c} value={c}>
                  {channelLabel(c)}
                </option>
              ))}
            </select>
          </Field>
          {form.campaign_type === 'automation' ? (
            <Field label="Trigger">
              <select className={inputClass} value={form.trigger_type} onChange={(e) => setForm({ ...form, trigger_type: e.target.value })}>
                {ACTIVE_TRIGGER_TYPES.map((t) => (
                  <option key={t} value={t}>
                    {triggerLabel(t)}
                  </option>
                ))}
              </select>
            </Field>
          ) : (
            <Field label="Audience name (label)">
              <input className={inputClass} value={form.audience_name} onChange={(e) => setForm({ ...form, audience_name: e.target.value })} />
            </Field>
          )}
          <Field label="Template">
            <select className={inputClass} value={form.template_id} onChange={(e) => setForm({ ...form, template_id: e.target.value })}>
              <option value="">None</option>
              {channelTemplates.map((t) => (
                <option key={t.id} value={t.id}>
                  {t.name}
                </option>
              ))}
            </select>
          </Field>
          <div className="md:col-span-2">
            <Field label="Notes">
              <textarea className={`${inputClass} min-h-20`} value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} />
            </Field>
          </div>
          <div className="md:col-span-2">
            <Button type="submit" disabled={saving}>
              {saving ? 'Saving…' : 'Create campaign'}
            </Button>
          </div>
        </form>
      </Card>

      <div className="mt-6">
        {loading ? (
          <LoadingState />
        ) : (
          <Card title="Campaigns & automations">
            {campaigns.length === 0 ? (
              <p className="text-sm text-zinc-500">No campaigns yet.</p>
            ) : (
              <table className="w-full text-left text-sm">
                <thead>
                  <tr className="border-b text-zinc-500">
                    <th className="py-2">Name</th>
                    <th>Type</th>
                    <th>Channel</th>
                    <th>Trigger</th>
                    <th>Status</th>
                    <th>Last run</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {campaigns.map((campaign) => (
                    <tr key={campaign.id} className="border-b border-zinc-100">
                      <td className="py-2 font-medium">{campaign.name}</td>
                      <td className="text-zinc-600">{humanizeToken(String(campaign.campaign_type))}</td>
                      <td>{channelLabel(String(campaign.channel))}</td>
                      <td className="text-zinc-600">{campaign.trigger_type ? triggerLabel(campaign.trigger_type) : '—'}</td>
                      <td>
                        <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusTone(campaign.status)}`}>
                          {humanizeToken(campaign.status)}
                        </span>
                      </td>
                      <td className="text-zinc-500">{campaign.last_run_at ? new Date(campaign.last_run_at).toLocaleDateString('en-GB') : '—'}</td>
                      <td>
                        <select
                          className="rounded-md border border-zinc-300 px-2 py-1 text-xs"
                          value=""
                          onChange={(e) => {
                            if (e.target.value) changeStatus(campaign.id, e.target.value);
                          }}
                        >
                          <option value="">Set status…</option>
                          {CAMPAIGN_STATUSES.filter((s) => s !== campaign.status).map((s) => (
                            <option key={s} value={s}>
                              {humanizeToken(s)}
                            </option>
                          ))}
                        </select>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </Card>
        )}
      </div>
    </AdminMarketingShell>
  );
}
