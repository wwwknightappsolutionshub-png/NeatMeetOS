'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminIntegrationsShell } from '@/components/admin/integrations/AdminIntegrationsShell';
import { ProviderWebhookEventsTable } from '@/components/admin/integrations/ProviderWebhookEventsTable';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import {
  PROVIDER_DRIVERS,
  PROVIDER_WEBHOOK_PROCESSING_STATUSES,
  type ProviderWebhookEvent,
} from '@/lib/integrations-types';
import { fetchProviderWebhookEvents } from '@/services/integrations.service';

export default function ProviderWebhookEventsPage() {
  const [driver, setDriver] = useState('');
  const [processingStatus, setProcessingStatus] = useState('');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [events, setEvents] = useState<ProviderWebhookEvent[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    setError(null);
    const params: Record<string, string> = {};
    if (driver) params.driver = driver;
    if (processingStatus) params.processing_status = processingStatus;
    if (from) params.from = from;
    if (to) params.to = to;

    fetchProviderWebhookEvents(params)
      .then(setEvents)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load webhook events'))
      .finally(() => setLoading(false));
  }, [driver, processingStatus, from, to]);

  useEffect(() => {
    load();
  }, [load]);

  return (
    <AdminIntegrationsShell title="Webhook events">
      <div className="mb-4 rounded-md border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-600">
        Append-only inbound webhook storage. Events are recorded via{' '}
        <code className="text-xs">POST /api/v1/integrations/webhooks/&#123;driver&#125;</code>.
        Signature validation and automatic reconciliation into business records are deferred.
      </div>

      <div className="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Field label="Driver">
          <select className={inputClass} value={driver} onChange={(e) => setDriver(e.target.value)}>
            <option value="">All</option>
            {PROVIDER_DRIVERS.map((d) => (
              <option key={d} value={d}>{d}</option>
            ))}
          </select>
        </Field>
        <Field label="Processing status">
          <select
            className={inputClass}
            value={processingStatus}
            onChange={(e) => setProcessingStatus(e.target.value)}
          >
            <option value="">All</option>
            {PROVIDER_WEBHOOK_PROCESSING_STATUSES.map((s) => (
              <option key={s} value={s}>{s}</option>
            ))}
          </select>
        </Field>
        <Field label="From">
          <input type="date" className={inputClass} value={from} onChange={(e) => setFrom(e.target.value)} />
        </Field>
        <Field label="To">
          <input type="date" className={inputClass} value={to} onChange={(e) => setTo(e.target.value)} />
        </Field>
      </div>

      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}

      <Card title="Events">
        {loading ? <LoadingState /> : <ProviderWebhookEventsTable events={events} />}
      </Card>
    </AdminIntegrationsShell>
  );
}
