'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminEcommerceShell } from '@/components/admin/ecommerce/AdminEcommerceShell';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { AdminEcommerceProduct } from '@/lib/ecommerce-types';
import { formatShopMoney } from '@/lib/ecommerce-types';
import type { InventoryItem } from '@/lib/inventory-types';
import {
  createAdminEcommerceProduct,
  fetchAdminEcommerceProducts,
  setAdminEcommerceProductStatus,
  updateAdminEcommerceProduct,
} from '@/services/ecommerce.service';
import { fetchInventoryItems } from '@/services/inventory.service';

export default function EcommerceProductsPage() {
  const [products, setProducts] = useState<AdminEcommerceProduct[]>([]);
  const [retailItems, setRetailItems] = useState<InventoryItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [inventoryItemId, setInventoryItemId] = useState('');
  const [title, setTitle] = useState('');
  const [pricePounds, setPricePounds] = useState('19.99');
  const [description, setDescription] = useState('');
  const [carousel, setCarousel] = useState(true);

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([
      fetchAdminEcommerceProducts(),
      fetchInventoryItems({ item_type: 'retail' }),
    ])
      .then(([prods, items]) => {
        setProducts(prods);
        setRetailItems(items);
        if (!inventoryItemId && items[0]) setInventoryItemId(items[0].id);
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, [inventoryItemId]);

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function handleCreate(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    const cents = Math.round(Number(pricePounds) * 100);
    if (!inventoryItemId || !title || Number.isNaN(cents)) {
      setError('Inventory SKU, title, and price are required');
      return;
    }
    try {
      await createAdminEcommerceProduct({
        inventory_item_id: inventoryItemId,
        title,
        description: description || undefined,
        price_cents: cents,
        show_on_booking_carousel: carousel,
      });
      setTitle('');
      setDescription('');
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Create failed');
    }
  }

  async function toggleCarousel(product: AdminEcommerceProduct) {
    try {
      await updateAdminEcommerceProduct(product.id, {
        show_on_booking_carousel: !product.show_on_booking_carousel,
      });
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Update failed');
    }
  }

  async function toggleStatus(product: AdminEcommerceProduct) {
    try {
      await setAdminEcommerceProductStatus(
        product.id,
        product.status === 'active' ? 'archived' : 'active',
      );
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Status update failed');
    }
  }

  return (
    <AdminEcommerceShell title="Shop products">
      {error ? <ErrorAlert message={error} /> : null}
      <div className="mb-4 grid gap-4 lg:grid-cols-2">
        <Card title="Add product">
          <form onSubmit={(e) => void handleCreate(e)} className="grid gap-3">
            <Field label="Inventory SKU (retail)">
              <select
                className={inputClass}
                value={inventoryItemId}
                onChange={(e) => setInventoryItemId(e.target.value)}
                required
              >
                <option value="">Select item…</option>
                {retailItems.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.name}
                    {item.sku ? ` (${item.sku})` : ''}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Storefront title">
              <input
                className={inputClass}
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                required
              />
            </Field>
            <Field label="Price (GBP)">
              <input
                className={inputClass}
                type="number"
                step="0.01"
                min="0"
                value={pricePounds}
                onChange={(e) => setPricePounds(e.target.value)}
                required
              />
            </Field>
            <Field label="Description">
              <textarea
                className={inputClass}
                rows={2}
                value={description}
                onChange={(e) => setDescription(e.target.value)}
              />
            </Field>
            <label className="flex items-center gap-2 text-sm text-zinc-700">
              <input
                type="checkbox"
                checked={carousel}
                onChange={(e) => setCarousel(e.target.checked)}
              />
              Show on booking page carousel
            </label>
            <Button type="submit">Create product</Button>
          </form>
        </Card>
        <Card title="Notes">
          <p className="text-sm text-zinc-600">
            Each shop product must link to a retail inventory SKU. Orders decrement that SKU at the
            pickup location. Payment is cash / pay-in-salon only.
          </p>
        </Card>
      </div>
      <Card title="Catalogue">
        {loading ? <LoadingState /> : null}
        {!loading && products.length === 0 ? (
          <p className="text-sm text-zinc-500">No shop products yet.</p>
        ) : null}
        <ul className="divide-y divide-zinc-100">
          {products.map((product) => (
            <li
              key={product.id}
              className="flex flex-wrap items-center justify-between gap-2 py-3"
            >
              <div>
                <p className="font-medium">{product.title}</p>
                <p className="text-xs text-zinc-500">
                  {formatShopMoney(product.price_cents)} · {product.status}
                  {product.inventory_item?.sku
                    ? ` · SKU ${product.inventory_item.sku}`
                    : ''}
                  {product.show_on_booking_carousel ? ' · carousel' : ''}
                </p>
              </div>
              <div className="flex gap-2">
                <Button type="button" variant="secondary" onClick={() => void toggleCarousel(product)}>
                  {product.show_on_booking_carousel ? 'Hide carousel' : 'Show carousel'}
                </Button>
                <Button type="button" variant="secondary" onClick={() => void toggleStatus(product)}>
                  {product.status === 'active' ? 'Archive' : 'Activate'}
                </Button>
              </div>
            </li>
          ))}
        </ul>
      </Card>
    </AdminEcommerceShell>
  );
}
