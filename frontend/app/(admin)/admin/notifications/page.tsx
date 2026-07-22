'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { AdminNotificationsShell } from '@/components/admin/notifications/AdminNotificationsShell';
import { NotificationSummaryCards } from '@/components/admin/notifications/NotificationSummaryCards';
import {
  NotificationChannelBadge,
  NotificationPurposeBadge,
  NotificationStatusBadge,
} from '@/components/admin/notifications/badges';
import { ErrorAlert } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import {
  channelLabel,
  formatDateTime,
  purposeLabel,
  type NotificationByPurposeRow,
  type NotificationFailureRow,
  type NotificationReportingSummary,
} from '@/lib/notifications-types';
import {
  fetchNotificationReportingByPurpose,
  fetchNotificationReportingFailures,
  fetchNotificationReportingSummary,
} from '@/services/notifications.service';

const quickLinks = [
  { href: '/admin/notifications/messages', label: 'Messages', description: 'Operational communication log' },
  { href: '/admin/notifications/messages/new', label: 'New message', description: 'Send a manual client message' },
  { href: '/admin/notifications/templates', label: 'Templates', description: 'Reusable operational copy' },
  { href: '/admin/notifications/preferences', label: 'Preferences', description: 'Per-client communication settings' },
  { href: '/admin/notifications/settings', label: 'Settings', description: 'Tenant notification defaults' },
];

export default function NotificationsOverviewPage() {
  const [summary, setSummary] = useState<NotificationReportingSummary | null>(null);
  const [failures, setFailures] = useState<NotificationFailureRow[]>([]);
  const [byPurpose, setByPurpose] = useState<NotificationByPurposeRow[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchNotificationReportingSummary()
      .then(setSummary)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load summary'));
    fetchNotificationReportingFailures()
      .then((data) => setFailures(data.slice(0, 8)))
      .catch(() => setFailures([]));
    fetchNotificationReportingByPurpose()
      .then(setByPurpose)
      .catch(() => setByPurpose([]));
  }, []);

  return (
    <AdminNotificationsShell title="Notifications overview">
      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}

      <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <strong>Operational communications (Module 11A).</strong> These are transactional notifications
        (booking, payments, membership, manual) — not marketing campaigns. Delivery is simulated locally until
        transport providers ship in a later module.
      </div>

      <NotificationSummaryCards summary={summary} />

      <div className="mt-6 grid gap-4 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <Card title="Recent failures & suppressions">
            {failures.length === 0 ? (
              <p className="text-sm text-zinc-500">No failed or suppressed messages in the last 30 days.</p>
            ) : (
              <table className="w-full text-left text-sm">
                <thead>
                  <tr className="border-b text-zinc-500">
                    <th className="py-2">Client</th>
                    <th>Channel</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Reason</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {failures.map((row) => (
                    <tr key={row.message_id} className="border-b border-zinc-100">
                      <td className="py-2 font-medium">{row.client_name ?? row.recipient_address ?? '—'}</td>
                      <td>{channelLabel(row.channel)}</td>
                      <td className="text-zinc-600">{purposeLabel(row.purpose)}</td>
                      <td>
                        <NotificationStatusBadge status={row.status} />
                      </td>
                      <td className="max-w-xs truncate text-zinc-500" title={row.failure_reason ?? ''}>
                        {row.failure_reason ?? '—'}
                      </td>
                      <td>
                        <Link href={`/admin/notifications/messages/${row.message_id}`} className="text-xs text-zinc-600 underline">
                          View
                        </Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </Card>

          <div className="mt-4">
            <Card title="By purpose (30 days)">
              {byPurpose.length === 0 ? (
                <p className="text-sm text-zinc-500">No messages in the last 30 days.</p>
              ) : (
                <table className="w-full text-left text-sm">
                  <thead>
                    <tr className="border-b text-zinc-500">
                      <th className="py-2">Purpose</th>
                      <th>Total</th>
                      <th>Sent</th>
                      <th>Failed</th>
                      <th>Suppressed</th>
                    </tr>
                  </thead>
                  <tbody>
                    {byPurpose.map((row) => (
                      <tr key={row.purpose} className="border-b border-zinc-100">
                        <td className="py-2">
                          <NotificationPurposeBadge purpose={row.purpose} />
                        </td>
                        <td>{row.total}</td>
                        <td>{(row.by_status?.sent ?? 0) + (row.by_status?.delivered ?? 0)}</td>
                        <td>{row.by_status?.failed ?? 0}</td>
                        <td>{row.by_status?.suppressed ?? 0}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </Card>
          </div>
        </div>

        <div className="grid gap-4">
          <Card title="Channels (30 days)">
            {summary && Object.keys(summary.by_channel).length > 0 ? (
              <ul className="space-y-1 text-sm">
                {Object.entries(summary.by_channel).map(([channel, count]) => (
                  <li key={channel} className="flex justify-between">
                    <span className="text-zinc-600">{channelLabel(channel)}</span>
                    <span className="font-medium">{count}</span>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-sm text-zinc-500">No messages in the last 30 days.</p>
            )}
            {summary ? (
              <p className="mt-3 text-xs text-zinc-500">
                Window: {formatDateTime(summary.period.from)} → {formatDateTime(summary.period.to)}
              </p>
            ) : null}
          </Card>
          <Card title="Quick links">
            <ul className="space-y-2">
              {quickLinks.map((link) => (
                <li key={link.href}>
                  <Link href={link.href} className="block rounded-md border border-zinc-200 px-3 py-2 hover:bg-zinc-50">
                    <span className="block text-sm font-medium">{link.label}</span>
                    <span className="block text-xs text-zinc-500">{link.description}</span>
                  </Link>
                </li>
              ))}
            </ul>
          </Card>
        </div>
      </div>
    </AdminNotificationsShell>
  );
}
