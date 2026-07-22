'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminMembershipsShell } from '@/components/admin/memberships/AdminMembershipsShell';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { formatMoneyCents, type PackageProduct } from '@/lib/memberships-types';
import { archivePackageProduct, createPackageProduct, fetchPackageProducts } from '@/services/memberships.service';

function poundsToCents(value: string): number {
  const n = Number.parseFloat(value);
  if (Number.isNaN(n)) return 0;
  return Math.round(n * 100);
}

export default function PackageProductsPage() {
  const [packages, setPackages] = useState<PackageProduct[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [name, setName] = useState('');
  const [price, setPrice] = useState('');
  const [qty, setQty] = useState('6');
  const [isPublic, setIsPublic] = useState(true);

  const load = useCallback(() => {
    setLoading(true);
    fetchPackageProducts().then(setPackages).catch((e) => setError(e instanceof Error ? e.message : 'Failed')).finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  async function handleCreate(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await createPackageProduct({
        name,
        price_cents: poundsToCents(price),
        included_quantity: parseFloat(qty),
        is_public: isPublic,
      });
      setName('');
      setPrice('');
      setIsPublic(true);
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Create failed');
    }
  }

  return (
    <AdminMembershipsShell title="Package products">
      {error ? <ErrorAlert message={error} /> : null}
      <div className="mb-4">
        <Card title="New package">
        <form onSubmit={handleCreate} className="flex flex-wrap gap-2">
          <Field label="Name"><input className={inputClass} value={name} onChange={(e) => setName(e.target.value)} required /></Field>
          <Field label="Price (Pound)">
            <input className={inputClass} type="number" min="0" step="0.01" value={price} onChange={(e) => setPrice(e.target.value)} required placeholder="e.g. 120.00" />
          </Field>
          <Field label="Quantity"><input className={inputClass} value={qty} onChange={(e) => setQty(e.target.value)} required /></Field>
          <label className="flex items-end gap-2 pb-2 text-sm text-zinc-700">
            <input type="checkbox" checked={isPublic} onChange={(e) => setIsPublic(e.target.checked)} />
            Public page
          </label>
          <div className="flex items-end"><Button type="submit">Create</Button></div>
        </form>
        </Card>
      </div>
      {loading ? <LoadingState /> : (
        <Card title="Packages">
          <table className="w-full text-left text-sm">
            <thead><tr className="border-b text-zinc-500"><th className="py-2">Name</th><th>Qty</th><th>Price</th><th>Public</th><th>Restrictions</th><th>Status</th><th /></tr></thead>
            <tbody>
              {packages.map((p) => (
                <tr key={p.id} className="border-b border-zinc-100">
                  <td className="py-2 font-medium">{p.name}</td>
                  <td>{p.included_quantity}</td>
                  <td>{formatMoneyCents(p.price_cents)}</td>
                  <td>{p.is_public ? 'Yes' : 'No'}</td>
                  <td className="text-zinc-600">{(p.service_restrictions?.length ?? 0) > 0 ? `${p.service_restrictions!.length} service(s)` : 'Any'}</td>
                  <td>{p.status}</td>
                  <td>{p.status !== 'archived' ? <button type="button" className="text-xs underline" onClick={() => archivePackageProduct(p.id).then(load)}>Archive</button> : null}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </AdminMembershipsShell>
  );
}
