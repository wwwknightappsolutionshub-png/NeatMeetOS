'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminMarketingShell } from '@/components/admin/marketing/AdminMarketingShell';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import {
  channelLabel,
  humanizeToken,
  MARKETING_CHANNELS,
  type AudiencePreviewResult,
  type AudienceRules,
  type MarketingAudience,
} from '@/lib/marketing-types';
import {
  archiveMarketingAudience,
  createMarketingAudience,
  fetchMarketingAudiences,
  previewMarketingAudience,
  updateMarketingAudience,
} from '@/services/marketing.service';

interface FormState {
  name: string;
  description: string;
  location_ids: string;
  client_tag_ids: string;
  client_status: string;
  requires_email_consent: boolean;
  requires_sms_consent: boolean;
  has_future_booking: boolean;
  last_visit_before: string;
  last_visit_after: string;
}

const emptyForm: FormState = {
  name: '',
  description: '',
  location_ids: '',
  client_tag_ids: '',
  client_status: '',
  requires_email_consent: false,
  requires_sms_consent: false,
  has_future_booking: false,
  last_visit_before: '',
  last_visit_after: '',
};

function splitCsv(value: string): string[] {
  return value
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean);
}

function buildRules(form: FormState): AudienceRules {
  const rules: AudienceRules = {};
  const locations = splitCsv(form.location_ids);
  const tags = splitCsv(form.client_tag_ids);
  if (locations.length) rules.location_ids = locations;
  if (tags.length) rules.client_tag_ids = tags;
  if (form.client_status) rules.client_status = form.client_status;
  if (form.requires_email_consent) rules.requires_email_consent = true;
  if (form.requires_sms_consent) rules.requires_sms_consent = true;
  if (form.has_future_booking) rules.has_future_booking = true;
  if (form.last_visit_before) rules.last_visit_before = form.last_visit_before;
  if (form.last_visit_after) rules.last_visit_after = form.last_visit_after;
  return rules;
}

function rulesToForm(audience: MarketingAudience): FormState {
  const rules = audience.rules ?? {};
  return {
    name: audience.name,
    description: audience.description ?? '',
    location_ids: (rules.location_ids ?? []).join(', '),
    client_tag_ids: (rules.client_tag_ids ?? []).join(', '),
    client_status: rules.client_status ?? '',
    requires_email_consent: Boolean(rules.requires_email_consent),
    requires_sms_consent: Boolean(rules.requires_sms_consent),
    has_future_booking: Boolean(rules.has_future_booking),
    last_visit_before: rules.last_visit_before ?? '',
    last_visit_after: rules.last_visit_after ?? '',
  };
}

export default function MarketingAudiencesPage() {
  const [audiences, setAudiences] = useState<MarketingAudience[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [previewChannel, setPreviewChannel] = useState('email');
  const [preview, setPreview] = useState<AudiencePreviewResult | null>(null);
  const [previewError, setPreviewError] = useState<string | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    fetchMarketingAudiences()
      .then(setAudiences)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load audiences'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  function resetForm() {
    setEditingId(null);
    setForm(emptyForm);
    setPreview(null);
    setPreviewError(null);
  }

  function startEdit(audience: MarketingAudience) {
    setEditingId(audience.id);
    setForm(rulesToForm(audience));
    setPreview(null);
    setPreviewError(null);
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    try {
      const rules = buildRules(form);
      if (editingId) {
        await updateMarketingAudience(editingId, {
          name: form.name,
          description: form.description || undefined,
          rules,
        });
      } else {
        await createMarketingAudience({
          name: form.name,
          description: form.description || undefined,
          rules,
        });
      }
      resetForm();
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  async function handlePreview() {
    setPreviewError(null);
    try {
      const result = await previewMarketingAudience({
        rules: buildRules(form),
        channel: previewChannel,
      });
      setPreview(result);
    } catch (err) {
      setPreviewError(err instanceof Error ? err.message : 'Preview failed');
    }
  }

  const eligibleCount = preview?.counts?.eligible ?? preview?.eligible_sample.length ?? 0;
  const skippedCount = preview?.counts?.skipped ?? preview?.skipped_sample.length ?? 0;

  return (
    <AdminMarketingShell title="Audiences">
      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}

      <div className="grid gap-4 lg:grid-cols-2">
        <Card title={editingId ? 'Edit audience' : 'New audience'}>
          <form onSubmit={handleSubmit} className="grid gap-3">
            <Field label="Name">
              <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
            </Field>
            <Field label="Description">
              <input className={inputClass} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
            </Field>
            <Field label="Location IDs (comma separated)">
              <input className={inputClass} value={form.location_ids} onChange={(e) => setForm({ ...form, location_ids: e.target.value })} />
            </Field>
            <Field label="Client tag IDs (comma separated)">
              <input className={inputClass} value={form.client_tag_ids} onChange={(e) => setForm({ ...form, client_tag_ids: e.target.value })} />
            </Field>
            <Field label="Client status">
              <select className={inputClass} value={form.client_status} onChange={(e) => setForm({ ...form, client_status: e.target.value })}>
                <option value="">Any</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="lead">Lead</option>
              </select>
            </Field>
            <div className="grid gap-2 rounded-md border border-zinc-200 p-3 text-sm">
              <label className="flex items-center gap-2">
                <input type="checkbox" checked={form.requires_email_consent} onChange={(e) => setForm({ ...form, requires_email_consent: e.target.checked })} />
                Requires email marketing consent
              </label>
              <label className="flex items-center gap-2">
                <input type="checkbox" checked={form.requires_sms_consent} onChange={(e) => setForm({ ...form, requires_sms_consent: e.target.checked })} />
                Requires SMS marketing consent
              </label>
              <label className="flex items-center gap-2">
                <input type="checkbox" checked={form.has_future_booking} onChange={(e) => setForm({ ...form, has_future_booking: e.target.checked })} />
                Has a future booking
              </label>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              <Field label="Last visit after">
                <input type="date" className={inputClass} value={form.last_visit_after} onChange={(e) => setForm({ ...form, last_visit_after: e.target.value })} />
              </Field>
              <Field label="Last visit before">
                <input type="date" className={inputClass} value={form.last_visit_before} onChange={(e) => setForm({ ...form, last_visit_before: e.target.value })} />
              </Field>
            </div>
            <div className="flex flex-wrap gap-2">
              <Button type="submit" disabled={saving}>
                {saving ? 'Saving…' : editingId ? 'Update audience' : 'Create audience'}
              </Button>
              {editingId ? (
                <Button type="button" variant="secondary" onClick={resetForm}>
                  Cancel edit
                </Button>
              ) : null}
            </div>
          </form>
        </Card>

        <Card title="Preview reach">
          <div className="flex items-end gap-3">
            <Field label="Channel">
              <select className={inputClass} value={previewChannel} onChange={(e) => setPreviewChannel(e.target.value)}>
                {MARKETING_CHANNELS.map((c) => (
                  <option key={c} value={c}>
                    {channelLabel(c)}
                  </option>
                ))}
              </select>
            </Field>
            <Button type="button" variant="secondary" onClick={handlePreview}>
              Preview count
            </Button>
          </div>
          {previewError ? <p className="mt-3 text-sm text-red-600">{previewError}</p> : null}
          {preview ? (
            <div className="mt-4 space-y-3">
              <div className="flex gap-4">
                <div className="rounded-md bg-emerald-50 px-3 py-2 text-sm">
                  <span className="block text-xs uppercase text-emerald-700">Eligible</span>
                  <span className="text-lg font-semibold text-emerald-800">{eligibleCount}</span>
                </div>
                <div className="rounded-md bg-zinc-100 px-3 py-2 text-sm">
                  <span className="block text-xs uppercase text-zinc-500">Skipped</span>
                  <span className="text-lg font-semibold text-zinc-700">{skippedCount}</span>
                </div>
              </div>
              {preview.eligible_sample.length > 0 ? (
                <div>
                  <p className="mb-1 text-xs font-medium uppercase tracking-wide text-zinc-500">Eligible sample</p>
                  <ul className="space-y-1 text-sm">
                    {preview.eligible_sample.map((client) => (
                      <li key={client.client_id} className="flex justify-between">
                        <span>{client.client_name}</span>
                        <span className="text-zinc-500">{client.recipient_address ?? '—'}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              ) : null}
              {preview.skipped_sample.length > 0 ? (
                <div>
                  <p className="mb-1 text-xs font-medium uppercase tracking-wide text-zinc-500">Skipped reasons</p>
                  <ul className="space-y-1 text-sm">
                    {preview.skipped_sample.map((skip, index) => (
                      <li key={`${skip.client_id}-${index}`} className="flex justify-between">
                        <span>{skip.client_name ?? skip.client_id}</span>
                        <span className="text-red-600">{humanizeToken(skip.reason)}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              ) : null}
            </div>
          ) : (
            <p className="mt-3 text-sm text-zinc-500">Set rules and preview to estimate reach without sending anything.</p>
          )}
        </Card>
      </div>

      <div className="mt-6">
        {loading ? (
          <LoadingState />
        ) : (
          <Card title="Saved audiences">
            {audiences.length === 0 ? (
              <p className="text-sm text-zinc-500">No audiences yet.</p>
            ) : (
              <table className="w-full text-left text-sm">
                <thead>
                  <tr className="border-b text-zinc-500">
                    <th className="py-2">Name</th>
                    <th>Rules</th>
                    <th>Status</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {audiences.map((audience) => (
                    <tr key={audience.id} className="border-b border-zinc-100">
                      <td className="py-2 font-medium">{audience.name}</td>
                      <td className="text-zinc-600">{Object.keys(audience.rules ?? {}).length} rule(s)</td>
                      <td>{audience.is_active ? 'Active' : 'Archived'}</td>
                      <td className="space-x-3 whitespace-nowrap">
                        <button type="button" className="text-xs text-zinc-600 underline" onClick={() => startEdit(audience)}>
                          Edit
                        </button>
                        {audience.is_active ? (
                          <button
                            type="button"
                            className="text-xs text-zinc-600 underline"
                            onClick={() =>
                              archiveMarketingAudience(audience.id)
                                .then(load)
                                .catch((e) => setError(e instanceof Error ? e.message : 'Archive failed'))
                            }
                          >
                            Archive
                          </button>
                        ) : null}
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
