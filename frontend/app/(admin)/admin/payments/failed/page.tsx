'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { AdminPaymentsShell } from '@/components/admin/payments/AdminPaymentsShell';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import { formatMoneyCents } from '@/lib/payments-types';
import type { PaymentTransaction } from '@/lib/payments-types';
import { fetchFailedPayments } from '@/services/payments.service';

export default function FailedPaymentsPage() {
  const [payments, setPayments] = useState<PaymentTransaction[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    fetchFailedPayments()
      .then(setPayments)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  return (
    <AdminPaymentsShell title="Failed payments">
      {error ? <ErrorAlert message={error} /> : null}
      {loading ? (
        <LoadingState />
      ) : (
        <Card title="Failed transactions">
          {payments.length === 0 ? (
            <p className="text-sm text-zinc-500">No failed payments.</p>
          ) : (
            <ul className="space-y-3 text-sm">
              {payments.map((p) => (
                <li key={p.id} className="border-b border-zinc-100 pb-2">
                  <Link href={`/admin/payments/${p.id}`} className="font-medium underline">
                    {formatMoneyCents(p.amount_cents, p.currency)} — {p.transaction_type}
                  </Link>
                  <p className="text-zinc-500">
                    {p.failure_message ?? 'No message'}
                    {p.failed_at ? ` · ${new Date(p.failed_at).toLocaleString()}` : ''}
                  </p>
                </li>
              ))}
            </ul>
          )}
        </Card>
      )}
    </AdminPaymentsShell>
  );
}
