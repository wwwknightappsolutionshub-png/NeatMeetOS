'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { AdminPosShell } from '@/components/admin/pos/AdminPosShell';
import { Card } from '@/components/ui/Card';
import type { CheckoutListItem } from '@/lib/pos-types';
import { checkoutStatusLabel, formatMoneyCents } from '@/lib/pos-types';
import { createCheckout, fetchCheckouts } from '@/services/pos.service';
import { fetchLocations } from '@/services/identity.service';

export default function PosListPage() {
  const [checkouts, setCheckouts] = useState<CheckoutListItem[]>([]);
  const [locationId, setLocationId] = useState('');
  const [locations, setLocations] = useState<Array<{ id: string; name: string }>>([]);
  const [status, setStatus] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = () => {
    setLoading(true);
    fetchCheckouts({ status: status || undefined, location_id: locationId || undefined })
      .then(setCheckouts)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load checkouts'))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchLocations().then((locs) => {
      setLocations(locs);
      if (locs[0]) setLocationId(locs[0].id);
    }).catch(() => {});
  }, []);

  useEffect(() => {
    load();
  }, [status, locationId]);

  const startCheckout = async () => {
    if (!locationId) return;
    try {
      const checkout = await createCheckout({ location_id: locationId });
      window.location.href = `/admin/pos/${checkout.id}`;
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to create checkout');
    }
  };

  return (
    <AdminPosShell title="Point of Sale">
      <div className="grid gap-4">
        <Card title="New checkout">
          <div className="flex flex-wrap items-end gap-3 text-sm">
            <label className="grid gap-1">
              <span className="text-zinc-500">Location</span>
              <select value={locationId} onChange={(e) => setLocationId(e.target.value)} className="rounded border border-zinc-300 px-2 py-1.5">
                {locations.map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
              </select>
            </label>
            <button type="button" onClick={startCheckout} className="rounded bg-zinc-900 px-4 py-2 text-white">Open new checkout</button>
          </div>
        </Card>

        <Card title="Recent checkouts">
          <div className="mb-3 flex flex-wrap gap-2 text-sm">
            <select value={status} onChange={(e) => setStatus(e.target.value)} className="rounded border border-zinc-300 px-2 py-1.5">
              <option value="">All statuses</option>
              <option value="draft">Draft</option>
              <option value="open">Open</option>
              <option value="completed">Completed</option>
              <option value="voided">Voided</option>
            </select>
          </div>
          {error ? <p className="text-sm text-red-600">{error}</p> : null}
          {loading ? <p className="text-sm text-zinc-500">Loading…</p> : checkouts.length === 0 ? (
            <p className="text-sm text-zinc-500">No checkouts yet.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="min-w-full text-sm">
                <thead>
                  <tr className="border-b border-zinc-200 text-left text-zinc-500">
                    <th className="py-2 pr-3">Number</th>
                    <th className="py-2 pr-3">Status</th>
                    <th className="py-2 pr-3">Client</th>
                    <th className="py-2 pr-3">Total</th>
                    <th className="py-2 pr-3">Due</th>
                    <th className="py-2" />
                  </tr>
                </thead>
                <tbody>
                  {checkouts.map((c) => (
                    <tr key={c.id} className="border-b border-zinc-100">
                      <td className="py-2 pr-3 font-medium">{c.checkout_number}</td>
                      <td className="py-2 pr-3 capitalize">{checkoutStatusLabel(c.status)}</td>
                      <td className="py-2 pr-3">{c.client_name ?? '—'}</td>
                      <td className="py-2 pr-3">{formatMoneyCents(c.total_cents)}</td>
                      <td className="py-2 pr-3">{formatMoneyCents(c.amount_due_cents)}</td>
                      <td className="py-2"><Link href={`/admin/pos/${c.id}`} className="text-zinc-900 underline">Open</Link></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      </div>
    </AdminPosShell>
  );
}
