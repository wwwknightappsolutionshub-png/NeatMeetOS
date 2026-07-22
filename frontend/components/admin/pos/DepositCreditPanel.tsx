'use client';

import { useState } from 'react';
import { Card } from '@/components/ui/Card';
import type { Checkout } from '@/lib/pos-types';
import { formatMoneyCents } from '@/lib/pos-types';

interface DepositCreditPanelProps {
  checkout: Checkout;
  onApply: () => Promise<void>;
  onRemove?: () => Promise<void>;
  disabled?: boolean;
}

export function DepositCreditPanel({ checkout, onApply, onRemove, disabled }: DepositCreditPanelProps) {
  const [loading, setLoading] = useState(false);
  const available = checkout.available_deposit_credit ?? [];
  const hasApplied = (checkout.lines ?? []).some((l) => l.line_type === 'deposit_credit');

  return (
    <Card title="Deposit credit">
      {available.length === 0 && !hasApplied ? (
        <p className="text-sm text-zinc-500">No collected deposit credit available for linked appointments.</p>
      ) : (
        <div className="space-y-3 text-sm">
          {available.map((d) => (
            <p key={d.deposit_record_id}>Available: {formatMoneyCents(d.available_cents)}</p>
          ))}
          {hasApplied ? (
            <p className="text-emerald-700">Deposit credit applied: {formatMoneyCents(checkout.deposit_credit_cents)}</p>
          ) : null}
          <div className="flex gap-2">
            {!hasApplied && available.length > 0 ? (
              <button
                type="button"
                disabled={disabled || loading}
                className="rounded bg-emerald-700 px-3 py-1.5 text-white disabled:opacity-50"
                onClick={async () => {
                  setLoading(true);
                  try {
                    await onApply();
                  } finally {
                    setLoading(false);
                  }
                }}
              >
                Apply deposit credit
              </button>
            ) : null}
            {hasApplied && onRemove ? (
              <button
                type="button"
                disabled={disabled || loading}
                className="rounded border border-zinc-300 px-3 py-1.5 disabled:opacity-50"
                onClick={async () => {
                  setLoading(true);
                  try {
                    await onRemove();
                  } finally {
                    setLoading(false);
                  }
                }}
              >
                Remove credit
              </button>
            ) : null}
          </div>
        </div>
      )}
    </Card>
  );
}
