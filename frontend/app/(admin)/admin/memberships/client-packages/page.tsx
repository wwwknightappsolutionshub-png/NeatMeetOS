'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminMembershipsShell } from '@/components/admin/memberships/AdminMembershipsShell';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { Client } from '@/lib/crm-types';
import type { ClientPackage, PackageProduct } from '@/lib/memberships-types';
import { fetchClients } from '@/services/crm.service';
import {
  assignClientPackage,
  fetchClientPackages,
  fetchPackageProducts,
  redeemClientPackage,
  restoreClientPackage,
} from '@/services/memberships.service';

export default function ClientPackagesPage() {
  const [packages, setPackages] = useState<ClientPackage[]>([]);
  const [products, setProducts] = useState<PackageProduct[]>([]);
  const [clients, setClients] = useState<Client[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [clientId, setClientId] = useState('');
  const [productId, setProductId] = useState('');

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([fetchClientPackages(), fetchPackageProducts(), fetchClients()])
      .then(([p, prod, c]) => { setPackages(p); setProducts(prod.filter((x) => x.status === 'active')); setClients(c.items); })
      .catch((err) => setError(err instanceof Error ? err.message : 'Failed'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  async function handleAssign(e: React.FormEvent) {
    e.preventDefault();
    if (!clientId || !productId) return;
    try {
      await assignClientPackage({ client_id: clientId, package_product_id: productId });
      setClientId('');
      setProductId('');
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Assign failed');
    }
  }

  return (
    <AdminMembershipsShell title="Client packages">
      {error ? <ErrorAlert message={error} /> : null}
      <div className="mb-4">
        <Card title="Assign package">
        <form onSubmit={handleAssign} className="flex flex-wrap gap-2">
          <Field label="Client">
            <select className={inputClass} value={clientId} onChange={(e) => setClientId(e.target.value)} required>
              <option value="">Select client</option>
              {clients.map((c) => <option key={c.id} value={c.id}>{c.resolved_display_name}</option>)}
            </select>
          </Field>
          <Field label="Package product">
            <select className={inputClass} value={productId} onChange={(e) => setProductId(e.target.value)} required>
              <option value="">Select package</option>
              {products.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
            </select>
          </Field>
          <div className="flex items-end"><Button type="submit">Assign</Button></div>
        </form>
        </Card>
      </div>
      {loading ? <LoadingState /> : (
        <Card title="Client package balances">
          <table className="w-full text-left text-sm">
            <thead><tr className="border-b text-zinc-500"><th className="py-2">Client</th><th>Package</th><th>Remaining</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              {packages.map((p) => (
                <tr key={p.id} className="border-b border-zinc-100">
                  <td className="py-2">{p.client_name}</td>
                  <td>{p.package_name}</td>
                  <td>{p.quantity_remaining} / {p.quantity_total}</td>
                  <td>{p.status}</td>
                  <td className="flex gap-2">
                    {p.status === 'active' && p.quantity_remaining > 0 ? (
                      <button type="button" className="text-xs underline" onClick={() => redeemClientPackage(p.id, 1).then(load)}>Redeem 1</button>
                    ) : null}
                    {p.quantity_remaining < p.quantity_total ? (
                      <button type="button" className="text-xs underline" onClick={() => restoreClientPackage(p.id, 1).then(load)}>Restore 1</button>
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
