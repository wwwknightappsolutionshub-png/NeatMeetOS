'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { AdminMarketingShell } from '@/components/admin/marketing/AdminMarketingShell';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import {
  channelLabel,
  formatDateTime,
  humanizeToken,
  statusTone,
  triggerLabel,
  type MarketingWorkflowExecution,
} from '@/lib/marketing-types';
import { cancelMarketingExecution, fetchMarketingExecution } from '@/services/marketing.service';

const CANCELLABLE = ['queued', 'running'];

export default function MarketingExecutionDetailPage() {
  const params = useParams();
  const executionId = params.executionId as string;
  const [execution, setExecution] = useState<MarketingWorkflowExecution | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    setLoading(true);
    fetchMarketingExecution(executionId)
      .then(setExecution)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load execution'))
      .finally(() => setLoading(false));
  }, [executionId]);

  useEffect(() => {
    load();
  }, [load]);

  async function cancel() {
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      const updated = await cancelMarketingExecution(executionId);
      setExecution(updated);
      setNotice('Execution cancelled.');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Cancel failed');
    } finally {
      setBusy(false);
    }
  }

  if (loading && !execution) {
    return (
      <AdminMarketingShell title="Execution">
        <LoadingState />
      </AdminMarketingShell>
    );
  }

  const canCancel = execution ? CANCELLABLE.includes(String(execution.status)) : false;

  return (
    <AdminMarketingShell title={execution?.workflow?.name ?? 'Execution'}>
      <p className="mb-4 text-sm">
        <Link href="/admin/marketing/executions" className="text-zinc-600 hover:underline">
          ← Back to executions
        </Link>
      </p>
      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}
      {notice ? (
        <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{notice}</div>
      ) : null}

      {execution ? (
        <div className="grid gap-6 lg:grid-cols-3">
          <div className="lg:col-span-2 space-y-6">
            <Card title="Steps">
              {execution.steps && execution.steps.length > 0 ? (
                <ol className="space-y-2">
                  {execution.steps.map((step, index) => (
                    <li key={step.id} className="rounded-md border border-zinc-200 p-3 text-sm">
                      <div className="flex items-center justify-between">
                        <span className="font-medium">
                          {(step.position ?? index) + 1}. {humanizeToken(String(step.step_type))}
                        </span>
                        <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusTone(step.status)}`}>
                          {humanizeToken(step.status)}
                        </span>
                      </div>
                      <div className="mt-1 text-xs text-zinc-500">
                        {step.scheduled_for ? `Scheduled ${formatDateTime(step.scheduled_for)}` : null}
                        {step.processed_at ? ` · Processed ${formatDateTime(step.processed_at)}` : null}
                      </div>
                      {step.failure_reason ? <p className="mt-1 text-xs text-red-600">{step.failure_reason}</p> : null}
                    </li>
                  ))}
                </ol>
              ) : (
                <p className="text-sm text-zinc-500">No steps recorded.</p>
              )}
            </Card>

            <Card title="Messages">
              {execution.messages && execution.messages.length > 0 ? (
                <ul className="space-y-2">
                  {execution.messages.map((message) => (
                    <li key={message.id} className="rounded-md border border-zinc-200 p-2 text-sm">
                      <div className="flex items-center justify-between">
                        <span className="font-medium">
                          {message.client?.display_name ?? message.recipient_address ?? message.client_id}
                        </span>
                        <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusTone(message.status)}`}>
                          {humanizeToken(message.status)}
                        </span>
                      </div>
                      <p className="text-xs text-zinc-500">{channelLabel(String(message.channel))}</p>
                      <Link href={`/admin/marketing/messages/${message.id}`} className="text-xs text-zinc-600 underline">
                        View message
                      </Link>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="text-sm text-zinc-500">No messages generated.</p>
              )}
            </Card>
          </div>

          <div className="space-y-6">
            <Card title="Execution">
              <dl className="space-y-2 text-sm">
                <div className="flex justify-between">
                  <dt className="text-zinc-500">Status</dt>
                  <dd>
                    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusTone(execution.status)}`}>
                      {humanizeToken(execution.status)}
                    </span>
                  </dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-zinc-500">Trigger</dt>
                  <dd>{execution.trigger_type ? triggerLabel(execution.trigger_type) : '—'}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-zinc-500">Client</dt>
                  <dd>{execution.client?.display_name ?? execution.client_id ?? '—'}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-zinc-500">Scheduled</dt>
                  <dd>{formatDateTime(execution.scheduled_for)}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-zinc-500">Started</dt>
                  <dd>{formatDateTime(execution.started_at)}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-zinc-500">Completed</dt>
                  <dd>{formatDateTime(execution.completed_at)}</dd>
                </div>
              </dl>
              {execution.failure_reason ? (
                <p className="mt-2 text-xs text-red-600">{execution.failure_reason}</p>
              ) : null}
              {canCancel ? (
                <div className="mt-4">
                  <Button type="button" variant="secondary" disabled={busy} onClick={cancel}>
                    {busy ? 'Cancelling…' : 'Cancel execution'}
                  </Button>
                </div>
              ) : null}
            </Card>
          </div>
        </div>
      ) : null}
    </AdminMarketingShell>
  );
}
