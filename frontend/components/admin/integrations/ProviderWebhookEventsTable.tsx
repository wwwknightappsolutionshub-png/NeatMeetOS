'use client';

import Link from 'next/link';
import { IntegrationsStatusBadge } from '@/components/admin/integrations/IntegrationsStatusBadge';
import { EmptyState } from '@/components/admin/ui';
import {
  driverLabel,
  formatDateTime,
  webhookProcessingStatusLabel,
  webhookProcessingStatusTone,
  type ProviderWebhookEvent,
} from '@/lib/integrations-types';

interface ProviderWebhookEventsTableProps {
  events: ProviderWebhookEvent[];
  compact?: boolean;
}

export function ProviderWebhookEventsTable({ events, compact }: ProviderWebhookEventsTableProps) {
  if (events.length === 0) {
    return <EmptyState message="No webhook events received yet." />;
  }

  return (
    <div className="overflow-x-auto">
      <table className="w-full text-left text-sm">
        <thead>
          <tr className="border-b text-zinc-500">
            <th className="py-2 pr-2">Received</th>
            <th className="pr-2">Driver</th>
            <th className="pr-2">Event</th>
            <th className="pr-2">Status</th>
            {!compact ? <th className="pr-2">External ID</th> : null}
            <th className="pr-2" />
          </tr>
        </thead>
        <tbody>
          {events.map((event) => (
            <tr key={event.id} className="border-b border-zinc-100">
              <td className="py-2 pr-2 text-zinc-600">{formatDateTime(event.received_at)}</td>
              <td className="pr-2">{driverLabel(event.driver)}</td>
              <td className="pr-2 font-medium">{event.event_type}</td>
              <td className="pr-2">
                <IntegrationsStatusBadge
                  label={webhookProcessingStatusLabel(event.processing_status)}
                  tone={webhookProcessingStatusTone(event.processing_status)}
                />
              </td>
              {!compact ? (
                <td className="max-w-xs truncate pr-2 text-zinc-500" title={event.external_event_id ?? ''}>
                  {event.external_event_id ?? '—'}
                </td>
              ) : null}
              <td className="pr-2">
                <Link
                  href={`/admin/integrations/provider-events/${event.id}`}
                  className="text-xs text-zinc-600 underline"
                >
                  View
                </Link>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
