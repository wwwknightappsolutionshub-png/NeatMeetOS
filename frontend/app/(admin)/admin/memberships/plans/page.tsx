'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminMembershipsShell } from '@/components/admin/memberships/AdminMembershipsShell';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import {
  MEMBERSHIP_BILLING_FREQUENCIES,
  formatMoneyCents,
  type MembershipPlan,
} from '@/lib/memberships-types';
import {
  archiveMembershipPlan,
  createMembershipPlan,
  fetchMembershipPlans,
  updateMembershipPlan,
} from '@/services/memberships.service';

function poundsToCents(value: string): number {
  const n = Number.parseFloat(value);
  if (Number.isNaN(n)) return 0;
  return Math.round(n * 100);
}

function centsToPounds(cents: number | null | undefined): string {
  if (cents == null) return '';
  return (cents / 100).toFixed(2);
}

export default function MembershipPlansPage() {
  const [plans, setPlans] = useState<MembershipPlan[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [name, setName] = useState('');
  const [price, setPrice] = useState('');
  const [frequency, setFrequency] = useState('monthly');
  const [walletCredit, setWalletCredit] = useState('');
  const [loyaltyPoints, setLoyaltyPoints] = useState('');
  const [isPublic, setIsPublic] = useState(true);

  const load = useCallback(() => {
    setLoading(true);
    fetchMembershipPlans()
      .then(setPlans)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  function resetForm() {
    setEditingId(null);
    setName('');
    setPrice('');
    setFrequency('monthly');
    setWalletCredit('');
    setLoyaltyPoints('');
    setIsPublic(true);
  }

  function startEdit(plan: MembershipPlan) {
    setError(null);
    setEditingId(plan.id);
    setName(plan.name);
    setPrice(centsToPounds(plan.price_cents));
    setFrequency(plan.billing_frequency || 'monthly');
    setWalletCredit(centsToPounds(plan.included_wallet_credit_cents));
    setLoyaltyPoints(
      plan.included_loyalty_points != null ? String(plan.included_loyalty_points) : '',
    );
    setIsPublic(Boolean(plan.is_public));
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSaving(true);
    const payload = {
      name,
      billing_frequency: frequency,
      price_cents: poundsToCents(price),
      included_wallet_credit_cents: walletCredit ? poundsToCents(walletCredit) : 0,
      included_loyalty_points: loyaltyPoints ? parseInt(loyaltyPoints, 10) : 0,
      is_public: isPublic,
    };
    try {
      if (editingId) {
        await updateMembershipPlan(editingId, payload);
      } else {
        await createMembershipPlan(payload);
      }
      resetForm();
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : editingId ? 'Update failed' : 'Create failed');
    } finally {
      setSaving(false);
    }
  }

  async function handleArchive(plan: MembershipPlan) {
    if (!window.confirm(`Archive “${plan.name}”?`)) return;
    setError(null);
    try {
      await archiveMembershipPlan(plan.id);
      if (editingId === plan.id) resetForm();
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Archive failed');
    }
  }

  return (
    <AdminMembershipsShell title="Memberships">
      <p className="mb-4 text-sm text-zinc-600">
        Recurring offers clients can enroll in. After creating one, enroll a client under{' '}
        <a href="/admin/memberships/subscriptions" className="font-medium underline">
          Client benefits → Enroll members
        </a>
        .
      </p>
      {error ? <ErrorAlert message={error} /> : null}
      <div className="mb-4 grid gap-4 md:grid-cols-2">
        <Card title={editingId ? 'Edit membership' : 'New membership'}>
          <form onSubmit={(e) => void handleSubmit(e)} className="grid gap-2">
            <Field label="Name">
              <input
                className={inputClass}
                value={name}
                onChange={(e) => setName(e.target.value)}
                required
              />
            </Field>
            <Field label="Price (Pound)">
              <input
                className={inputClass}
                type="number"
                min="0"
                step="0.01"
                value={price}
                onChange={(e) => setPrice(e.target.value)}
                required
                placeholder="e.g. 29.00"
              />
            </Field>
            <Field label="Billing frequency">
              <select
                className={inputClass}
                value={frequency}
                onChange={(e) => setFrequency(e.target.value)}
              >
                {MEMBERSHIP_BILLING_FREQUENCIES.map((f) => (
                  <option key={f} value={f}>
                    {f}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Included store credit (Pound)">
              <input
                className={inputClass}
                type="number"
                min="0"
                step="0.01"
                value={walletCredit}
                onChange={(e) => setWalletCredit(e.target.value)}
                placeholder="e.g. 10.00"
              />
            </Field>
            <Field label="Included loyalty points">
              <input
                className={inputClass}
                value={loyaltyPoints}
                onChange={(e) => setLoyaltyPoints(e.target.value)}
              />
            </Field>
            <label className="flex items-center gap-2 text-sm text-zinc-700">
              <input
                type="checkbox"
                checked={isPublic}
                onChange={(e) => setIsPublic(e.target.checked)}
              />
              Show on public memberships page
            </label>
            <div className="flex flex-wrap gap-2">
              <Button type="submit" disabled={saving}>
                {saving ? 'Saving…' : editingId ? 'Update membership' : 'Create membership'}
              </Button>
              {editingId ? (
                <Button type="button" variant="secondary" onClick={resetForm}>
                  Cancel
                </Button>
              ) : null}
            </div>
          </form>
        </Card>
      </div>
      {loading ? (
        <LoadingState />
      ) : (
        <Card title="Your memberships">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="border-b text-zinc-500">
                <th className="py-2">Name</th>
                <th>Frequency</th>
                <th>Price</th>
                <th>Benefits</th>
                <th>Public</th>
                <th>Status</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {plans.map((p) => (
                <tr key={p.id} className="border-b border-zinc-100">
                  <td className="py-2 font-medium">{p.name}</td>
                  <td>{p.billing_frequency}</td>
                  <td>{formatMoneyCents(p.price_cents)}</td>
                  <td className="text-zinc-600">
                    {(p.included_wallet_credit_cents ?? 0) > 0
                      ? `Credit ${formatMoneyCents(p.included_wallet_credit_cents!)}`
                      : null}
                    {(p.included_loyalty_points ?? 0) > 0
                      ? ` · ${p.included_loyalty_points} pts`
                      : null}
                    {(p.included_wallet_credit_cents ?? 0) === 0 &&
                    (p.included_loyalty_points ?? 0) === 0
                      ? '—'
                      : null}
                  </td>
                  <td>{p.is_public ? 'Yes' : 'No'}</td>
                  <td>{p.status}</td>
                  <td className="space-x-3 whitespace-nowrap">
                    {p.status !== 'archived' ? (
                      <>
                        <button
                          type="button"
                          className="text-xs text-zinc-600 underline"
                          onClick={() => startEdit(p)}
                        >
                          Edit
                        </button>
                        <button
                          type="button"
                          className="text-xs text-zinc-600 underline"
                          onClick={() => void handleArchive(p)}
                        >
                          Archive
                        </button>
                      </>
                    ) : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </AdminMembershipsShell>
  );
}
