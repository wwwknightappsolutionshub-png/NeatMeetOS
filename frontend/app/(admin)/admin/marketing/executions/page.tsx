'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { AdminMarketingShell } from '@/components/admin/marketing/AdminMarketingShell';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import {
  EXECUTION_STATUSES,
  formatDateTime,
  humanizeToken,
  statusTone,
  triggerLabel,
  type MarketingWorkflowExecution,
} from '@/lib/marketing-types';
import {
  fetchMarketingExecutions,
  processMarketingExecutions,
  runBirthdayAutomations,
} from '@/services/marketing.service';

export default function MarketingExecutionsPage() {
  const [executions, setExecutions] = useState<MarketingWorkflowExecution[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [status, setStatus] = useState('');

  const load = useCallback(() => {
    setLoading(true);
    fetchMarketingExecutions({ status: status || undefined })
      .then(setExecutions)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load executions'))
      .finally(() => setLoading(false));
  }, [status]);

  useEffect(() => {
    load();
  }, [load]);

  async function processQueued() {
    setBusy('process');
    setError(null);
    setNotice(null);
    try {
      const summary = await processMarketingExecutions();
      setNotice(`Processed ${summary.processed ?? 0} execution(s): ${summary.completed ?? 0} completed, ${summary.failed ?? 0} failed, ${summary.skipped ?? 0} skipped.`);
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Processing failed');
    } finally {
      setBusy(null);
    }
  }

  async function runBirthday() {
    setBusy('birthday');
    setError(null);
    setNotice(null);
    try {
      const result = await runBirthdayAutomations();
      setNotice(`Birthday automations matched ${result.matched} client(s), created ${result.executions.length} execution(s).`);
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Birthday automations failed');
    } finally {
      setBusy(null);
    }
  }

  return (
    <AdminMarketingShell title="Executions">
      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}
      {notice ? (
        <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{notice}</div>
      ) : null}

      <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <strong>Simulated provider environment.</strong> Processing queued executions generates and dispatches messages
        via local simulation only.
      </div>

      <Card title="Actions">
        <div className="flex flex-wrap items-end gap-3">
          <Field label="Status filter">
            <select className={`${inputClass} w-44`} value={status} onChange={(e) => setStatus(e.target.value)}>
              <option value="">All statuses</option>
              {EXECUTION_STATUSES.map((s) => (
                <option key={s} value={s}>
                  {humanizeToken(s)}
                </option>
              ))}
            </select>
          </Field>
          <Button type="button" disabled={busy === 'process'} onClick={processQueued}>
            {busy === 'process' ? 'Processing…' : 'Process queued'}
          </Button>
          <Button type="button" variant="secondary" disabled={busy === 'birthday'} onClick={runBirthday}>
            {busy === 'birthday' ? 'Running…' : 'Run birthday automations'}
          </Button>
        </div>
      </Card>

      <div className="mt-6">
        {loading ? (
          <LoadingState />
        ) : (
          <Card title="Executions">
            {executions.length === 0 ? (
              <p className="text-sm text-zinc-500">No executions match the current filter.</p>
            ) : (
              <table className="w-full text-left text-sm">
                <thead>
                  <tr className="border-b text-zinc-500">
                    <th className="py-2">Workflow</th>
                    <th>Trigger</th>
                    <th>Client</th>
                    <th>Status</th>
                    <th>Messages</th>
                    <th>Scheduled</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {executions.map((execution) => (
                    <tr key={execution.id} className="border-b border-zinc-100">
                      <td className="py-2 font-medium">{execution.workflow?.name ?? '—'}</td>
                      <td className="text-zinc-600">{execution.trigger_type ? triggerLabel(execution.trigger_type) : '—'}</td>
                      <td className="text-zinc-600">{execution.client?.display_name ?? execution.client_id ?? '—'}</td>
                      <td>
                        <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusTone(execution.status)}`}>
                          {humanizeToken(execution.status)}
                        </span>
                      </td>
                      <td>{execution.messages_count ?? execution.messages?.length ?? 0}</td>
                      <td className="text-zinc-500">{formatDateTime(execution.scheduled_for)}</td>
                      <td>
                        <Link href={`/admin/marketing/executions/${execution.id}`} className="text-xs text-zinc-600 underline">
                          View
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
