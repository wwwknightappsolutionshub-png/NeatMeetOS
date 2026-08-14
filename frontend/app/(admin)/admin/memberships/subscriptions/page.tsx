'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminMembershipsShell } from '@/components/admin/memberships/AdminMembershipsShell';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { Client } from '@/lib/crm-types';
import { formatMoneyCents, type ClientMembership, type MembershipPlan } from '@/lib/memberships-types';
import { fetchClients } from '@/services/crm.service';
import {
  cancelClientMembership,
  createClientMembership,
  fetchClientMemberships,
  fetchMembershipPlans,
  pauseClientMembership,
  resumeClientMembership,
} from '@/services/memberships.service';

export default function SubscriptionsPage() {
  const [subs, setSubs] = useState<ClientMembership[]>([]);
  const [plans, setPlans] = useState<MembershipPlan[]>([]);
  const [clients, setClients] = useState<Client[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [clientId, setClientId] = useState('');
  const [planId, setPlanId] = useState('');

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([fetchClientMemberships(), fetchMembershipPlans(), fetchClients()])
      .then(([s, p, c]) => { setSubs(s); setPlans(p.filter((x) => x.status === 'active')); setClients(c.items); })
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  async function handleAssign(e: React.FormEvent) {
    e.preventDefault();
    if (!clientId || !planId) return;
    setError(null);
    try {
      await createClientMembership({ client_id: clientId, membership_plan_id: planId });
      setClientId('');
      setPlanId('');
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Assign failed');
    }
  }

  return (
    <AdminMembershipsShell title="Enroll members">
      <p className="mb-4 text-sm text-zinc-600">
        Put a client on a membership. Use Pause / Resume / Cancel when they renew or leave. Need a
        membership first?{' '}
        <a href="/admin/memberships/plans" className="font-medium underline">
          Create one under Offers
        </a>
        .
      </p>
      {error ? <ErrorAlert message={error} /> : null}
      <div className="mb-4">
        <Card title="Enroll a client">
        <form onSubmit={handleAssign} className="flex flex-wrap gap-2">
          <Field label="Client">
            <select className={inputClass} value={clientId} onChange={(e) => setClientId(e.target.value)} required>
              <option value="">Select client</option>
              {clients.map((c) => <option key={c.id} value={c.id}>{c.resolved_display_name}</option>)}
            </select>
          </Field>
          <Field label="Membership">
            <select className={inputClass} value={planId} onChange={(e) => setPlanId(e.target.value)} required>
              <option value="">Select membership</option>
              {plans.map((p) => <option key={p.id} value={p.id}>{p.name} · {formatMoneyCents(p.price_cents)}</option>)}
            </select>
          </Field>
          <div className="flex items-end"><Button type="submit">Enroll</Button></div>
        </form>
        </Card>
      </div>
      {loading ? <LoadingState /> : (
        <Card title="Active & past members">
          <table className="w-full text-left text-sm">
            <thead><tr className="border-b text-zinc-500"><th className="py-2">Client</th><th>Membership</th><th>Status</th><th>Renews / ends</th><th>Actions</th></tr></thead>
            <tbody>
              {subs.map((s) => (
                <tr key={s.id} className="border-b border-zinc-100">
                  <td className="py-2">{s.client_name}</td>
                  <td>{s.plan_name}</td>
                  <td>{s.status}</td>
                  <td>{s.current_period_ends_at ? new Date(s.current_period_ends_at).toLocaleDateString() : '—'}</td>
                  <td className="flex gap-2">
                    {s.status === 'active' ? <button type="button" className="text-xs underline" onClick={() => pauseClientMembership(s.id).then(load)}>Pause</button> : null}
                    {s.status === 'paused' ? <button type="button" className="text-xs underline" onClick={() => resumeClientMembership(s.id).then(load)}>Resume</button> : null}
                    {!['cancelled', 'expired'].includes(s.status) ? <button type="button" className="text-xs underline" onClick={() => cancelClientMembership(s.id).then(load)}>Cancel</button> : null}
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
