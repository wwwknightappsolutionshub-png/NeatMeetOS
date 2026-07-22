'use client';

import { Card } from '@/components/ui/Card';
import type { Checkout } from '@/lib/pos-types';
import { formatMoneyCents } from '@/lib/pos-types';

interface CheckoutTotalsCardProps {
  checkout: Checkout;
}

export function CheckoutTotalsCard({ checkout }: CheckoutTotalsCardProps) {
  return (
    <Card title="Totals">
      <dl className="grid gap-2 text-sm">
        <div className="flex justify-between"><dt className="text-zinc-500">Subtotal</dt><dd>{formatMoneyCents(checkout.subtotal_cents)}</dd></div>
        {checkout.discount_cents > 0 ? (
          <div className="flex justify-between"><dt className="text-zinc-500">Discount</dt><dd>-{formatMoneyCents(checkout.discount_cents)}</dd></div>
        ) : null}
        {checkout.deposit_credit_cents > 0 ? (
          <div className="flex justify-between text-emerald-700"><dt>Deposit credit</dt><dd>-{formatMoneyCents(checkout.deposit_credit_cents)}</dd></div>
        ) : null}
        {(checkout.gift_card_redemption_cents ?? 0) > 0 ? (
          <div className="flex justify-between text-emerald-700"><dt>Gift card</dt><dd>-{formatMoneyCents(checkout.gift_card_redemption_cents ?? 0)}</dd></div>
        ) : null}
        {(checkout.package_covered_cents ?? 0) > 0 ? (
          <div className="flex justify-between text-emerald-700"><dt>Package coverage</dt><dd>-{formatMoneyCents(checkout.package_covered_cents ?? 0)}</dd></div>
        ) : null}
        {(checkout.loyalty_discount_cents ?? 0) > 0 ? (
          <div className="flex justify-between text-emerald-700"><dt>Loyalty ({checkout.loyalty_points_redeemed ?? 0} pts)</dt><dd>-{formatMoneyCents(checkout.loyalty_discount_cents ?? 0)}</dd></div>
        ) : null}
        {(checkout.wallet_credit_cents ?? 0) > 0 ? (
          <div className="flex justify-between text-emerald-700"><dt>Wallet credit</dt><dd>-{formatMoneyCents(checkout.wallet_credit_cents ?? 0)}</dd></div>
        ) : null}
        <div className="flex justify-between border-t border-zinc-200 pt-2 font-medium">
          <dt>Total</dt><dd>{formatMoneyCents(checkout.total_cents)}</dd>
        </div>
        <div className="flex justify-between"><dt className="text-zinc-500">Paid</dt><dd>{formatMoneyCents(checkout.amount_paid_cents)}</dd></div>
        <div className="flex justify-between text-lg font-semibold">
          <dt>Amount due</dt><dd>{formatMoneyCents(checkout.amount_due_cents)}</dd>
        </div>
      </dl>
    </Card>
  );
}
