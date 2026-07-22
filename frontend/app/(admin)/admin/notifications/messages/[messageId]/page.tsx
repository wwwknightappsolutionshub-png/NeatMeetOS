'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useCallback, useEffect, useState } from 'react';
import { AdminNotificationsShell } from '@/components/admin/notifications/AdminNotificationsShell';
import { NotificationAttemptsTable } from '@/components/admin/notifications/NotificationAttemptsTable';
import {
  NotificationChannelBadge,
  NotificationPurposeBadge,
  NotificationStatusBadge,
} from '@/components/admin/notifications/badges';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import {
  canMarkDelivered,
  formatDateTime,
  isCancellable,
  sourceTypeLabel,
  type NotificationMessage,
} from '@/lib/notifications-types';
import {
  cancelNotificationMessage,
  fetchNotificationMessage,
  markNotificationMessageDelivered,
} from '@/services/notifications.service';

const REFERENCES: { key: keyof NotificationMessage; label: string }[] = [
  { key: 'client_id', label: 'Client' },
  { key: 'appointment_id', label: 'Appointment' },
  { key: 'payment_transaction_id', label: 'Payment transaction' },
  { key: 'client_membership_id', label: 'Client membership' },
  { key: 'checkout_id', label: 'Checkout' },
  { key: 'marketing_workflow_execution_id', label: 'Marketing execution' },
  { key: 'notification_template_id', label: 'Template' },
];

export default function NotificationMessageDetailPage() {
  const params = useParams();
  const messageId = params.messageId as string;
  const [message, setMessage] = useState<NotificationMessage | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    fetchNotificationMessage(messageId)
      .then(setMessage)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load message'))
      .finally(() => setLoading(false));
  }, [messageId]);

  useEffect(() => {
    load();
  }, [load]);

  async function runAction(
    key: string,
    action: (id: string) => Promise<NotificationMessage>,
    label: string,
  ) {
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

  if (loading && !message) {
    return (
      <AdminNotificationsShell title="Message">
        <LoadingState />
      </AdminNotificationsShell>
    );
  }

  const isFailed = message?.status === 'failed' || message?.status === 'suppressed';

  return (
    <AdminNotificationsShell title="Message">
      <p className="mb-4 text-sm">
        <Link href="/admin/notifications/messages" className="text-zinc-600 hover:underline">
          ← Back to messages
        </Link>
      </p>
      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}
      {notice ? (
        <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{notice}</div>
      ) : null}

      {message ? (
        <div className="grid gap-6 lg:grid-cols-3">
          <div className="lg:col-span-2 space-y-6">
            {isFailed && message.failure_reason ? (
              <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <strong>{message.status === 'suppressed' ? 'Suppressed: ' : 'Failed: '}</strong>
                {message.failure_reason}
              </div>
            ) : null}

            <Card title="Message">
              <dl className="grid gap-3 text-sm sm:grid-cols-2">
                <div>
                  <dt className="text-zinc-500">Recipient</dt>
                  <dd className="font-medium">
                    {message.client?.display_name ?? message.recipient_name ?? message.recipient_address ?? '—'}
                  </dd>
                </div>
                <div>
                  <dt className="text-zinc-500">Address</dt>
                  <dd>{message.recipient_address ?? '—'}</dd>
                </div>
                <div>
                  <dt className="text-zinc-500">Channel</dt>
                  <dd><NotificationChannelBadge channel={message.channel} /></dd>
                </div>
                <div>
                  <dt className="text-zinc-500">Purpose</dt>
                  <dd><NotificationPurposeBadge purpose={message.purpose} /></dd>
                </div>
                <div>
                  <dt className="text-zinc-500">Source</dt>
                  <dd>{sourceTypeLabel(message.source_type)}</dd>
                </div>
                <div>
                  <dt className="text-zinc-500">Status</dt>
                  <dd><NotificationStatusBadge status={message.status} /></dd>
                </div>
                <div className="sm:col-span-2">
                  <dt className="text-zinc-500">Subject</dt>
                  <dd>{message.subject ?? '—'}</dd>
                </div>
              </dl>
              {message.body_text ? (
                <div className="mt-4">
                  <p className="mb-1 text-xs font-medium uppercase tracking-wide text-zinc-500">Body preview</p>
                  <pre className="whitespace-pre-wrap rounded-md border border-zinc-200 bg-zinc-50 p-3 text-sm">{message.body_text}</pre>
                </div>
              ) : null}
            </Card>

            <Card title="Delivery timestamps">
              <dl className="grid gap-2 text-sm sm:grid-cols-2">
                <div className="flex justify-between"><dt className="text-zinc-500">Queued</dt><dd>{formatDateTime(message.queued_at)}</dd></div>
                <div className="flex justify-between"><dt className="text-zinc-500">Sent</dt><dd>{formatDateTime(message.sent_at)}</dd></div>
                <div className="flex justify-between"><dt className="text-zinc-500">Delivered</dt><dd>{formatDateTime(message.delivered_at)}</dd></div>
                <div className="flex justify-between"><dt className="text-zinc-500">Failed</dt><dd>{formatDateTime(message.failed_at)}</dd></div>
                <div className="flex justify-between"><dt className="text-zinc-500">Cancelled</dt><dd>{formatDateTime(message.cancelled_at)}</dd></div>
              </dl>
            </Card>

            <Card title="Dispatch attempts">
              <NotificationAttemptsTable attempts={message.attempts} />
            </Card>
          </div>

          <div className="space-y-6">
            <Card title="Actions">
              <p className="mb-3 text-xs text-zinc-500">Simulated dispatch environment — no live message is sent.</p>
              <div className="flex flex-col gap-2">
                <Button
                  type="button"
                  variant="secondary"
                  disabled={busy !== null || !isCancellable(message.status)}
                  onClick={() => runAction('cancel', cancelNotificationMessage, 'Message cancelled.')}
                >
                  {busy === 'cancel' ? '…' : 'Cancel message'}
                </Button>
                <Button
                  type="button"
                  variant="secondary"
                  disabled={busy !== null || !canMarkDelivered(message.status)}
                  onClick={() => runAction('delivered', markNotificationMessageDelivered, 'Marked delivered.')}
                >
                  {busy === 'delivered' ? '…' : 'Mark delivered'}
                </Button>
              </div>
              <p className="mt-3 text-xs text-zinc-500">
                &ldquo;Mark delivered&rdquo; is an admin correction utility for reconciling manual delivery.
              </p>
            </Card>

            <Card title="Related records">
              <dl className="space-y-2 text-sm">
                {REFERENCES.filter((ref) => message[ref.key]).map((ref) => (
                  <div key={ref.key}>
                    <dt className="text-zinc-500">{ref.label}</dt>
                    <dd className="break-all font-mono text-xs">{String(message[ref.key])}</dd>
                  </div>
                ))}
                {REFERENCES.every((ref) => !message[ref.key]) ? (
                  <p className="text-zinc-500">No linked records.</p>
                ) : null}
              </dl>
            </Card>
          </div>
        </div>
      ) : null}
    </AdminNotificationsShell>
  );
}
