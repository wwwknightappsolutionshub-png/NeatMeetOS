'use client';

import Link from 'next/link';
import { IntegrationsStatusBadge } from '@/components/admin/integrations/IntegrationsStatusBadge';
import { EmptyState } from '@/components/admin/ui';
import {
  attemptDirectionLabel,
  attemptDriverLabel,
  attemptStatusLabel,
  attemptStatusTone,
  attemptTransportLabel,
  attemptTransportTone,
  formatDateTime,
  isSimulationFallback,
  recipientSummary,
  sourceDomainLabel,
  sourceTypeLabel,
  type ProviderDeliveryAttempt,
} from '@/lib/integrations-types';

interface ProviderAttemptsTableProps {
  attempts: ProviderDeliveryAttempt[];
  compact?: boolean;
}

export function ProviderAttemptsTable({ attempts, compact }: ProviderAttemptsTableProps) {
  if (attempts.length === 0) {
    return <EmptyState message="No provider delivery attempts recorded yet." />;
  }

  return (
    <div className="overflow-x-auto">
      <table className="w-full text-left text-sm">
        <thead>
          <tr className="border-b text-zinc-500">
            <th className="py-2 pr-2">When</th>
            <th className="pr-2">Source</th>
            {!compact ? <th className="pr-2">Provider</th> : null}
            <th className="pr-2">Direction</th>
            <th className="pr-2">Status</th>
            {!compact ? <th className="pr-2">Recipient</th> : null}
            <th className="pr-2" />
          </tr>
        </thead>
        <tbody>
          {attempts.map((attempt) => (
            <tr key={attempt.id} className="border-b border-zinc-100">
              <td className="py-2 pr-2 text-zinc-600">{formatDateTime(attempt.created_at)}</td>
              <td className="pr-2">
                <div className="font-medium">{sourceDomainLabel(attempt.source_domain)}</div>
                <div className="text-xs text-zinc-500">
                  {sourceTypeLabel(attempt.source_type)}
                  {attempt.source_id ? ` · ${attempt.source_id.slice(0, 8)}…` : ''}
                </div>
              </td>
              {!compact ? (
                <td className="pr-2 text-zinc-600">
                  <div>{attemptDriverLabel(attempt)}</div>
                  <IntegrationsStatusBadge
                    label={attemptTransportLabel(attempt)}
                    tone={attemptTransportTone(attempt)}
                  />
                </td>
              ) : null}
              <td className="pr-2">{attemptDirectionLabel(attempt.direction)}</td>
              <td className="pr-2">
                <IntegrationsStatusBadge
                  label={attemptStatusLabel(attempt.status)}
                  tone={attemptStatusTone(attempt.status)}
                />
              </td>
              {!compact ? (
                <td className="max-w-xs truncate pr-2 text-zinc-600" title={recipientSummary(attempt)}>
                  {recipientSummary(attempt)}
                </td>
              ) : null}
              <td className="pr-2">
                <Link
                  href={`/admin/integrations/provider-attempts/${attempt.id}`}
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
