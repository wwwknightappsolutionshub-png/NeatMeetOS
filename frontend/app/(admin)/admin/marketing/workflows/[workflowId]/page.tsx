'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
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
  WORKFLOW_TRIGGERS,
  type MarketingTemplate,
  type MarketingWorkflow,
  type MarketingWorkflowStep,
} from '@/lib/marketing-types';
import {
  fetchMarketingTemplates,
  fetchMarketingWorkflow,
  testMarketingWorkflow,
  updateMarketingWorkflow,
  updateMarketingWorkflowStatus,
  updateMarketingWorkflowSteps,
} from '@/services/marketing.service';

const statusActions = [
  { status: 'active', label: 'Activate' },
  { status: 'paused', label: 'Pause' },
  { status: 'archived', label: 'Archive' },
] as const;

export default function MarketingWorkflowDetailPage() {
  const params = useParams();
  const workflowId = params.workflowId as string;
  const [workflow, setWorkflow] = useState<MarketingWorkflow | null>(null);
  const [templates, setTemplates] = useState<MarketingTemplate[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);

  const [form, setForm] = useState({ name: '', description: '', trigger_type: '', channel: '' });
  const [steps, setSteps] = useState<MarketingWorkflowStep[]>([]);
  const [newStepTemplate, setNewStepTemplate] = useState('');
  const [newStepDelay, setNewStepDelay] = useState('');
  const [testClientId, setTestClientId] = useState('');

  const applyWorkflow = useCallback((data: MarketingWorkflow) => {
    setWorkflow(data);
    setForm({
      name: data.name ?? '',
      description: data.description ?? '',
      trigger_type: String(data.trigger_type ?? ''),
      channel: String(data.channel ?? ''),
    });
    setSteps(data.steps ?? []);
  }, []);

  const load = useCallback(() => {
    setLoading(true);
    fetchMarketingWorkflow(workflowId)
      .then(applyWorkflow)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load workflow'))
      .finally(() => setLoading(false));
  }, [workflowId, applyWorkflow]);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    fetchMarketingTemplates({ is_active: true })
      .then(setTemplates)
      .catch(() => setTemplates([]));
  }, []);

  async function saveDetails(e: React.FormEvent) {
    e.preventDefault();
    setBusy('details');
    setError(null);
    setNotice(null);
    try {
      const updated = await updateMarketingWorkflow(workflowId, {
        name: form.name,
        description: form.description || null,
        trigger_type: form.trigger_type,
        channel: form.channel,
      });
      applyWorkflow(updated);
      setNotice('Workflow details saved.');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Save failed');
    } finally {
      setBusy(null);
    }
  }

  async function changeStatus(status: string) {
    setBusy(`status-${status}`);
    setError(null);
    setNotice(null);
    try {
      const updated = await updateMarketingWorkflowStatus(workflowId, status);
      applyWorkflow(updated);
      setNotice(`Workflow ${humanizeToken(status).toLowerCase()}.`);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Status update failed');
    } finally {
      setBusy(null);
    }
  }

  function addSendMessageStep() {
    if (!newStepTemplate) return;
    const delay = parseInt(newStepDelay, 10);
    setSteps((current) => [
      ...current,
      {
        step_type: 'send_message',
        template_id: newStepTemplate,
        channel: form.channel || null,
        delay_minutes: Number.isFinite(delay) && delay > 0 ? delay : 0,
        payload: {},
      },
    ]);
    setNewStepTemplate('');
    setNewStepDelay('');
  }

  function removeStep(index: number) {
    setSteps((current) => current.filter((_, i) => i !== index));
  }

  async function saveSteps() {
    setBusy('steps');
    setError(null);
    setNotice(null);
    try {
      const updated = await updateMarketingWorkflowSteps(
        workflowId,
        steps.map((step) => ({
          step_type: step.step_type,
          delay_minutes: step.delay_minutes ?? 0,
          template_id: step.template_id ?? null,
          channel: step.channel ?? null,
          payload: step.payload ?? {},
        })),
      );
      applyWorkflow(updated);
      setNotice('Workflow steps saved.');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Saving steps failed');
    } finally {
      setBusy(null);
    }
  }

  async function runTest() {
    if (!testClientId) return;
    setBusy('test');
    setError(null);
    setNotice(null);
    try {
      const execution = await testMarketingWorkflow(workflowId, testClientId);
      setNotice(`Test run created execution ${execution.id} (${humanizeToken(execution.status)}).`);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Test run failed');
    } finally {
      setBusy(null);
    }
  }

  function templateName(id?: string | null): string {
    if (!id) return 'None';
    return templates.find((t) => t.id === id)?.name ?? id;
  }

  const channelTemplates = templates.filter((t) => String(t.channel) === form.channel);

  if (loading && !workflow) {
    return (
      <AdminMarketingShell title="Workflow">
        <LoadingState />
      </AdminMarketingShell>
    );
  }

  return (
    <AdminMarketingShell title={workflow ? workflow.name : 'Workflow'}>
      <p className="mb-4 text-sm">
        <Link href="/admin/marketing/workflows" className="text-zinc-600 hover:underline">
          ← Back to workflows
        </Link>
      </p>
      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}
      {notice ? (
        <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{notice}</div>
      ) : null}

      {workflow ? (
        <div className="grid gap-6 lg:grid-cols-3">
          <div className="lg:col-span-2 space-y-6">
            <Card title="Workflow details">
              <form onSubmit={saveDetails} className="grid gap-3 md:grid-cols-2">
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
                  <select className={inputClass} value={form.channel} onChange={(e) => setForm({ ...form, channel: e.target.value })}>
                    {MARKETING_CHANNELS.map((c) => (
                      <option key={c} value={c}>
                        {channelLabel(c)}
                      </option>
                    ))}
                  </select>
                </Field>
                <div className="md:col-span-2">
                  <Field label="Description">
                    <textarea className={`${inputClass} min-h-20`} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
                  </Field>
                </div>
                <div className="md:col-span-2">
                  <Button type="submit" disabled={busy === 'details'}>
                    {busy === 'details' ? 'Saving…' : 'Save details'}
                  </Button>
                </div>
              </form>
            </Card>

            <Card title="Steps">
              {steps.length === 0 ? (
                <p className="text-sm text-zinc-500">No steps yet. Add a send message step below.</p>
              ) : (
                <ol className="space-y-2">
                  {steps.map((step, index) => (
                    <li key={step.id ?? index} className="flex items-center justify-between rounded-md border border-zinc-200 p-2 text-sm">
                      <div>
                        <span className="font-medium">
                          {index + 1}. {humanizeToken(String(step.step_type))}
                        </span>
                        <span className="ml-2 text-zinc-500">
                          {step.step_type === 'send_message' ? templateName(step.template_id) : ''}
                          {step.delay_minutes ? ` · +${step.delay_minutes} min` : ''}
                        </span>
                      </div>
                      <button type="button" className="text-xs text-red-600 underline" onClick={() => removeStep(index)}>
                        Remove
                      </button>
                    </li>
                  ))}
                </ol>
              )}

              <div className="mt-4 flex flex-wrap items-end gap-3 border-t border-zinc-100 pt-4">
                <Field label="Send message template">
                  <select className={`${inputClass} w-56`} value={newStepTemplate} onChange={(e) => setNewStepTemplate(e.target.value)}>
                    <option value="">Select template…</option>
                    {channelTemplates.map((t) => (
                      <option key={t.id} value={t.id}>
                        {t.name}
                      </option>
                    ))}
                  </select>
                </Field>
                <Field label="Delay (minutes)">
                  <input
                    type="number"
                    min={0}
                    className={`${inputClass} w-32`}
                    value={newStepDelay}
                    onChange={(e) => setNewStepDelay(e.target.value)}
                  />
                </Field>
                <Button type="button" variant="secondary" disabled={!newStepTemplate} onClick={addSendMessageStep}>
                  Add step
                </Button>
              </div>

              <div className="mt-4">
                <Button type="button" disabled={busy === 'steps'} onClick={saveSteps}>
                  {busy === 'steps' ? 'Saving…' : 'Save steps'}
                </Button>
              </div>
            </Card>
          </div>

          <div className="space-y-6">
            <Card title="Status">
              <p className="mb-3">
                <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusTone(workflow.status)}`}>
                  {humanizeToken(workflow.status)}
                </span>
              </p>
              <div className="flex flex-wrap gap-2">
                {statusActions
                  .filter((action) => action.status !== workflow.status)
                  .map((action) => (
                    <Button
                      key={action.status}
                      type="button"
                      variant="secondary"
                      disabled={busy === `status-${action.status}`}
                      onClick={() => changeStatus(action.status)}
                    >
                      {busy === `status-${action.status}` ? '…' : action.label}
                    </Button>
                  ))}
              </div>
            </Card>

            <Card title="Test run">
              <p className="mb-2 text-xs text-zinc-500">
                Runs the workflow for a single client in the simulated provider environment.
              </p>
              <Field label="Client ID">
                <input className={inputClass} value={testClientId} onChange={(e) => setTestClientId(e.target.value)} placeholder="UUID" />
              </Field>
              <div className="mt-3">
                <Button type="button" disabled={!testClientId || busy === 'test'} onClick={runTest}>
                  {busy === 'test' ? 'Running…' : 'Run test'}
                </Button>
              </div>
            </Card>

            <Card title="Meta">
              <dl className="space-y-2 text-sm">
                <div className="flex justify-between">
                  <dt className="text-zinc-500">Channel</dt>
                  <dd>{channelLabel(String(workflow.channel))}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-zinc-500">Last triggered</dt>
                  <dd>{formatDateTime(workflow.last_triggered_at)}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-zinc-500">Created</dt>
                  <dd>{formatDateTime(workflow.created_at)}</dd>
                </div>
              </dl>
            </Card>
          </div>
        </div>
      ) : null}
    </AdminMarketingShell>
  );
}
