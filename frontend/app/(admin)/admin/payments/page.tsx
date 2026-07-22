'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { AdminPaymentsShell } from '@/components/admin/payments/AdminPaymentsShell';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import {
  formatMoneyCents,
  PAYMENT_TRANSACTION_STATUSES,
  PAYMENT_TRANSACTION_TYPES,
} from '@/lib/payments-types';
import type { PaymentSummary, PaymentTransaction } from '@/lib/payments-types';
import { fetchPaymentSummary, fetchPayments } from '@/services/payments.service';

export default function PaymentsListPage() {
  const [payments, setPayments] = useState<PaymentTransaction[]>([]);
  const [summary, setSummary] = useState<PaymentSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [status, setStatus] = useState('');
  const [transactionType, setTransactionType] = useState('');

  const load = useCallback(() => {
    setLoading(true);
    setError(null);
    Promise.all([
      fetchPayments({
        status: status || undefined,
        transaction_type: transactionType || undefined,
      }),
      fetchPaymentSummary(),
    ])
      .then(([list, sum]) => {
        setPayments(list);
        setSummary(sum);
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load payments'))
      .finally(() => setLoading(false));
  }, [status, transactionType]);

  useEffect(() => {
    load();
  }, [load]);

  return (
    <AdminPaymentsShell title="Payments">
      {error ? <ErrorAlert message={error} /> : null}

      {summary ? (
        <div className="mb-4 grid gap-3 sm:grid-cols-3">
          <Card title="Transactions">
            <p className="text-2xl font-semibold">{summary.total_transactions}</p>
          </Card>
          <Card title="Succeeded inbound">
            <p className="text-2xl font-semibold">{formatMoneyCents(summary.succeeded_inbound_cents)}</p>
          </Card>
          <Card title="Failed">
            <p className="text-2xl font-semibold">{summary.by_status.failed ?? 0}</p>
          </Card>
        </div>
      ) : null}

      <Card title="Filters">
        <div className="flex flex-wrap gap-3">
          <Field label="Status">
            <select className={inputClass} value={status} onChange={(e) => setStatus(e.target.value)}>
              <option value="">All</option>
              {PAYMENT_TRANSACTION_STATUSES.map((s) => (
                <option key={s} value={s}>
                  {s.replace(/_/g, ' ')}
                </option>
              ))}
            </select>
          </Field>
          <Field label="Type">
            <select
              className={inputClass}
              value={transactionType}
              onChange={(e) => setTransactionType(e.target.value)}
            >
              <option value="">All</option>
              {PAYMENT_TRANSACTION_TYPES.map((t) => (
                <option key={t} value={t}>
                  {t.replace(/_/g, ' ')}
                </option>
              ))}
            </select>
          </Field>
          <div className="flex items-end">
            <Button type="button" onClick={load}>
              Apply
            </Button>
          </div>
        </div>
      </Card>

      {loading ? (
        <LoadingState />
      ) : (
        <div className="mt-4">
        <Card title="Transactions">
          {payments.length === 0 ? (
            <p className="text-sm text-zinc-500">No payments match your filters.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead>
                  <tr className="border-b border-zinc-200 text-zinc-500">
                    <th className="py-2 pr-4">Date</th>
                    <th className="py-2 pr-4">Type</th>
                    <th className="py-2 pr-4">Status</th>
                    <th className="py-2 pr-4">Amount</th>
                    <th className="py-2 pr-4">Client</th>
                    <th className="py-2 pr-4">Method</th>
                  </tr>
                </thead>
                <tbody>
                  {payments.map((p) => (
                    <tr key={p.id} className="border-b border-zinc-100">
                      <td className="py-2 pr-4">
                        <Link href={`/admin/payments/${p.id}`} className="underline">
                          {p.created_at ? new Date(p.created_at).toLocaleString() : '—'}
                        </Link>
                      </td>
                      <td className="py-2 pr-4">{p.transaction_type}</td>
                      <td className="py-2 pr-4">{p.status}</td>
                      <td className="py-2 pr-4">{formatMoneyCents(p.amount_cents, p.currency)}</td>
                      <td className="py-2 pr-4">{p.client?.resolved_display_name ?? '—'}</td>
                      <td className="py-2 pr-4">{p.payment_method_label ?? p.payment_method_type ?? '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
        </div>
      )}
    </AdminPaymentsShell>
  );
}
