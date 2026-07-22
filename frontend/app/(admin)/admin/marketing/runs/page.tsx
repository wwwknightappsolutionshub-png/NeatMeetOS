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
  statusTone,
  triggerLabel,
  type MarketingMessage,
  type MarketingRun,
} from '@/lib/marketing-types';
import {
  dispatchMarketingRun,
  fetchMarketingRun,
  fetchMarketingRuns,
  fetchRunMessages,
  generateBookingReminders,
  generateRebooking,
  generateReviewRequests,
  generateWinBack,
  type GenerationFilters,
} from '@/services/marketing.service';

const generators = [
  { key: 'booking', label: 'Booking reminders', run: generateBookingReminders },
  { key: 'rebook', label: 'Rebooking nudges', run: generateRebooking },
  { key: 'review', label: 'Review requests', run: generateReviewRequests },
  { key: 'winback', label: 'Win-back', run: generateWinBack },
] as const;

export default function MarketingRunsPage() {
  const [runs, setRuns] = useState<MarketingRun[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [selectedRun, setSelectedRun] = useState<MarketingRun | null>(null);
  const [messages, setMessages] = useState<MarketingMessage[]>([]);
  const [messagesLoading, setMessagesLoading] = useState(false);
  const [limit, setLimit] = useState('');

  const load = useCallback(() => {
    setLoading(true);
    fetchMarketingRuns()
      .then(setRuns)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load runs'))
      .finally(() => setLoading(false));
  }, []);

  const openRun = useCallback((id: string) => {
    setMessagesLoading(true);
    setError(null);
    fetchMarketingRun(id)
      .then(setSelectedRun)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load run'));
    fetchRunMessages(id)
      .then(setMessages)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load messages'))
      .finally(() => setMessagesLoading(false));
  }, []);

  useEffect(() => {
    load();
    if (typeof window !== 'undefined') {
      const runId = new URLSearchParams(window.location.search).get('run');
      if (runId) openRun(runId);
    }
  }, [load, openRun]);

  function generationFilters(): GenerationFilters {
    const parsed = parseInt(limit, 10);
    return Number.isFinite(parsed) && parsed > 0 ? { limit: parsed } : {};
  }

  async function runGenerator(key: string, generator: (filters?: GenerationFilters) => Promise<MarketingRun>) {
    setBusy(key);
    setError(null);
    setNotice(null);
    try {
      const run = await generator(generationFilters());
      setNotice(`Generated run with ${run.messages_count ?? run.messages?.length ?? 0} message(s).`);
      load();
      openRun(run.id);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Generation failed');
    } finally {
      setBusy(null);
    }
  }

  async function handleDispatch(id: string) {
    setBusy(`dispatch-${id}`);
    setError(null);
    setNotice(null);
    try {
      const run = await dispatchMarketingRun(id);
      const dispatch = run.summary?.dispatch;
      setNotice(
        dispatch
          ? `Simulated dispatch: ${dispatch.sent ?? 0} sent, ${dispatch.failed ?? 0} failed, ${dispatch.skipped ?? 0} skipped.`
          : 'Run dispatched (simulated).',
      );
      load();
      openRun(id);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Dispatch failed');
    } finally {
      setBusy(null);
    }
  }

  return (
    <AdminMarketingShell title="Runs">
      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}
      {notice ? (
        <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{notice}</div>
      ) : null}

      <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <strong>Simulated dispatch (Module 10A).</strong> Generation creates rendered messages; dispatch marks them sent
        via simulation only.
      </div>

      <Card title="Generate automation runs">
        <div className="flex flex-wrap items-end gap-3">
          <Field label="Limit (optional)">
            <input
              type="number"
              min={1}
              className={`${inputClass} w-32`}
              value={limit}
              onChange={(e) => setLimit(e.target.value)}
            />
          </Field>
          {generators.map((generator) => (
            <Button
              key={generator.key}
              type="button"
              variant="secondary"
              disabled={busy === generator.key}
              onClick={() => runGenerator(generator.key, generator.run)}
            >
              {busy === generator.key ? 'Generating…' : generator.label}
            </Button>
          ))}
        </div>
      </Card>

      <div className="mt-6 grid gap-4 lg:grid-cols-3">
        <div className="lg:col-span-2">
          {loading ? (
            <LoadingState />
          ) : (
            <Card title="Runs">
              {runs.length === 0 ? (
                <p className="text-sm text-zinc-500">No runs yet.</p>
              ) : (
                <table className="w-full text-left text-sm">
                  <thead>
                    <tr className="border-b text-zinc-500">
                      <th className="py-2">Type</th>
                      <th>Source</th>
                      <th>Status</th>
                      <th>Messages</th>
                      <th>Created</th>
                      <th />
                    </tr>
                  </thead>
                  <tbody>
                    {runs.map((run) => (
                      <tr key={run.id} className="border-b border-zinc-100">
                        <td className="py-2">{run.trigger_type ? triggerLabel(run.trigger_type) : 'Broadcast'}</td>
                        <td className="text-zinc-600">{humanizeToken(run.run_source)}</td>
                        <td>
                          <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusTone(run.status)}`}>
                            {humanizeToken(run.status)}
                          </span>
                        </td>
                        <td>{run.messages_count ?? run.summary?.total ?? '—'}</td>
                        <td className="text-zinc-500">{formatDateTime(run.created_at)}</td>
                        <td className="space-x-3 whitespace-nowrap">
                          <button type="button" className="text-xs text-zinc-600 underline" onClick={() => openRun(run.id)}>
                            View
                          </button>
                          <button
                            type="button"
                            className="text-xs text-zinc-900 underline disabled:opacity-50"
                            disabled={busy === `dispatch-${run.id}`}
                            onClick={() => handleDispatch(run.id)}
                          >
                            {busy === `dispatch-${run.id}` ? 'Dispatching…' : 'Dispatch'}
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

        <div>
          <Card title="Run detail">
            {!selectedRun ? (
              <p className="text-sm text-zinc-500">Select a run to view messages.</p>
            ) : (
              <div className="space-y-3">
                <div className="text-sm">
                  <p className="font-medium">{selectedRun.trigger_type ? triggerLabel(selectedRun.trigger_type) : 'Broadcast'}</p>
                  <p className="text-zinc-500">{selectedRun.campaign?.name ?? 'Ad-hoc run'}</p>
                  <p className="mt-1">
                    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusTone(selectedRun.status)}`}>
                      {humanizeToken(selectedRun.status)}
                    </span>
                  </p>
                </div>
                <Button
                  type="button"
                  disabled={busy === `dispatch-${selectedRun.id}`}
                  onClick={() => handleDispatch(selectedRun.id)}
                >
                  {busy === `dispatch-${selectedRun.id}` ? 'Dispatching…' : 'Dispatch run'}
                </Button>
                <div>
                  <p className="mb-1 text-xs font-medium uppercase tracking-wide text-zinc-500">Messages</p>
                  {messagesLoading ? (
                    <LoadingState />
                  ) : messages.length === 0 ? (
                    <p className="text-sm text-zinc-500">No messages.</p>
                  ) : (
                    <ul className="max-h-96 space-y-2 overflow-y-auto">
                      {messages.map((message) => (
                        <li key={message.id} className="rounded-md border border-zinc-200 p-2 text-sm">
                          <div className="flex items-center justify-between">
                            <span className="font-medium">{message.client?.display_name ?? message.recipient_address ?? message.client_id}</span>
                            <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusTone(message.status)}`}>
                              {humanizeToken(message.status)}
                            </span>
                          </div>
                          <p className="text-xs text-zinc-500">{channelLabel(String(message.channel))}</p>
                          {message.skipped_reason ? (
                            <p className="text-xs text-red-600">Skipped: {humanizeToken(message.skipped_reason)}</p>
                          ) : null}
                          {message.error_message ? (
                            <p className="text-xs text-red-600">{message.error_message}</p>
                          ) : null}
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
              </div>
            )}
          </Card>
        </div>
      </div>
    </AdminMarketingShell>
  );
}
