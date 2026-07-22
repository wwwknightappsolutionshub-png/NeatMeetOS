'use client';

import { useEffect, useState } from 'react';
import { Card } from '@/components/ui/Card';
import type { Checkout, CheckoutReceipt, CheckoutRefund } from '@/lib/pos-types';
import { formatMoneyCents } from '@/lib/pos-types';
import {
  createCheckoutRefund,
  fetchCheckoutRefunds,
  fetchCheckoutReceipts,
  processCheckoutReturn,
  reopenCheckout,
  resendCheckoutReceipt,
} from '@/services/pos.service';

interface CompletedCheckoutPanelProps {
  checkout: Checkout;
  onUpdated: (checkout: Checkout) => void;
}

export function CompletedCheckoutPanel({ checkout, onUpdated }: CompletedCheckoutPanelProps) {
  const [refunds, setRefunds] = useState<CheckoutRefund[]>([]);
  const [receipts, setReceipts] = useState<CheckoutReceipt[]>([]);
  const [refundAmount, setRefundAmount] = useState('');
  const [refundReason, setRefundReason] = useState('');
  const [reopenReason, setReopenReason] = useState('');
  const [emailTarget, setEmailTarget] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    fetchCheckoutRefunds(checkout.id).then(setRefunds).catch(() => {});
    fetchCheckoutReceipts(checkout.id).then(setReceipts).catch(() => {});
  }, [checkout.id]);

  const retailLine = checkout.lines?.find((l) => l.line_type === 'retail_product');

  const wrap = async (fn: () => Promise<Checkout | void>) => {
    setLoading(true);
    setError(null);
    try {
      const result = await fn();
      if (result) onUpdated(result);
      setRefunds(await fetchCheckoutRefunds(checkout.id));
      setReceipts(await fetchCheckoutReceipts(checkout.id));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Action failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="grid gap-4">
      <Card title="Completed checkout">
        <p className="text-sm text-zinc-600">
          Paid {formatMoneyCents(checkout.amount_paid_cents)} · Refunded {formatMoneyCents(checkout.refunded_total_cents ?? 0)}
        </p>
        {checkout.reopened_at ? (
          <p className="mt-2 text-sm text-amber-700">Previously reopened: {checkout.reopen_reason}</p>
        ) : null}
      </Card>

      {error ? <p className="text-sm text-red-600">{error}</p> : null}

      <Card title="Refunds">
        {refunds.length === 0 ? <p className="text-sm text-zinc-500">No refunds recorded.</p> : (
          <ul className="space-y-1 text-sm">
            {refunds.map((r) => (
              <li key={r.id}>{formatMoneyCents(r.amount_cents)} · {r.reason}</li>
            ))}
          </ul>
        )}
        {(checkout.refunded_total_cents ?? 0) < checkout.amount_paid_cents ? (
          <div className="mt-3 flex flex-wrap gap-2 text-sm">
            <input value={refundAmount} onChange={(e) => setRefundAmount(e.target.value)} placeholder="Amount (pence)" className="rounded border px-2 py-1" />
            <input value={refundReason} onChange={(e) => setRefundReason(e.target.value)} placeholder="Reason" className="rounded border px-2 py-1" />
            <button
              type="button"
              disabled={loading || !refundReason}
              className="rounded bg-zinc-900 px-3 py-1 text-white disabled:opacity-50"
              onClick={() => wrap(async () => {
                const res = await createCheckoutRefund(checkout.id, {
                  amount_cents: refundAmount ? parseInt(refundAmount, 10) : undefined,
                  reason: refundReason,
                });
                onUpdated(res.checkout);
              })}
            >
              Refund
            </button>
          </div>
        ) : null}
      </Card>

      {retailLine && retailLine.return_status !== 'returned' ? (
        <Card title="Return retail line">
          <button
            type="button"
            disabled={loading}
            className="rounded border px-3 py-1 text-sm"
            onClick={() => wrap(() => processCheckoutReturn(checkout.id, {
              line_id: retailLine.id,
              quantity: 1,
              reason: 'Customer return',
              refund_immediately: true,
            }))}
          >
            Return 1 unit + refund
          </button>
        </Card>
      ) : null}

      {!checkout.reopened_at && (checkout.refunded_total_cents ?? 0) === 0 ? (
        <Card title="Reopen checkout">
          <div className="flex flex-wrap gap-2 text-sm">
            <input value={reopenReason} onChange={(e) => setReopenReason(e.target.value)} placeholder="Reason" className="rounded border px-2 py-1" />
            <button
              type="button"
              disabled={loading || !reopenReason}
              className="rounded border px-3 py-1 disabled:opacity-50"
              onClick={() => wrap(() => reopenCheckout(checkout.id, reopenReason))}
            >
              Reopen
            </button>
          </div>
        </Card>
      ) : null}

      <Card title="Receipts">
        {receipts.length === 0 ? <p className="text-sm text-zinc-500">No receipts yet.</p> : (
          <ul className="mb-3 space-y-1 text-sm">
            {receipts.map((r) => (
              <li key={r.id}>{r.receipt_number} · {r.delivery_method} · {r.delivery_status}</li>
            ))}
          </ul>
        )}
        <div className="flex flex-wrap gap-2 text-sm">
          <input value={emailTarget} onChange={(e) => setEmailTarget(e.target.value)} placeholder="Email" className="rounded border px-2 py-1" />
          <button
            type="button"
            disabled={loading || !emailTarget}
            className="rounded bg-zinc-900 px-3 py-1 text-white disabled:opacity-50"
            onClick={() => wrap(async () => {
              await resendCheckoutReceipt(checkout.id, { delivery_method: 'email', delivery_target: emailTarget });
            })}
          >
            Resend email
          </button>
        </div>
      </Card>
    </div>
  );
}
