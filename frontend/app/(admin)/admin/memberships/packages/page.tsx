'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminMembershipsShell } from '@/components/admin/memberships/AdminMembershipsShell';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { formatMoneyCents, type PackageProduct } from '@/lib/memberships-types';
import {
  archivePackageProduct,
  createPackageProduct,
  fetchPackageProducts,
  updatePackageProduct,
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

export default function PackageProductsPage() {
  const [packages, setPackages] = useState<PackageProduct[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [name, setName] = useState('');
  const [price, setPrice] = useState('');
  const [qty, setQty] = useState('6');
  const [isPublic, setIsPublic] = useState(true);

  const load = useCallback(() => {
    setLoading(true);
    fetchPackageProducts()
      .then(setPackages)
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
    setQty('6');
    setIsPublic(true);
  }

  function startEdit(pack: PackageProduct) {
    setError(null);
    setEditingId(pack.id);
    setName(pack.name);
    setPrice(centsToPounds(pack.price_cents));
    setQty(String(pack.included_quantity));
    setIsPublic(Boolean(pack.is_public));
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSaving(true);
    const payload = {
      name,
      price_cents: poundsToCents(price),
      included_quantity: Number.parseFloat(qty),
      is_public: isPublic,
    };
    try {
      if (editingId) {
        await updatePackageProduct(editingId, payload);
      } else {
        await createPackageProduct(payload);
      }
      resetForm();
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : editingId ? 'Update failed' : 'Create failed');
    } finally {
      setSaving(false);
    }
  }

  async function handleArchive(pack: PackageProduct) {
    if (!window.confirm(`Archive “${pack.name}”?`)) return;
    setError(null);
    try {
      await archivePackageProduct(pack.id);
      if (editingId === pack.id) resetForm();
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Archive failed');
    }
  }

  return (
    <AdminMembershipsShell title="Visit packs">
      <p className="mb-4 text-sm text-zinc-600">
        Prepaid visit bundles (e.g. 6 blow-drys). After creating one, sell it to a client under{' '}
        <a href="/admin/memberships/client-packages" className="font-medium underline">
          Client benefits → Sell visit packs
        </a>
        .
      </p>
      {error ? <ErrorAlert message={error} /> : null}
      <div className="mb-4">
        <Card title={editingId ? 'Edit visit pack' : 'New visit pack'}>
          <form onSubmit={(e) => void handleSubmit(e)} className="flex flex-wrap gap-2">
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
                placeholder="e.g. 120.00"
              />
            </Field>
            <Field label="Visits included">
              <input
                className={inputClass}
                value={qty}
                onChange={(e) => setQty(e.target.value)}
                required
              />
            </Field>
            <label className="flex items-end gap-2 pb-2 text-sm text-zinc-700">
              <input
                type="checkbox"
                checked={isPublic}
                onChange={(e) => setIsPublic(e.target.checked)}
              />
              Public page
            </label>
            <div className="flex items-end gap-2">
              <Button type="submit" disabled={saving}>
                {saving ? 'Saving…' : editingId ? 'Update visit pack' : 'Create visit pack'}
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
        <Card title="Your visit packs">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="border-b text-zinc-500">
                <th className="py-2">Name</th>
                <th>Visits</th>
                <th>Price</th>
                <th>Public</th>
                <th>Restrictions</th>
                <th>Status</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {packages.map((p) => (
                <tr key={p.id} className="border-b border-zinc-100">
                  <td className="py-2 font-medium">{p.name}</td>
                  <td>{p.included_quantity}</td>
                  <td>{formatMoneyCents(p.price_cents)}</td>
                  <td>{p.is_public ? 'Yes' : 'No'}</td>
                  <td className="text-zinc-600">
                    {(p.service_restrictions?.length ?? 0) > 0
                      ? `${p.service_restrictions!.length} service(s)`
                      : 'Any'}
                  </td>
                  <td>{p.status}</td>
                  <td className="space-x-3 whitespace-nowrap">
                    {p.status !== 'archived' ? (
                      <>
                        <button
                          type="button"
                          className="text-xs underline"
                          onClick={() => startEdit(p)}
                        >
                          Edit
                        </button>
                        <button
                          type="button"
                          className="text-xs underline"
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
