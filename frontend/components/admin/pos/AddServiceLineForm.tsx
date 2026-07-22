'use client';

import { useState } from 'react';
import { Card } from '@/components/ui/Card';
import type { PosCatalogRetailItem, PosCatalogService } from '@/lib/pos-types';
import { formatMoneyCents } from '@/lib/pos-types';

interface AddServiceLineFormProps {
  services: PosCatalogService[];
  onAdd: (data: { description: string; unit_price_cents: number; booking_service_id?: string }) => Promise<void>;
  disabled?: boolean;
}

export function AddServiceLineForm({ services, onAdd, disabled }: AddServiceLineFormProps) {
  const [serviceId, setServiceId] = useState('');
  const [description, setDescription] = useState('');
  const [price, setPrice] = useState('');
  const [loading, setLoading] = useState(false);

  return (
    <Card title="Add service">
      <form
        className="flex flex-wrap gap-2 text-sm"
        onSubmit={async (e) => {
          e.preventDefault();
          setLoading(true);
          try {
            const selected = services.find((s) => s.id === serviceId);
            await onAdd({
              description: description || selected?.name || 'Service',
              unit_price_cents: parseInt(price, 10) || selected?.price_cents || 0,
              booking_service_id: serviceId || undefined,
            });
            setDescription('');
            setPrice('');
          } finally {
            setLoading(false);
          }
        }}
      >
        <select
          value={serviceId}
          onChange={(e) => {
            setServiceId(e.target.value);
            const s = services.find((x) => x.id === e.target.value);
            if (s) {
              setDescription(s.name);
              setPrice(String(s.price_cents ?? 0));
            }
          }}
          className="rounded border border-zinc-300 px-2 py-1.5"
        >
          <option value="">Manual / walk-in</option>
          {services.map((s) => (
            <option key={s.id} value={s.id}>{s.name} · {formatMoneyCents(s.price_cents ?? 0)}</option>
          ))}
        </select>
        <input value={description} onChange={(e) => setDescription(e.target.value)} placeholder="Description" className="rounded border border-zinc-300 px-2 py-1.5" />
        <input value={price} onChange={(e) => setPrice(e.target.value)} placeholder="Price (pence)" className="rounded border border-zinc-300 px-2 py-1.5" />
        <button type="submit" disabled={disabled || loading} className="rounded bg-zinc-900 px-3 py-1.5 text-white disabled:opacity-50">Add</button>
      </form>
    </Card>
  );
}

interface AddRetailLineFormProps {
  items: PosCatalogRetailItem[];
  onAdd: (data: { inventory_item_id: string; quantity?: number }) => Promise<void>;
  disabled?: boolean;
}

export function AddRetailLineForm({ items, onAdd, disabled }: AddRetailLineFormProps) {
  const [itemId, setItemId] = useState(items[0]?.id ?? '');
  const [qty, setQty] = useState('1');
  const [loading, setLoading] = useState(false);

  return (
    <Card title="Add retail">
      <form
        className="flex flex-wrap gap-2 text-sm"
        onSubmit={async (e) => {
          e.preventDefault();
          if (!itemId) return;
          setLoading(true);
          try {
            await onAdd({ inventory_item_id: itemId, quantity: parseInt(qty, 10) || 1 });
          } finally {
            setLoading(false);
          }
        }}
      >
        <select value={itemId} onChange={(e) => setItemId(e.target.value)} className="rounded border border-zinc-300 px-2 py-1.5">
          {items.map((item) => (
            <option key={item.id} value={item.id}>{item.name} · {formatMoneyCents(item.retail_price_cents ?? 0)}</option>
          ))}
        </select>
        <input value={qty} onChange={(e) => setQty(e.target.value)} placeholder="Qty" className="w-20 rounded border border-zinc-300 px-2 py-1.5" />
        <button type="submit" disabled={disabled || loading || !itemId} className="rounded bg-zinc-900 px-3 py-1.5 text-white disabled:opacity-50">Add</button>
      </form>
    </Card>
  );
}
