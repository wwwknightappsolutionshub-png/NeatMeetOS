'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useEffect, useState } from 'react';
import { AdminIntegrationsShell } from '@/components/admin/integrations/AdminIntegrationsShell';
import { IntegrationsStatusBadge } from '@/components/admin/integrations/IntegrationsStatusBadge';
import { JsonBlock } from '@/components/admin/integrations/JsonBlock';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import {
  categoryLabel,
  driverLabel,
  formatDateTime,
  sourceDomainLabel,
  sourceTypeLabel,
  webhookProcessingStatusLabel,
  webhookProcessingStatusTone,
  type ProviderWebhookEvent,
} from '@/lib/integrations-types';
import { fetchProviderWebhookEvent } from '@/services/integrations.service';

export default function ProviderWebhookEventDetailPage() {
  const params = useParams();
  const eventId = params.eventId as string;

  const [event, setEvent] = useState<ProviderWebhookEvent | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true);
    setError(null);
    fetchProviderWebhookEvent(eventId)
      .then(setEvent)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load event'))
      .finally(() => setLoading(false));
  }, [eventId]);

  return (
    <AdminIntegrationsShell title="Webhook event detail">
      <p className="mb-4">
        <Link href="/admin/integrations/provider-events" className="text-sm text-zinc-600 underline">
          ← Back to events
        </Link>
      </p>

      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}
      {loading && !event ? <LoadingState /> : null}

      {event ? (
        <div className="grid gap-4">
          <Card title="Event">
            <div className="flex flex-wrap items-center gap-3">
              <IntegrationsStatusBadge
                label={webhookProcessingStatusLabel(event.processing_status)}
                tone={webhookProcessingStatusTone(event.processing_status)}
              />
              <span className="text-sm">{driverLabel(event.driver)} · {event.event_type}</span>
            </div>
            {event.processing_error ? (
              <p className="mt-2 text-sm text-red-700">{event.processing_error}</p>
            ) : null}
          </Card>

          <Card title="Identifiers">
            <dl className="grid gap-2 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-zinc-500">External event ID</dt>
                <dd className="font-mono text-xs">{event.external_event_id ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-zinc-500">Category</dt>
                <dd>{event.category ? categoryLabel(event.category) : '—'}</dd>
              </div>
              <div>
                <dt className="text-zinc-500">Provider account</dt>
                <dd>
                  {event.provider_account_id ? (
                    <Link
                      href={`/admin/integrations/provider-accounts/${event.provider_account_id}`}
                      className="underline"
                    >
                      {event.provider_account?.name ?? event.provider_account_id}
                    </Link>
                  ) : (
                    '—'
                  )}
                </dd>
              </div>
              <div>
                <dt className="text-zinc-500">Signature valid</dt>
                <dd>
                  {event.signature_valid === null || event.signature_valid === undefined
                    ? 'Not checked'
                    : event.signature_valid
                      ? 'Yes'
                      : 'No'}
                </dd>
              </div>
            </dl>
          </Card>

          <Card title="Resolution (if mapped)">
            <dl className="grid gap-2 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-zinc-500">Source domain</dt>
                <dd>{event.resolved_source_domain ? sourceDomainLabel(event.resolved_source_domain) : '—'}</dd>
              </div>
              <div>
                <dt className="text-zinc-500">Source type</dt>
                <dd>{event.resolved_source_type ? sourceTypeLabel(event.resolved_source_type) : '—'}</dd>
              </div>
              <div>
                <dt className="text-zinc-500">Source ID</dt>
                <dd className="font-mono text-xs">{event.resolved_source_id ?? '—'}</dd>
              </div>
            </dl>
          </Card>

          <Card title="Timestamps">
            <dl className="grid gap-2 text-sm sm:grid-cols-2">
              <div><dt className="text-zinc-500">Received</dt><dd>{formatDateTime(event.received_at)}</dd></div>
              <div><dt className="text-zinc-500">Processed</dt><dd>{formatDateTime(event.processed_at)}</dd></div>
              <div><dt className="text-zinc-500">Created</dt><dd>{formatDateTime(event.created_at)}</dd></div>
            </dl>
          </Card>

          <Card title="Payload & headers">
            <div className="grid gap-4">
              <JsonBlock title="Payload" value={event.payload} />
              <JsonBlock title="Headers" value={event.headers} />
              <JsonBlock title="Metadata" value={event.metadata} />
            </div>
          </Card>
        </div>
      ) : null}
    </AdminIntegrationsShell>
  );
}
