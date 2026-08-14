'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminMembershipsShell } from '@/components/admin/memberships/AdminMembershipsShell';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { Client } from '@/lib/crm-types';
import type { LoyaltyEntry } from '@/lib/memberships-types';
import { fetchClients } from '@/services/crm.service';
import { createLoyaltyEntry, fetchClientLoyalty, fetchLoyaltyEntries } from '@/services/memberships.service';

export default function LoyaltyPage() {
  const [entries, setEntries] = useState<LoyaltyEntry[]>([]);
  const [clients, setClients] = useState<Client[]>([]);
  const [selectedClient, setSelectedClient] = useState('');
  const [balance, setBalance] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [points, setPoints] = useState('');
  const [direction, setDirection] = useState<'credit' | 'debit'>('credit');

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([fetchLoyaltyEntries(selectedClient || undefined), fetchClients()])
      .then(([e, c]) => { setEntries(e); setClients(c.items); })
      .catch((err) => setError(err instanceof Error ? err.message : 'Failed'))
      .finally(() => setLoading(false));
  }, [selectedClient]);

  useEffect(() => { load(); }, [load]);

  useEffect(() => {
    if (!selectedClient) { setBalance(null); return; }
    fetchClientLoyalty(selectedClient).then((r) => setBalance(r.points_balance)).catch(() => setBalance(null));
  }, [selectedClient, entries]);

  async function handlePost(e: React.FormEvent) {
    e.preventDefault();
    if (!selectedClient) return;
    setError(null);
    try {
      await createLoyaltyEntry({ client_id: selectedClient, direction, points: parseInt(points, 10) });
      setPoints('');
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed');
    }
  }

  return (
    <AdminMembershipsShell title="Loyalty points">
      <p className="mb-4 text-sm text-zinc-600">
        Points on a client’s account (separate from store credit). Set how points convert to money in{' '}
        <a href="/admin/memberships/loyalty-settings" className="font-medium underline">
          Settings
        </a>
        .
      </p>
      {error ? <ErrorAlert message={error} /> : null}
      <div className="mb-4 grid gap-4 md:grid-cols-2">
        <Card title="Client points">
          <Field label="Client">
            <select className={inputClass} value={selectedClient} onChange={(e) => setSelectedClient(e.target.value)}>
              <option value="">All clients</option>
              {clients.map((c) => <option key={c.id} value={c.id}>{c.resolved_display_name}</option>)}
            </select>
          </Field>
          {balance !== null ? <p className="mt-2 text-lg font-semibold">{balance} points</p> : null}
        </Card>
        <Card title="Adjust points">
          <form onSubmit={handlePost} className="grid gap-2">
            <Field label="Action">
              <select className={inputClass} value={direction} onChange={(e) => setDirection(e.target.value as 'credit' | 'debit')}>
                <option value="credit">Award</option>
                <option value="debit">Deduct</option>
              </select>
            </Field>
            <Field label="Points"><input className={inputClass} value={points} onChange={(e) => setPoints(e.target.value)} required /></Field>
            <Button type="submit" disabled={!selectedClient}>Save</Button>
          </form>
        </Card>
      </div>
      {loading ? <LoadingState /> : (
        <Card title="Points history">
          <table className="w-full text-left text-sm">
            <thead><tr className="border-b text-zinc-500"><th className="py-2">Client</th><th>Type</th><th>Action</th><th>Points</th></tr></thead>
            <tbody>
              {entries.map((e) => (
                <tr key={e.id} className="border-b border-zinc-100">
                  <td className="py-2">{e.client_name}</td>
                  <td>{e.entry_type}</td>
                  <td>{e.direction === 'credit' ? 'Awarded' : 'Deducted'}</td>
                  <td>{e.points}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </AdminMembershipsShell>
  );
}
