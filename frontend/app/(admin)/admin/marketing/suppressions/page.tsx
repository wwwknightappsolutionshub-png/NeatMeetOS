'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminMarketingShell } from '@/components/admin/marketing/AdminMarketingShell';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import {
  channelLabel,
  formatDateTime,
  humanizeToken,
  MARKETING_CHANNELS,
  SUPPRESSION_REASONS,
  type MarketingContactSuppression,
} from '@/lib/marketing-types';
import {
  createMarketingSuppression,
  deactivateMarketingSuppression,
  fetchMarketingSuppressions,
  reactivateMarketingSuppression,
} from '@/services/marketing.service';

interface FormState {
  channel: string;
  contact_value: string;
  reason: string;
  notes: string;
}

const emptyForm: FormState = {
  channel: 'email',
  contact_value: '',
  reason: 'manual',
  notes: '',
};

export default function MarketingSuppressionsPage() {
  const [suppressions, setSuppressions] = useState<MarketingContactSuppression[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [busy, setBusy] = useState<string | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);

  const load = useCallback(() => {
    setLoading(true);
    fetchMarketingSuppressions()
      .then(setSuppressions)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load suppressions'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    setNotice(null);
    try {
      await createMarketingSuppression({
        channel: form.channel,
        contact_value: form.contact_value,
        reason: form.reason || null,
        notes: form.notes || null,
      });
      setForm(emptyForm);
      setNotice('Suppression created.');
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Create failed');
    } finally {
      setSaving(false);
    }
  }

  async function toggle(suppression: MarketingContactSuppression) {
    setBusy(suppression.id);
    setError(null);
    setNotice(null);
    try {
      if (suppression.is_active) {
        await deactivateMarketingSuppression(suppression.id);
        setNotice('Suppression deactivated.');
      } else {
        await reactivateMarketingSuppression(suppression.id);
        setNotice('Suppression reactivated.');
      }
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Update failed');
    } finally {
      setBusy(null);
    }
  }

  return (
    <AdminMarketingShell title="Suppressions">
      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}
      {notice ? (
        <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{notice}</div>
      ) : null}

      <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <strong>Contact suppressions (Module 10B).</strong> Suppressed contacts are excluded from marketing dispatch
        across all channels.
      </div>

      <Card title="Add manual suppression">
        <form onSubmit={handleSubmit} className="grid gap-3 md:grid-cols-2">
          <Field label="Channel">
            <select className={inputClass} value={form.channel} onChange={(e) => setForm({ ...form, channel: e.target.value })}>
              {MARKETING_CHANNELS.map((c) => (
                <option key={c} value={c}>
                  {channelLabel(c)}
                </option>
              ))}
            </select>
          </Field>
          <Field label="Contact value (email / phone)">
            <input className={inputClass} value={form.contact_value} onChange={(e) => setForm({ ...form, contact_value: e.target.value })} required />
          </Field>
          <Field label="Reason">
            <select className={inputClass} value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })}>
              {SUPPRESSION_REASONS.map((r) => (
                <option key={r} value={r}>
                  {humanizeToken(r)}
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
              {saving ? 'Saving…' : 'Add suppression'}
            </Button>
          </div>
        </form>
      </Card>

      <div className="mt-6">
        {loading ? (
          <LoadingState />
        ) : (
          <Card title="Suppressions">
            {suppressions.length === 0 ? (
              <p className="text-sm text-zinc-500">No suppressions recorded.</p>
            ) : (
              <table className="w-full text-left text-sm">
                <thead>
                  <tr className="border-b text-zinc-500">
                    <th className="py-2">Contact</th>
                    <th>Channel</th>
                    <th>Reason</th>
                    <th>Source</th>
                    <th>Active</th>
                    <th>Created</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {suppressions.map((suppression) => (
                    <tr key={suppression.id} className="border-b border-zinc-100">
                      <td className="py-2 font-medium">{suppression.contact_value}</td>
                      <td>{channelLabel(String(suppression.channel))}</td>
                      <td className="text-zinc-600">{suppression.reason ? humanizeToken(String(suppression.reason)) : '—'}</td>
                      <td className="text-zinc-600">{suppression.source ? humanizeToken(String(suppression.source)) : '—'}</td>
                      <td>
                        <span
                          className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${
                            suppression.is_active ? 'bg-orange-100 text-orange-800' : 'bg-zinc-200 text-zinc-600'
                          }`}
                        >
                          {suppression.is_active ? 'Active' : 'Inactive'}
                        </span>
                      </td>
                      <td className="text-zinc-500">{formatDateTime(suppression.created_at)}</td>
                      <td>
                        <button
                          type="button"
                          className="text-xs text-zinc-900 underline disabled:opacity-50"
                          disabled={busy === suppression.id}
                          onClick={() => toggle(suppression)}
                        >
                          {busy === suppression.id ? '…' : suppression.is_active ? 'Deactivate' : 'Reactivate'}
                        </button>
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
