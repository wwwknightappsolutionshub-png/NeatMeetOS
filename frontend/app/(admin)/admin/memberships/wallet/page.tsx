'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminMembershipsShell } from '@/components/admin/memberships/AdminMembershipsShell';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { Client } from '@/lib/crm-types';
import { formatMoneyCents, type WalletEntry } from '@/lib/memberships-types';
import { fetchClients } from '@/services/crm.service';
import { createWalletEntry, fetchClientWallet, fetchWalletEntries } from '@/services/memberships.service';

export default function WalletPage() {
  const [entries, setEntries] = useState<WalletEntry[]>([]);
  const [clients, setClients] = useState<Client[]>([]);
  const [selectedClient, setSelectedClient] = useState('');
  const [balance, setBalance] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [amount, setAmount] = useState('');
  const [direction, setDirection] = useState<'credit' | 'debit'>('credit');
  const [notes, setNotes] = useState('');

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([fetchWalletEntries(selectedClient || undefined), fetchClients()])
      .then(([e, c]) => { setEntries(e); setClients(c.items); })
      .catch((err) => setError(err instanceof Error ? err.message : 'Failed'))
      .finally(() => setLoading(false));
  }, [selectedClient]);

  useEffect(() => { load(); }, [load]);

  useEffect(() => {
    if (!selectedClient) { setBalance(null); return; }
    fetchClientWallet(selectedClient).then((r) => setBalance(r.balance_cents)).catch(() => setBalance(null));
  }, [selectedClient, entries]);

  async function handlePost(e: React.FormEvent) {
    e.preventDefault();
    if (!selectedClient) return;
    setError(null);
    try {
      await createWalletEntry({ client_id: selectedClient, direction, amount_cents: Math.round(Number.parseFloat(amount) * 100) || 0, notes: notes || undefined });
      setAmount('');
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed');
    }
  }

  return (
    <AdminMembershipsShell title="Wallet ledger">
      {error ? <ErrorAlert message={error} /> : null}
      <div className="mb-4 grid gap-4 md:grid-cols-2">
        <Card title="Filter / balance">
          <Field label="Client">
            <select className={inputClass} value={selectedClient} onChange={(e) => setSelectedClient(e.target.value)}>
              <option value="">All clients</option>
              {clients.map((c) => <option key={c.id} value={c.id}>{c.resolved_display_name}</option>)}
            </select>
          </Field>
          {balance !== null ? <p className="mt-2 text-lg font-semibold">Balance: {formatMoneyCents(balance)}</p> : null}
        </Card>
        <Card title="Manual adjustment">
          <form onSubmit={handlePost} className="grid gap-2">
            <Field label="Direction">
              <select className={inputClass} value={direction} onChange={(e) => setDirection(e.target.value as 'credit' | 'debit')}>
                <option value="credit">Credit</option>
                <option value="debit">Debit</option>
              </select>
            </Field>
          <Field label="Amount (Pound)">
            <input className={inputClass} value={amount} onChange={(e) => setAmount(e.target.value)} required placeholder="e.g. 10.00" />
          </Field>
            <Field label="Notes"><input className={inputClass} value={notes} onChange={(e) => setNotes(e.target.value)} /></Field>
            <Button type="submit" disabled={!selectedClient}>Post entry</Button>
          </form>
        </Card>
      </div>
      {loading ? <LoadingState /> : (
        <Card title="Entries">
          <table className="w-full text-left text-sm">
            <thead><tr className="border-b text-zinc-500"><th className="py-2">Client</th><th>Type</th><th>Direction</th><th>Amount</th><th>Notes</th></tr></thead>
            <tbody>
              {entries.map((e) => (
                <tr key={e.id} className="border-b border-zinc-100">
                  <td className="py-2">{e.client_name}</td>
                  <td>{e.entry_type}</td>
                  <td>{e.direction}</td>
                  <td>{formatMoneyCents(e.amount_cents)}</td>
                  <td className="text-zinc-600">{e.notes}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </AdminMembershipsShell>
  );
}
