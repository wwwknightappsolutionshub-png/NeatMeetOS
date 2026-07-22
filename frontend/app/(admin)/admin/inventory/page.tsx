'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { AdminInventoryShell } from '@/components/admin/inventory/AdminInventoryShell';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { INVENTORY_ITEM_TYPES, formatMoneyCents } from '@/lib/inventory-types';
import type { InventoryItem } from '@/lib/inventory-types';
import { createInventoryItem, fetchInventoryItems } from '@/services/inventory.service';

export default function InventoryListPage() {
  const [items, setItems] = useState<InventoryItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [itemType, setItemType] = useState('');
  const [name, setName] = useState('');
  const [sku, setSku] = useState('');
  const [newType, setNewType] = useState('retail');

  const load = useCallback(() => {
    setLoading(true);
    fetchInventoryItems({ item_type: itemType || undefined })
      .then(setItems)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, [itemType]);

  useEffect(() => { load(); }, [load]);

  async function handleCreate(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await createInventoryItem({ name, sku: sku || undefined, item_type: newType as InventoryItem['item_type'] });
      setName('');
      setSku('');
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Create failed');
    }
  }

  return (
    <AdminInventoryShell title="Stock catalogue">
      {error ? <ErrorAlert message={error} /> : null}
      <div className="mb-4 grid gap-4 md:grid-cols-2">
        <Card title="Filters">
          <Field label="Item type">
            <select className={inputClass} value={itemType} onChange={(e) => setItemType(e.target.value)}>
              <option value="">All</option>
              {INVENTORY_ITEM_TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
            </select>
          </Field>
        </Card>
        <Card title="New item">
          <form onSubmit={handleCreate} className="grid gap-2">
            <Field label="Name"><input className={inputClass} value={name} onChange={(e) => setName(e.target.value)} required /></Field>
            <Field label="SKU"><input className={inputClass} value={sku} onChange={(e) => setSku(e.target.value)} /></Field>
            <Field label="Type">
              <select className={inputClass} value={newType} onChange={(e) => setNewType(e.target.value)}>
                {INVENTORY_ITEM_TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
              </select>
            </Field>
            <Button type="submit">Create item</Button>
          </form>
        </Card>
      </div>
      {loading ? <LoadingState /> : (
        <Card title="Items">
          {items.length === 0 ? <p className="text-sm text-zinc-500">No items found.</p> : (
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b text-zinc-500">
                  <th className="py-2">Name</th>
                  <th className="py-2">Type</th>
                  <th className="py-2">SKU</th>
                  <th className="py-2">Retail</th>
                  <th className="py-2">Status</th>
                </tr>
              </thead>
              <tbody>
                {items.map((item) => (
                  <tr key={item.id} className="border-b border-zinc-100">
                    <td className="py-2">
                      <Link href={`/admin/inventory/items/${item.id}`} className="underline">
                        {item.name}
                        {item.is_low_stock ? <span className="ml-2 text-xs text-amber-700">low</span> : null}
                      </Link>
                    </td>
                    <td className="py-2">{item.item_type}</td>
                    <td className="py-2">{item.sku ?? '—'}</td>
                    <td className="py-2">{formatMoneyCents(item.retail_price_cents)}</td>
                    <td className="py-2">{item.status}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </Card>
      )}
    </AdminInventoryShell>
  );
}
