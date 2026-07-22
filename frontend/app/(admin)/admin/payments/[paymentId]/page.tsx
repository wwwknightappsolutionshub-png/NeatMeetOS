'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { AdminPaymentsShell } from '@/components/admin/payments/AdminPaymentsShell';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { formatMoneyCents } from '@/lib/payments-types';
import type { PaymentTransaction } from '@/lib/payments-types';
import {
  cancelPayment,
  createPaymentRefund,
  fetchPayment,
  markPaymentFailed,
  markPaymentSucceeded,
} from '@/services/payments.service';

export default function PaymentDetailPage() {
  const params = useParams();
  const paymentId = params.paymentId as string;
  const [payment, setPayment] = useState<PaymentTransaction | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    fetchPayment(paymentId)
      .then(setPayment)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load payment'))
      .finally(() => setLoading(false));
  }, [paymentId]);

  useEffect(() => {
    load();
  }, [load]);

  if (loading && !payment) {
    return (
      <AdminPaymentsShell title="Payment">
        <LoadingState />
      </AdminPaymentsShell>
    );
  }

  return (
    <AdminPaymentsShell title={payment ? formatMoneyCents(payment.amount_cents, payment.currency) : 'Payment'}>
      <p className="mb-4 text-sm">
        <Link href="/admin/payments" className="text-zinc-600 hover:underline">
          ← Back to payments
        </Link>
      </p>
      {error ? <ErrorAlert message={error} /> : null}

      {payment ? (
        <div className="grid gap-4 md:grid-cols-2">
          <Card title="Transaction">
            <dl className="space-y-2 text-sm">
              <div>
                <dt className="text-zinc-500">Status</dt>
                <dd>{payment.status}</dd>
              </div>
              <div>
                <dt className="text-zinc-500">Type</dt>
                <dd>{payment.transaction_type} ({payment.direction})</dd>
              </div>
              <div>
                <dt className="text-zinc-500">Provider</dt>
                <dd>{payment.provider ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-zinc-500">Method</dt>
                <dd>{payment.payment_method_label ?? payment.payment_method_type ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-zinc-500">Processed</dt>
                <dd>{payment.processed_at ? new Date(payment.processed_at).toLocaleString() : '—'}</dd>
              </div>
              {payment.failure_message ? (
                <div>
                  <dt className="text-zinc-500">Failure</dt>
                  <dd>
                    {payment.failure_code ? `${payment.failure_code}: ` : ''}
                    {payment.failure_message}
                  </dd>
                </div>
              ) : null}
              {payment.appointment_id ? (
                <div>
                  <dt className="text-zinc-500">Appointment</dt>
                  <dd>
                    <Link href={`/admin/bookings/${payment.appointment_id}`} className="underline">
                      {payment.appointment?.booking_reference ?? payment.appointment_id}
                    </Link>
                  </dd>
                </div>
              ) : null}
              {payment.client ? (
                <div>
                  <dt className="text-zinc-500">Client</dt>
                  <dd>{payment.client.resolved_display_name}</dd>
                </div>
              ) : null}
              <div>
                <dt className="text-zinc-500">Refundable</dt>
                <dd>{formatMoneyCents(payment.refundable_amount_cents ?? 0, payment.currency)}</dd>
              </div>
            </dl>
          </Card>

          <Card title="Actions">
            <div className="flex flex-wrap gap-2">
              {payment.status === 'pending' ? (
                <>
                  <Button type="button" onClick={() => markPaymentSucceeded(paymentId).then(load)}>
                    Mark succeeded
                  </Button>
                  <Button
                    type="button"
                    variant="secondary"
                    onClick={() =>
                      markPaymentFailed(paymentId, { failure_message: 'Marked failed from admin' }).then(load)
                    }
                  >
                    Mark failed
                  </Button>
                  <Button type="button" variant="secondary" onClick={() => cancelPayment(paymentId).then(load)}>
                    Cancel
                  </Button>
                </>
              ) : null}
              {(payment.refundable_amount_cents ?? 0) > 0 ? (
                <Button
                  type="button"
                  variant="secondary"
                  onClick={() =>
                    createPaymentRefund(paymentId, { reason: 'Admin refund' }).then(load).catch((e) =>
                      setError(e instanceof Error ? e.message : 'Refund failed'),
                    )
                  }
                >
                  Refund full balance
                </Button>
              ) : null}
            </div>
          </Card>

          {payment.allocations && payment.allocations.length > 0 ? (
            <Card title="Allocations">
              <ul className="space-y-2 text-sm">
                {payment.allocations.map((a) => (
                  <li key={a.id}>
                    {a.allocation_type}: {formatMoneyCents(a.amount_cents, payment.currency)}
                    {a.notes ? ` — ${a.notes}` : ''}
                  </li>
                ))}
              </ul>
            </Card>
          ) : null}

          {payment.refunds && payment.refunds.length > 0 ? (
            <Card title="Refunds">
              <ul className="space-y-2 text-sm">
                {payment.refunds.map((r) => (
                  <li key={r.id}>
                    {formatMoneyCents(r.amount_cents, payment.currency)} — {r.status}
                    {r.reason ? ` (${r.reason})` : ''}
                  </li>
                ))}
              </ul>
            </Card>
          ) : null}
        </div>
      ) : null}
    </AdminPaymentsShell>
  );
}
