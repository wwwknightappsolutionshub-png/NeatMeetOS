'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminSettingsShell } from '@/components/admin/AdminSettingsShell';
import { EmptyState, ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import type { SubscriptionPlan, TenantSubscription } from '@/lib/identity-types';
import { fetchSubscription, fetchSubscriptionPlans } from '@/services/identity.service';

function formatDate(value: string | null): string {
  if (!value) return '—';
  return new Date(value).toLocaleDateString();
}

function formatPrice(cents: number | null | undefined): string {
  if (cents == null) return '—';
  return `£${(cents / 100).toFixed(2)}`;
}

export default function SubscriptionSettingsPage() {
  const [subscription, setSubscription] = useState<TenantSubscription | null>(null);
  const [plans, setPlans] = useState<SubscriptionPlan[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([fetchSubscription(), fetchSubscriptionPlans()])
      .then(([sub, available]) => {
        setSubscription(sub);
        setPlans(available);
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const plan = subscription?.plan;

  return (
    <AdminSettingsShell title="Subscription">
      {error ? <ErrorAlert message={error} /> : null}
      {loading ? <LoadingState /> : null}
      {!loading && subscription ? (
        <div className="grid gap-4 md:grid-cols-2">
          <Card title="Current plan">
            <dl className="space-y-2 text-sm">
              <div className="flex justify-between gap-4">
                <dt className="text-zinc-500">Plan</dt>
                <dd className="font-medium">{plan?.name ?? '—'}</dd>
              </div>
              <div className="flex justify-between gap-4">
                <dt className="text-zinc-500">Status</dt>
                <dd className="font-medium capitalize">{subscription.status}</dd>
              </div>
              <div className="flex justify-between gap-4">
                <dt className="text-zinc-500">Billing interval</dt>
                <dd>{subscription.billing_interval}</dd>
              </div>
              <div className="flex justify-between gap-4">
                <dt className="text-zinc-500">Trial ends</dt>
                <dd>{formatDate(subscription.trial_ends_at)}</dd>
              </div>
              <div className="flex justify-between gap-4">
                <dt className="text-zinc-500">Current period</dt>
                <dd>
                  {formatDate(subscription.current_period_start)} –{' '}
                  {formatDate(subscription.current_period_end)}
                </dd>
              </div>
              {plan?.limits ? (
                <div className="border-t border-zinc-100 pt-2">
                  <p className="mb-1 text-zinc-500">Plan limits</p>
                  <ul className="text-zinc-700">
                    {Object.entries(plan.limits).map(([key, value]) => (
                      <li key={key}>
                        {key.replace(/_/g, ' ')}: {value}
                      </li>
                    ))}
                  </ul>
                </div>
              ) : null}
            </dl>
            <p className="mt-4 text-xs text-zinc-500">
              Payment collection and plan changes are handled in a future Payments module.
            </p>
          </Card>
          <Card title="Available plans">
            {plans.length === 0 ? <EmptyState message="No plans configured." /> : null}
            <ul className="divide-y divide-zinc-100">
              {plans.map((p) => (
                <li key={p.id} className="py-3">
                  <p className="font-medium">
                    {p.name}{' '}
                    {p.id === plan?.id ? (
                      <span className="text-xs text-emerald-600">(current)</span>
                    ) : null}
                  </p>
                  <p className="text-sm text-zinc-600">{p.description}</p>
                  <p className="text-xs text-zinc-500">
                    {formatPrice(p.display_price_cents)} / {p.billing_interval}
                  </p>
                </li>
              ))}
            </ul>
          </Card>
        </div>
      ) : null}
    </AdminSettingsShell>
  );
}
