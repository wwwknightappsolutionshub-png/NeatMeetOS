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
  attemptDirectionLabel,
  attemptDriverLabel,
  attemptStatusLabel,
  attemptStatusTone,
  attemptTransportLabel,
  attemptTransportTone,
  categoryLabel,
  formatDateTime,
  isSimulationFallback,
  recipientSummary,
  sourceDomainLabel,
  sourceTypeLabel,
  type ProviderDeliveryAttempt,
} from '@/lib/integrations-types';
import { fetchProviderAttempt, retryProviderAttempt } from '@/services/integrations.service';

export default function ProviderAttemptDetailPage() {
  const params = useParams();
  const attemptId = params.attemptId as string;

  const [attempt, setAttempt] = useState<ProviderDeliveryAttempt | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [retrying, setRetrying] = useState(false);

  useEffect(() => {
    setLoading(true);
    setError(null);
    fetchProviderAttempt(attemptId)
      .then(setAttempt)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load attempt'))
      .finally(() => setLoading(false));
  }, [attemptId]);

  async function handleRetry() {
    if (!attempt || attempt.status !== 'failed') return;
    setRetrying(true);
    setError(null);
    try {
      const result = await retryProviderAttempt(attempt.id);
      setAttempt(result.attempt);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Retry failed');
    } finally {
      setRetrying(false);
    }
  }

  return (
    <AdminIntegrationsShell title="Delivery attempt detail">
      <p className="mb-4">
        <Link href="/admin/integrations/provider-attempts" className="text-sm text-zinc-600 underline">
          ← Back to attempts
        </Link>
      </p>

      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}
      {loading && !attempt ? <LoadingState /> : null}

      {attempt ? (
        <div className="grid gap-4">
          <Card title="Status">
            <div className="flex flex-wrap items-center gap-3">
              <IntegrationsStatusBadge
                label={attemptStatusLabel(attempt.status)}
                tone={attemptStatusTone(attempt.status)}
              />
              <IntegrationsStatusBadge
                label={attemptTransportLabel(attempt)}
                tone={attemptTransportTone(attempt)}
              />
              <span className="text-sm text-zinc-600">{attemptDirectionLabel(attempt.direction)}</span>
              {isSimulationFallback(attempt) ? (
                <span className="text-xs text-amber-700">
                  Simulation fallback
                  {typeof attempt.metadata?.live_fallback_reason === 'string'
                    ? ` (${attempt.metadata.live_fallback_reason})`
                    : ''}
                </span>
              ) : null}
            </div>
            {attempt.status === 'failed' && attempt.failure_message ? (
              <p className="mt-2 text-sm text-red-700">{attempt.failure_message}</p>
            ) : null}
            {attempt.status === 'failed' ? (
              <button
                type="button"
                disabled={retrying}
                onClick={handleRetry}
                className="mt-3 rounded-md border border-zinc-300 px-3 py-1.5 text-sm hover:bg-zinc-50 disabled:opacity-50"
              >
                {retrying ? 'Retrying…' : 'Retry (simulation only)'}
              </button>
            ) : null}
          </Card>

          <Card title="Source reference">
            <dl className="grid gap-2 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-zinc-500">Domain</dt>
                <dd>{sourceDomainLabel(attempt.source_domain)}</dd>
              </div>
              <div>
                <dt className="text-zinc-500">Type</dt>
                <dd>{sourceTypeLabel(attempt.source_type)}</dd>
              </div>
              <div>
                <dt className="text-zinc-500">Source ID</dt>
                <dd className="font-mono text-xs">{attempt.source_id ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-zinc-500">Purpose</dt>
                <dd>{attempt.purpose ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-zinc-500">Category</dt>
                <dd>{categoryLabel(attempt.category)}</dd>
              </div>
              <div>
                <dt className="text-zinc-500">Provider / driver</dt>
                <dd>{attemptDriverLabel(attempt)}</dd>
              </div>
              <div>
                <dt className="text-zinc-500">Provider account</dt>
                <dd>
                  {attempt.provider_account_id ? (
                    <Link
                      href={`/admin/integrations/provider-accounts/${attempt.provider_account_id}`}
                      className="underline"
                    >
                      {attempt.provider_account?.name ?? attempt.provider_account_id}
                    </Link>
                  ) : (
                    '— (implicit simulation)'
                  )}
                </dd>
              </div>
              <div>
                <dt className="text-zinc-500">Remote status</dt>
                <dd>{typeof attempt.metadata?.remote_status === 'string' ? attempt.metadata.remote_status : '—'}</dd>
              </div>
              <div>
                <dt className="text-zinc-500">Provider reference</dt>
                <dd className="font-mono text-xs">{attempt.provider_reference ?? '—'}</dd>
              </div>
            </dl>
          </Card>

          <Card title="Recipient & content">
            <dl className="grid gap-2 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-zinc-500">Recipient</dt>
                <dd>{recipientSummary(attempt)}</dd>
              </div>
              <div>
                <dt className="text-zinc-500">Client</dt>
                <dd>{attempt.related_client?.name ?? attempt.related_client_id ?? '—'}</dd>
              </div>
              <div className="sm:col-span-2">
                <dt className="text-zinc-500">Subject</dt>
                <dd>{attempt.subject ?? '—'}</dd>
              </div>
            </dl>
          </Card>

          <Card title="Timestamps">
            <dl className="grid gap-2 text-sm sm:grid-cols-2">
              <div><dt className="text-zinc-500">Requested</dt><dd>{formatDateTime(attempt.requested_at)}</dd></div>
              <div><dt className="text-zinc-500">Sent</dt><dd>{formatDateTime(attempt.sent_at)}</dd></div>
              <div><dt className="text-zinc-500">Delivered</dt><dd>{formatDateTime(attempt.delivered_at)}</dd></div>
              <div><dt className="text-zinc-500">Failed</dt><dd>{formatDateTime(attempt.failed_at)}</dd></div>
              <div><dt className="text-zinc-500">Created</dt><dd>{formatDateTime(attempt.created_at)}</dd></div>
            </dl>
          </Card>

          <Card title="Payload & metadata">
            <div className="grid gap-4">
              <JsonBlock title="Payload" value={attempt.payload} />
              <JsonBlock title="Metadata" value={attempt.metadata} />
            </div>
          </Card>
        </div>
      ) : null}
    </AdminIntegrationsShell>
  );
}
