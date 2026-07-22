'use client';

import { useEffect, useState } from 'react';
import { Card } from '@/components/ui/Card';
import type { Checkout, CheckoutMembershipOptions } from '@/lib/pos-types';
import { formatMoneyCents } from '@/lib/pos-types';
import {
  applyCheckoutLoyalty,
  applyCheckoutPackage,
  applyCheckoutWallet,
  fetchCheckoutMembershipOptions,
  removeCheckoutLoyalty,
  removeCheckoutPackage,
  removeCheckoutWallet,
} from '@/services/pos.service';

interface MembershipBenefitsPanelProps {
  checkout: Checkout;
  onUpdated: (checkout: Checkout) => void;
  disabled?: boolean;
}

export function MembershipBenefitsPanel({ checkout, onUpdated, disabled }: MembershipBenefitsPanelProps) {
  const [options, setOptions] = useState<CheckoutMembershipOptions | null>(null);
  const [walletAmount, setWalletAmount] = useState('');
  const [loyaltyPoints, setLoyaltyPoints] = useState('');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!checkout.client?.id) {
      setOptions(null);
      return;
    }
    fetchCheckoutMembershipOptions(checkout.id)
      .then(setOptions)
      .catch(() => setOptions(null));
  }, [checkout.id, checkout.client?.id, checkout.wallet_credit_cents, checkout.loyalty_points_redeemed, checkout.package_covered_cents]);

  if (!checkout.client) {
    return (
      <Card title="Membership benefits">
        <p className="text-sm text-zinc-500">Assign a client to apply wallet, loyalty, or package benefits.</p>
      </Card>
    );
  }

  const run = async (fn: () => Promise<Checkout>) => {
    setLoading(true);
    try {
      onUpdated(await fn());
    } finally {
      setLoading(false);
    }
  };

  const rule = options?.loyalty_redemption_rule;

  return (
    <Card title="Membership benefits">
      <div className="space-y-4 text-sm">
        <div>
          <p className="text-zinc-500">Wallet balance: {formatMoneyCents(options?.wallet_balance_cents ?? 0)}</p>
          {(checkout.wallet_credit_cents ?? 0) > 0 ? (
            <p className="text-emerald-700">Applied: {formatMoneyCents(checkout.wallet_credit_cents ?? 0)}</p>
          ) : null}
          <div className="mt-2 flex gap-2">
            <input
              type="number"
              min={1}
              placeholder="Amount (pence)"
              className="w-full rounded border border-zinc-300 px-2 py-1"
              value={walletAmount}
              onChange={(e) => setWalletAmount(e.target.value)}
              disabled={disabled || loading}
            />
            {(checkout.wallet_credit_cents ?? 0) > 0 ? (
              <button
                type="button"
                className="rounded border border-zinc-300 px-3 py-1"
                disabled={disabled || loading}
                onClick={() => run(() => removeCheckoutWallet(checkout.id))}
              >
                Remove
              </button>
            ) : (
              <button
                type="button"
                className="rounded bg-emerald-700 px-3 py-1 text-white disabled:opacity-50"
                disabled={disabled || loading || !walletAmount}
                onClick={() => run(() => applyCheckoutWallet(checkout.id, parseInt(walletAmount, 10)))}
              >
                Apply
              </button>
            )}
          </div>
        </div>

        <div>
          <p className="text-zinc-500">
            Loyalty: {options?.loyalty_points_balance ?? 0} pts
            {rule?.is_enabled
              ? ` (${formatMoneyCents(options?.loyalty_redeemable_value_cents ?? 0)} redeemable)`
              : ' (redemption disabled)'}
          </p>
          {(checkout.loyalty_points_redeemed ?? 0) > 0 ? (
            <p className="text-emerald-700">
              Redeemed: {checkout.loyalty_points_redeemed} pts (-{formatMoneyCents(checkout.loyalty_discount_cents ?? 0)})
            </p>
          ) : null}
          {rule?.is_enabled ? (
            <div className="mt-2 flex gap-2">
              <input
                type="number"
                min={rule.points_per_block}
                step={rule.points_per_block}
                placeholder={`Points (${rule.points_per_block} block)`}
                className="w-full rounded border border-zinc-300 px-2 py-1"
                value={loyaltyPoints}
                onChange={(e) => setLoyaltyPoints(e.target.value)}
                disabled={disabled || loading}
              />
              {(checkout.loyalty_points_redeemed ?? 0) > 0 ? (
                <button
                  type="button"
                  className="rounded border border-zinc-300 px-3 py-1"
                  disabled={disabled || loading}
                  onClick={() => run(() => removeCheckoutLoyalty(checkout.id))}
                >
                  Remove
                </button>
              ) : (
                <button
                  type="button"
                  className="rounded bg-emerald-700 px-3 py-1 text-white disabled:opacity-50"
                  disabled={disabled || loading || !loyaltyPoints}
                  onClick={() => run(() => applyCheckoutLoyalty(checkout.id, parseInt(loyaltyPoints, 10)))}
                >
                  Redeem
                </button>
              )}
            </div>
          ) : null}
        </div>

        {(options?.service_lines ?? []).map((line) => (
          <div key={line.line_id} className="rounded border border-zinc-200 p-2">
            <p className="font-medium">{line.description}</p>
            {line.applied_package ? (
              <p className="text-emerald-700">
                Package applied: -{formatMoneyCents(line.applied_package.covered_amount_cents)}
              </p>
            ) : line.reserved_package ? (
              <p className="text-amber-700">Reserved from booking</p>
            ) : null}
            {!line.applied_package && line.eligible_packages.length > 0 ? (
              <div className="mt-2 flex gap-2">
                <select
                  id={`pkg-${line.line_id}`}
                  className="w-full rounded border border-zinc-300 px-2 py-1"
                  disabled={disabled || loading}
                  defaultValue=""
                >
                  <option value="" disabled>
                    Select package
                  </option>
                  {line.eligible_packages.map((pkg) => (
                    <option key={pkg.id} value={pkg.id}>
                      {pkg.package_name} ({pkg.quantity_remaining} left)
                    </option>
                  ))}
                </select>
                <button
                  type="button"
                  className="rounded bg-emerald-700 px-3 py-1 text-white disabled:opacity-50"
                  disabled={disabled || loading}
                  onClick={() => {
                    const select = document.getElementById(`pkg-${line.line_id}`) as HTMLSelectElement;
                    if (select?.value) {
                      run(() => applyCheckoutPackage(checkout.id, line.line_id, select.value));
                    }
                  }}
                >
                  Apply
                </button>
              </div>
            ) : null}
            {line.applied_package ? (
              <button
                type="button"
                className="mt-2 rounded border border-zinc-300 px-3 py-1"
                disabled={disabled || loading}
                onClick={() => run(() => removeCheckoutPackage(checkout.id, line.line_id))}
              >
                Remove package
              </button>
            ) : null}
          </div>
        ))}

        {(checkout.package_covered_cents ?? 0) > 0 ? (
          <p className="text-emerald-700">Total package coverage: {formatMoneyCents(checkout.package_covered_cents ?? 0)}</p>
        ) : null}
      </div>
    </Card>
  );
}
