'use client';

import { Suspense, useCallback, useEffect, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import { AdminIntegrationsShell } from '@/components/admin/integrations/AdminIntegrationsShell';
import { ProviderAttemptsTable } from '@/components/admin/integrations/ProviderAttemptsTable';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import {
  PROVIDER_ATTEMPT_STATUSES,
  PROVIDER_SOURCE_DOMAINS,
  type ProviderDeliveryAttempt,
} from '@/lib/integrations-types';
import { fetchProviderAttempts } from '@/services/integrations.service';

export default function ProviderAttemptsPage() {
  return (
    <Suspense
      fallback={
        <AdminIntegrationsShell title="Delivery attempts">
          <LoadingState />
        </AdminIntegrationsShell>
      }
    >
      <ProviderAttemptsContent />
    </Suspense>
  );
}

function ProviderAttemptsContent() {
  const searchParams = useSearchParams();
  const [sourceDomain, setSourceDomain] = useState(searchParams.get('source_domain') ?? '');
  const [status, setStatus] = useState(searchParams.get('status') ?? '');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [attempts, setAttempts] = useState<ProviderDeliveryAttempt[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    setError(null);
    const params: Record<string, string> = {};
    if (sourceDomain) params.source_domain = sourceDomain;
    if (status) params.status = status;
    if (from) params.from = from;
    if (to) params.to = to;

    fetchProviderAttempts(params)
      .then(setAttempts)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load attempts'))
      .finally(() => setLoading(false));
  }, [sourceDomain, status, from, to]);

  useEffect(() => {
    load();
  }, [load]);

  return (
    <AdminIntegrationsShell title="Delivery attempts">
      <div className="mb-4 rounded-md border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-600">
        Cross-domain operational ledger for external provider traffic. Attempts may originate from{' '}
        <strong>Notifications</strong>, <strong>Marketing</strong>, or <strong>Payments</strong>.
        Domain-specific message and payment tables remain authoritative for business state.
      </div>

      <div className="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Field label="Source domain">
          <select className={inputClass} value={sourceDomain} onChange={(e) => setSourceDomain(e.target.value)}>
            <option value="">All</option>
            {PROVIDER_SOURCE_DOMAINS.map((d) => (
              <option key={d} value={d}>{d}</option>
            ))}
          </select>
        </Field>
        <Field label="Status">
          <select className={inputClass} value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">All</option>
            {PROVIDER_ATTEMPT_STATUSES.map((s) => (
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

      <Card title="Attempts">
        {loading ? <LoadingState /> : <ProviderAttemptsTable attempts={attempts} />}
      </Card>
    </AdminIntegrationsShell>
  );
}
