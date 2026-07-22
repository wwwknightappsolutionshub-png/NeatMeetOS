'use client';

import Link from 'next/link';
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
  statusTone,
  triggerLabel,
  WORKFLOW_STATUSES,
  WORKFLOW_TRIGGERS,
  type MarketingTemplate,
  type MarketingWorkflow,
} from '@/lib/marketing-types';
import {
  createMarketingWorkflow,
  fetchMarketingTemplates,
  fetchMarketingWorkflows,
} from '@/services/marketing.service';

interface FormState {
  name: string;
  trigger_type: string;
  channel: string;
  template_id: string;
  status: string;
}

const emptyForm: FormState = {
  name: '',
  trigger_type: 'client_created',
  channel: 'email',
  template_id: '',
  status: 'draft',
};

export default function MarketingWorkflowsPage() {
  const [workflows, setWorkflows] = useState<MarketingWorkflow[]>([]);
  const [templates, setTemplates] = useState<MarketingTemplate[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [filters, setFilters] = useState({ status: '', trigger_type: '', channel: '' });

  const load = useCallback(() => {
    setLoading(true);
    fetchMarketingWorkflows({
      status: filters.status || undefined,
      trigger_type: filters.trigger_type || undefined,
      channel: filters.channel || undefined,
    })
      .then(setWorkflows)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load workflows'))
      .finally(() => setLoading(false));
  }, [filters]);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    fetchMarketingTemplates({ is_active: true })
      .then(setTemplates)
      .catch(() => setTemplates([]));
  }, []);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    try {
      await createMarketingWorkflow({
        name: form.name,
        trigger_type: form.trigger_type,
        channel: form.channel,
        status: form.status,
        template_id: form.template_id || null,
      });
      setForm(emptyForm);
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Create failed');
    } finally {
      setSaving(false);
    }
  }

  const channelTemplates = templates.filter((t) => String(t.channel) === form.channel);

  return (
    <AdminMarketingShell title="Workflows">
      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}

      <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <strong>Automation workflows (Module 10B).</strong> Event-triggered journeys that queue executions and
        generate messages through the simulated provider environment.
      </div>

      <Card title="New workflow">
        <form onSubmit={handleSubmit} className="grid gap-3 md:grid-cols-2">
          <Field label="Name">
            <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
          </Field>
          <Field label="Trigger">
            <select className={inputClass} value={form.trigger_type} onChange={(e) => setForm({ ...form, trigger_type: e.target.value })}>
              {WORKFLOW_TRIGGERS.map((t) => (
                <option key={t} value={t}>
                  {triggerLabel(t)}
                </option>
              ))}
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
          <Field label="Template (optional)">
            <select className={inputClass} value={form.template_id} onChange={(e) => setForm({ ...form, template_id: e.target.value })}>
              <option value="">None</option>
              {channelTemplates.map((t) => (
                <option key={t.id} value={t.id}>
                  {t.name}
                </option>
              ))}
            </select>
          </Field>
          <Field label="Status">
            <select className={inputClass} value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
              {WORKFLOW_STATUSES.map((s) => (
                <option key={s} value={s}>
                  {humanizeToken(s)}
                </option>
              ))}
            </select>
          </Field>
          <div className="md:col-span-2">
            <Button type="submit" disabled={saving}>
              {saving ? 'Saving…' : 'Create workflow'}
            </Button>
          </div>
        </form>
      </Card>

      <div className="mt-6">
        <Card title="Filters">
          <div className="flex flex-wrap items-end gap-3">
            <Field label="Status">
              <select className={`${inputClass} w-40`} value={filters.status} onChange={(e) => setFilters({ ...filters, status: e.target.value })}>
                <option value="">All statuses</option>
                {WORKFLOW_STATUSES.map((s) => (
                  <option key={s} value={s}>
                    {humanizeToken(s)}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Trigger">
              <select className={`${inputClass} w-48`} value={filters.trigger_type} onChange={(e) => setFilters({ ...filters, trigger_type: e.target.value })}>
                <option value="">All triggers</option>
                {WORKFLOW_TRIGGERS.map((t) => (
                  <option key={t} value={t}>
                    {triggerLabel(t)}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Channel">
              <select className={`${inputClass} w-40`} value={filters.channel} onChange={(e) => setFilters({ ...filters, channel: e.target.value })}>
                <option value="">All channels</option>
                {MARKETING_CHANNELS.map((c) => (
                  <option key={c} value={c}>
                    {channelLabel(c)}
                  </option>
                ))}
              </select>
            </Field>
          </div>
        </Card>
      </div>

      <div className="mt-6">
        {loading ? (
          <LoadingState />
        ) : (
          <Card title="Workflows">
            {workflows.length === 0 ? (
              <p className="text-sm text-zinc-500">No workflows match the current filters.</p>
            ) : (
              <table className="w-full text-left text-sm">
                <thead>
                  <tr className="border-b text-zinc-500">
                    <th className="py-2">Name</th>
                    <th>Trigger</th>
                    <th>Channel</th>
                    <th>Status</th>
                    <th>Steps</th>
                    <th>Last triggered</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {workflows.map((workflow) => (
                    <tr key={workflow.id} className="border-b border-zinc-100">
                      <td className="py-2 font-medium">{workflow.name}</td>
                      <td className="text-zinc-600">{triggerLabel(workflow.trigger_type)}</td>
                      <td>{channelLabel(String(workflow.channel))}</td>
                      <td>
                        <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusTone(workflow.status)}`}>
                          {humanizeToken(workflow.status)}
                        </span>
                      </td>
                      <td>{workflow.steps_count ?? workflow.steps?.length ?? 0}</td>
                      <td className="text-zinc-500">{formatDateTime(workflow.last_triggered_at)}</td>
                      <td>
                        <Link href={`/admin/marketing/workflows/${workflow.id}`} className="text-xs text-zinc-600 underline">
                          Edit
                        </Link>
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
