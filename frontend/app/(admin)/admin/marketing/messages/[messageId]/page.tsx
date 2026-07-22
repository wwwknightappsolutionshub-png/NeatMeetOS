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
  type MarketingMessage,
} from '@/lib/marketing-types';
import {
  fetchMarketingMessage,
  markMessageClicked,
  markMessageDelivered,
  markMessageFailed,
  markMessageOpened,
  unsubscribeMarketingMessage,
} from '@/services/marketing.service';

export default function MarketingMessageDetailPage() {
  const params = useParams();
  const messageId = params.messageId as string;
  const [message, setMessage] = useState<MarketingMessage | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    fetchMarketingMessage(messageId)
      .then(setMessage)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load message'))
      .finally(() => setLoading(false));
  }, [messageId]);

  useEffect(() => {
    load();
  }, [load]);

  async function runAction(key: string, action: (id: string) => Promise<MarketingMessage>, label: string) {
    setBusy(key);
    setError(null);
    setNotice(null);
    try {
      const updated = await action(messageId);
      setMessage(updated);
      setNotice(label);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Action failed');
    } finally {
      setBusy(null);
    }
  }

  const actions = [
    { key: 'delivered', label: 'Mark delivered', action: markMessageDelivered, notice: 'Message marked delivered.' },
    { key: 'opened', label: 'Mark opened', action: markMessageOpened, notice: 'Message marked opened.' },
    { key: 'clicked', label: 'Mark clicked', action: markMessageClicked, notice: 'Message marked clicked.' },
    { key: 'failed', label: 'Mark failed', action: (id: string) => markMessageFailed(id), notice: 'Message marked failed.' },
    { key: 'unsubscribe', label: 'Unsubscribe', action: unsubscribeMarketingMessage, notice: 'Recipient unsubscribed.' },
  ] as const;

  if (loading && !message) {
    return (
      <AdminMarketingShell title="Message">
        <LoadingState />
      </AdminMarketingShell>
    );
  }

  return (
    <AdminMarketingShell title="Message">
      <p className="mb-4 text-sm">
        <Link href="/admin/marketing/messages" className="text-zinc-600 hover:underline">
          ← Back to messages
        </Link>
      </p>
      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}
      {notice ? (
        <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{notice}</div>
      ) : null}

      <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <strong>Simulated provider environment.</strong> The controls below record delivery events locally for testing;
        no live message is sent.
      </div>

      {message ? (
        <div className="grid gap-6 lg:grid-cols-3">
          <div className="lg:col-span-2 space-y-6">
            <Card title="Message">
              <dl className="grid gap-3 text-sm sm:grid-cols-2">
                <div>
                  <dt className="text-zinc-500">Recipient</dt>
                  <dd className="font-medium">{message.client?.display_name ?? message.recipient_address ?? message.client_id ?? '—'}</dd>
                </div>
                <div>
                  <dt className="text-zinc-500">Address</dt>
                  <dd>{message.recipient_address ?? '—'}</dd>
                </div>
                <div>
                  <dt className="text-zinc-500">Channel</dt>
                  <dd>{channelLabel(String(message.channel))}</dd>
                </div>
                <div>
                  <dt className="text-zinc-500">Purpose</dt>
                  <dd>{message.purpose ? humanizeToken(String(message.purpose)) : '—'}</dd>
                </div>
                <div>
                  <dt className="text-zinc-500">Status</dt>
                  <dd>
                    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusTone(message.status)}`}>
                      {humanizeToken(message.status)}
                    </span>
                  </dd>
                </div>
                <div>
                  <dt className="text-zinc-500">Subject</dt>
                  <dd>{message.subject ?? '—'}</dd>
                </div>
              </dl>
              {message.rendered_body_text ? (
                <div className="mt-4">
                  <p className="mb-1 text-xs font-medium uppercase tracking-wide text-zinc-500">Rendered body</p>
                  <pre className="whitespace-pre-wrap rounded-md border border-zinc-200 bg-zinc-50 p-3 text-sm">{message.rendered_body_text}</pre>
                </div>
              ) : null}
            </Card>

            <Card title="Delivery timestamps">
              <dl className="grid gap-3 text-sm sm:grid-cols-2">
                <div className="flex justify-between">
                  <dt className="text-zinc-500">Scheduled</dt>
                  <dd>{formatDateTime(message.scheduled_for)}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-zinc-500">Sent</dt>
                  <dd>{formatDateTime(message.sent_at)}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-zinc-500">Delivered</dt>
                  <dd>{formatDateTime(message.delivered_at)}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-zinc-500">Opened</dt>
                  <dd>{formatDateTime(message.opened_at)}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-zinc-500">Clicked</dt>
                  <dd>{formatDateTime(message.clicked_at)}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-zinc-500">Failed</dt>
                  <dd>{formatDateTime(message.failed_at)}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-zinc-500">Unsubscribed</dt>
                  <dd>{formatDateTime(message.unsubscribed_at)}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-zinc-500">Suppressed</dt>
                  <dd>{formatDateTime(message.suppressed_at)}</dd>
                </div>
              </dl>
              {message.error_message ? <p className="mt-2 text-xs text-red-600">{message.error_message}</p> : null}
            </Card>

            <Card title="Attempts">
              {message.attempts && message.attempts.length > 0 ? (
                <table className="w-full text-left text-sm">
                  <thead>
                    <tr className="border-b text-zinc-500">
                      <th className="py-2">Status</th>
                      <th>Provider</th>
                      <th>Reference</th>
                      <th>Attempted</th>
                    </tr>
                  </thead>
                  <tbody>
                    {message.attempts.map((attempt) => (
                      <tr key={attempt.id} className="border-b border-zinc-100">
                        <td className="py-2">
                          <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusTone(attempt.status)}`}>
                            {humanizeToken(attempt.status)}
                          </span>
                        </td>
                        <td className="text-zinc-600">{attempt.provider ?? '—'}</td>
                        <td className="text-zinc-500">{attempt.provider_reference ?? '—'}</td>
                        <td className="text-zinc-500">{formatDateTime(attempt.attempted_at)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              ) : (
                <p className="text-sm text-zinc-500">No delivery attempts recorded.</p>
              )}
            </Card>
          </div>

          <div className="space-y-6">
            <Card title="Admin testing">
              <p className="mb-3 text-xs text-zinc-500">Simulated provider environment</p>
              <div className="flex flex-col gap-2">
                {actions.map((item) => (
                  <Button
                    key={item.key}
                    type="button"
                    variant="secondary"
                    disabled={busy === item.key}
                    onClick={() => runAction(item.key, item.action, item.notice)}
                  >
                    {busy === item.key ? '…' : item.label}
                  </Button>
                ))}
              </div>
            </Card>
          </div>
        </div>
      ) : null}
    </AdminMarketingShell>
  );
}
