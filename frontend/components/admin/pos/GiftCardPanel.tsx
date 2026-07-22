'use client';

import { useState } from 'react';
import { Card } from '@/components/ui/Card';
import type { Checkout } from '@/lib/pos-types';
import { formatMoneyCents } from '@/lib/pos-types';

interface GiftCardSalePanelProps {
  disabled?: boolean;
  onAdd: (data: { amount_cents: number; description?: string }) => Promise<void>;
}

export function GiftCardSalePanel({ disabled, onAdd }: GiftCardSalePanelProps) {
  const [amount, setAmount] = useState('');
  const [description, setDescription] = useState('Gift card');
  const [loading, setLoading] = useState(false);

  return (
    <Card title="Sell gift card">
      <form
        className="flex flex-wrap gap-2 text-sm"
        onSubmit={async (e) => {
          e.preventDefault();
          const amountCents = parseInt(amount, 10);
          if (!amountCents || amountCents <= 0) return;
          setLoading(true);
          try {
            await onAdd({ amount_cents: amountCents, description: description || undefined });
            setAmount('');
          } finally {
            setLoading(false);
          }
        }}
      >
        <input
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          placeholder="Description"
          className="rounded border border-zinc-300 px-2 py-1.5"
        />
        <input
          value={amount}
          onChange={(e) => setAmount(e.target.value)}
          placeholder="Value (pence)"
          className="rounded border border-zinc-300 px-2 py-1.5"
        />
        <button type="submit" disabled={disabled || loading || !amount} className="rounded bg-zinc-900 px-3 py-1.5 text-white disabled:opacity-50">
          Add gift card line
        </button>
      </form>
    </Card>
  );
}

interface GiftCardRedemptionPanelProps {
  checkout: Checkout;
  disabled?: boolean;
  onApply: (code: string, amountCents?: number) => Promise<void>;
}

export function GiftCardRedemptionPanel({ checkout, disabled, onApply }: GiftCardRedemptionPanelProps) {
  const [code, setCode] = useState('');
  const [amount, setAmount] = useState('');
  const [loading, setLoading] = useState(false);

  return (
    <Card title="Redeem gift card">
      {(checkout.gift_card_redemption_cents ?? 0) > 0 ? (
        <p className="mb-2 text-sm text-emerald-700">
          Applied: {formatMoneyCents(checkout.gift_card_redemption_cents ?? 0)}
        </p>
      ) : null}
      <form
        className="flex flex-wrap gap-2 text-sm"
        onSubmit={async (e) => {
          e.preventDefault();
          if (!code.trim()) return;
          setLoading(true);
          try {
            const amountCents = amount ? parseInt(amount, 10) : undefined;
            await onApply(code.trim(), amountCents);
            setCode('');
            setAmount('');
          } finally {
            setLoading(false);
          }
        }}
      >
        <input
          value={code}
          onChange={(e) => setCode(e.target.value)}
          placeholder="Gift card code"
          className="rounded border border-zinc-300 px-2 py-1.5"
        />
        <input
          value={amount}
          onChange={(e) => setAmount(e.target.value)}
          placeholder="Amount (pence, optional)"
          className="rounded border border-zinc-300 px-2 py-1.5"
        />
        <button type="submit" disabled={disabled || loading || !code.trim()} className="rounded bg-zinc-900 px-3 py-1.5 text-white disabled:opacity-50">
          Apply
        </button>
      </form>
    </Card>
  );
}
