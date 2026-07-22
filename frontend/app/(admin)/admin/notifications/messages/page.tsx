'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { AdminNotificationsShell } from '@/components/admin/notifications/AdminNotificationsShell';
import {
  NotificationChannelBadge,
  NotificationPurposeBadge,
  NotificationStatusBadge,
} from '@/components/admin/notifications/badges';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import {
  formatDateTime,
  humanizeToken,
  NOTIFICATION_CHANNELS,
  NOTIFICATION_MESSAGE_STATUSES,
  NOTIFICATION_PURPOSES,
  NOTIFICATION_SOURCE_TYPES,
  purposeLabel,
  sourceTypeLabel,
  channelLabel,
  type NotificationMessage,
} from '@/lib/notifications-types';
import { fetchNotificationMessages } from '@/services/notifications.service';

const emptyFilters = {
  status: '',
  channel: '',
  source_type: '',
  purpose: '',
  from: '',
  to: '',
};

export default function NotificationMessagesPage() {
  const [messages, setMessages] = useState<NotificationMessage[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [filters, setFilters] = useState(emptyFilters);

  const load = useCallback(() => {
    setLoading(true);
    fetchNotificationMessages({
      status: filters.status || undefined,
      channel: filters.channel || undefined,
      source_type: filters.source_type || undefined,
      purpose: filters.purpose || undefined,
      from: filters.from || undefined,
      to: filters.to || undefined,
    })
      .then(setMessages)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load messages'))
      .finally(() => setLoading(false));
  }, [filters]);

  useEffect(() => {
    load();
  }, [load]);

  function relatedRefs(message: NotificationMessage): string {
    const refs: string[] = [];
    if (message.appointment_id) refs.push('Appointment');
    if (message.payment_transaction_id) refs.push('Payment');
    if (message.client_membership_id) refs.push('Membership');
    if (message.checkout_id) refs.push('Checkout');
    if (message.marketing_workflow_execution_id) refs.push('Marketing');
    return refs.length > 0 ? refs.join(', ') : '—';
  }

  return (
    <AdminNotificationsShell title="Messages">
      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}

      <div className="mb-4 flex items-center justify-between gap-3">
        <p className="text-sm text-zinc-500">Operational communication log — transactional messages, not marketing.</p>
        <Link href="/admin/notifications/messages/new">
          <Button type="button">New manual message</Button>
        </Link>
      </div>

      <Card title="Filters">
        <div className="flex flex-wrap items-end gap-3">
          <Field label="Status">
            <select className={`${inputClass} w-40`} value={filters.status} onChange={(e) => setFilters({ ...filters, status: e.target.value })}>
              <option value="">All statuses</option>
              {NOTIFICATION_MESSAGE_STATUSES.map((s) => (
                <option key={s} value={s}>{humanizeToken(s)}</option>
              ))}
            </select>
          </Field>
          <Field label="Channel">
            <select className={`${inputClass} w-36`} value={filters.channel} onChange={(e) => setFilters({ ...filters, channel: e.target.value })}>
              <option value="">All channels</option>
              {NOTIFICATION_CHANNELS.map((c) => (
                <option key={c} value={c}>{channelLabel(c)}</option>
              ))}
            </select>
          </Field>
          <Field label="Source">
            <select className={`${inputClass} w-36`} value={filters.source_type} onChange={(e) => setFilters({ ...filters, source_type: e.target.value })}>
              <option value="">All sources</option>
              {NOTIFICATION_SOURCE_TYPES.map((s) => (
                <option key={s} value={s}>{sourceTypeLabel(s)}</option>
              ))}
            </select>
          </Field>
          <Field label="Purpose">
            <select className={`${inputClass} w-44`} value={filters.purpose} onChange={(e) => setFilters({ ...filters, purpose: e.target.value })}>
              <option value="">All purposes</option>
              {NOTIFICATION_PURPOSES.map((p) => (
                <option key={p} value={p}>{purposeLabel(p)}</option>
              ))}
            </select>
          </Field>
          <Field label="From">
            <input type="date" className={inputClass} value={filters.from} onChange={(e) => setFilters({ ...filters, from: e.target.value })} />
          </Field>
          <Field label="To">
            <input type="date" className={inputClass} value={filters.to} onChange={(e) => setFilters({ ...filters, to: e.target.value })} />
          </Field>
          <Button type="button" variant="secondary" onClick={() => setFilters(emptyFilters)}>
            Reset
          </Button>
        </div>
      </Card>

      <div className="mt-6">
        {loading ? (
          <LoadingState />
        ) : (
          <Card title="Messages">
            {messages.length === 0 ? (
              <p className="text-sm text-zinc-500">No messages match the current filters.</p>
            ) : (
              <table className="w-full text-left text-sm">
                <thead>
                  <tr className="border-b text-zinc-500">
                    <th className="py-2">Recipient</th>
                    <th>Channel</th>
                    <th>Purpose</th>
                    <th>Source</th>
                    <th>Related</th>
                    <th>Status</th>
                    <th>Sent</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {messages.map((message) => (
                    <tr key={message.id} className="border-b border-zinc-100">
                      <td className="py-2 font-medium">
                        {message.client?.display_name ?? message.recipient_name ?? message.recipient_address ?? message.client_id ?? '—'}
                      </td>
                      <td><NotificationChannelBadge channel={message.channel} /></td>
                      <td><NotificationPurposeBadge purpose={message.purpose} /></td>
                      <td className="text-zinc-600">{sourceTypeLabel(message.source_type)}</td>
                      <td className="text-zinc-500">{relatedRefs(message)}</td>
                      <td><NotificationStatusBadge status={message.status} /></td>
                      <td className="text-zinc-500">{formatDateTime(message.sent_at)}</td>
                      <td>
                        <Link href={`/admin/notifications/messages/${message.id}`} className="text-xs text-zinc-600 underline">
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
    </AdminNotificationsShell>
  );
}
