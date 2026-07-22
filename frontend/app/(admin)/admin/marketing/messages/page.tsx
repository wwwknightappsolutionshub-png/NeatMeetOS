'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { AdminMarketingShell } from '@/components/admin/marketing/AdminMarketingShell';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import {
  channelLabel,
  formatDateTime,
  humanizeToken,
  MARKETING_CHANNELS,
  MESSAGE_STATUSES,
  statusTone,
  type MarketingMessage,
} from '@/lib/marketing-types';
import { fetchMarketingMessages } from '@/services/marketing.service';

export default function MarketingMessagesPage() {
  const [messages, setMessages] = useState<MarketingMessage[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [filters, setFilters] = useState({ status: '', channel: '' });

  const load = useCallback(() => {
    setLoading(true);
    fetchMarketingMessages({
      status: filters.status || undefined,
      channel: filters.channel || undefined,
    })
      .then(setMessages)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load messages'))
      .finally(() => setLoading(false));
  }, [filters]);

  useEffect(() => {
    load();
  }, [load]);

  return (
    <AdminMarketingShell title="Messages">
      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}

      <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <strong>Simulated provider environment.</strong> Delivery, open and click events are recorded through local
        simulation for testing.
      </div>

      <Card title="Filters">
        <div className="flex flex-wrap items-end gap-3">
          <Field label="Status">
            <select className={`${inputClass} w-44`} value={filters.status} onChange={(e) => setFilters({ ...filters, status: e.target.value })}>
              <option value="">All statuses</option>
              {MESSAGE_STATUSES.map((s) => (
                <option key={s} value={s}>
                  {humanizeToken(s)}
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
                    <th>Status</th>
                    <th>Sent</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {messages.map((message) => (
                    <tr key={message.id} className="border-b border-zinc-100">
                      <td className="py-2 font-medium">
                        {message.client?.display_name ?? message.recipient_address ?? message.client_id ?? '—'}
                      </td>
                      <td>{channelLabel(String(message.channel))}</td>
                      <td className="text-zinc-600">{message.purpose ? humanizeToken(String(message.purpose)) : '—'}</td>
                      <td>
                        <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusTone(message.status)}`}>
                          {humanizeToken(message.status)}
                        </span>
                      </td>
                      <td className="text-zinc-500">{formatDateTime(message.sent_at)}</td>
                      <td>
                        <Link href={`/admin/marketing/messages/${message.id}`} className="text-xs text-zinc-600 underline">
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
