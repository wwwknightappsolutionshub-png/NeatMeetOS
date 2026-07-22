'use client';

import { useState } from 'react';
import { Card } from '@/components/ui/Card';
import type { Checkout } from '@/lib/pos-types';
import { formatMoneyCents, POS_TENDER_TYPES } from '@/lib/pos-types';

interface PaymentPanelProps {
  checkout: Checkout;
  onPay: (tenders: Array<{ amount_cents: number; payment_method_type: string; payment_method_label?: string }>) => Promise<void>;
  disabled?: boolean;
}

export function PaymentPanel({ checkout, onPay, disabled }: PaymentPanelProps) {
  const [method, setMethod] = useState<string>(POS_TENDER_TYPES[0]);
  const [amount, setAmount] = useState<string>('');
  const [splitRows, setSplitRows] = useState<Array<{ amount_cents: number; payment_method_type: string }>>([]);
  const [loading, setLoading] = useState(false);

  const due = checkout.amount_due_cents;

  return (
    <Card title="Payments">
      <div className="space-y-3 text-sm">
        <p className="font-medium">Amount due: {formatMoneyCents(due)}</p>
        {!disabled && due > 0 ? (
          <>
            <div className="flex flex-wrap gap-2">
              <select value={method} onChange={(e) => setMethod(e.target.value)} className="rounded border border-zinc-300 px-2 py-1.5">
                {POS_TENDER_TYPES.map((t) => (
                  <option key={t} value={t}>{t.replace(/_/g, ' ')}</option>
                ))}
              </select>
              <input
                type="number"
                min={1}
                placeholder="Amount (pence)"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                className="rounded border border-zinc-300 px-2 py-1.5"
              />
              <button
                type="button"
                className="rounded border border-zinc-300 px-3 py-1.5"
                onClick={() => {
                  const cents = parseInt(amount, 10);
                  if (cents > 0) {
                    setSplitRows([...splitRows, { amount_cents: cents, payment_method_type: method }]);
                    setAmount('');
                  }
                }}
              >
                Add tender
              </button>
              <button
                type="button"
                className="rounded border border-zinc-300 px-3 py-1.5"
                onClick={() => setSplitRows([{ amount_cents: due, payment_method_type: method }])}
              >
                Pay full due
              </button>
            </div>
            {splitRows.length > 0 ? (
              <ul className="space-y-1">
                {splitRows.map((row, i) => (
                  <li key={i} className="flex justify-between">
                    <span>{row.payment_method_type} · {formatMoneyCents(row.amount_cents)}</span>
                    <button type="button" className="text-red-600" onClick={() => setSplitRows(splitRows.filter((_, j) => j !== i))}>×</button>
                  </li>
                ))}
              </ul>
            ) : null}
            <button
              type="button"
              disabled={loading || splitRows.length === 0}
              className="rounded bg-zinc-900 px-4 py-2 text-white disabled:opacity-50"
              onClick={async () => {
                setLoading(true);
                try {
                  await onPay(splitRows);
                  setSplitRows([]);
                } finally {
                  setLoading(false);
                }
              }}
            >
              Record payment{splitRows.length > 1 ? 's' : ''}
            </button>
          </>
        ) : (
          <p className="text-emerald-700">{due === 0 ? 'Fully paid.' : 'Checkout is closed.'}</p>
        )}
      </div>
    </Card>
  );
}
