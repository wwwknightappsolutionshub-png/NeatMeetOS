'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { AdminInventoryShell } from '@/components/admin/inventory/AdminInventoryShell';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { formatMoneyCents, formatQuantity } from '@/lib/inventory-types';
import type { InventoryItem, InventoryMovement } from '@/lib/inventory-types';
import { fetchBookableServices } from '@/services/booking.service';
import {
  createServiceConsumptionRule,
  fetchInventoryItem,
  fetchInventoryMovements,
  recordInventoryMovement,
  updateInventoryItemLevel,
} from '@/services/inventory.service';
import { fetchLocations } from '@/services/identity.service';

export default function InventoryItemDetailPage() {
  const params = useParams();
  const itemId = params.itemId as string;
  const [item, setItem] = useState<InventoryItem | null>(null);
  const [movements, setMovements] = useState<InventoryMovement[]>([]);
  const [locations, setLocations] = useState<{ id: string; name: string }[]>([]);
  const [services, setServices] = useState<{ id: string; name: string }[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [locationId, setLocationId] = useState('');
  const [reorderPoint, setReorderPoint] = useState('');
  const [movementQty, setMovementQty] = useState('1');
  const [movementType, setMovementType] = useState('purchase_receipt');
  const [ruleServiceId, setRuleServiceId] = useState('');
  const [ruleQty, setRuleQty] = useState('1');

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([
      fetchInventoryItem(itemId),
      fetchInventoryMovements({ inventory_item_id: itemId }),
      fetchLocations(),
      fetchBookableServices(false),
    ])
      .then(([it, movs, locs, svcs]) => {
        setItem(it);
        setMovements(movs);
        setLocations(locs);
        setServices(svcs);
        setLocationId(locs[0]?.id ?? '');
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, [itemId]);

  useEffect(() => { load(); }, [load]);

  if (loading && !item) {
    return <AdminInventoryShell title="Item"><LoadingState /></AdminInventoryShell>;
  }

  return (
    <AdminInventoryShell title={item?.name ?? 'Item'}>
      <p className="mb-4 text-sm"><Link href="/admin/inventory" className="text-zinc-600 hover:underline">← Back to inventory</Link></p>
      {error ? <ErrorAlert message={error} /> : null}
      {item ? (
        <div className="grid gap-4 md:grid-cols-2">
          <Card title="Details">
            <dl className="space-y-2 text-sm">
              <div><dt className="text-zinc-500">Type</dt><dd>{item.item_type}</dd></div>
              <div><dt className="text-zinc-500">SKU</dt><dd>{item.sku ?? '—'}</dd></div>
              <div><dt className="text-zinc-500">Cost</dt><dd>{formatMoneyCents(item.cost_price_cents)}</dd></div>
              <div><dt className="text-zinc-500">Retail</dt><dd>{formatMoneyCents(item.retail_price_cents)}</dd></div>
              <div><dt className="text-zinc-500">Unit</dt><dd>{item.unit_label ?? '—'}{item.unit_size ? ` (${item.unit_size})` : ''}</dd></div>
              <div><dt className="text-zinc-500">Supplier</dt><dd>{item.preferred_supplier?.name ?? '—'}</dd></div>
            </dl>
          </Card>
          <Card title="Location levels">
            <ul className="mb-3 space-y-2 text-sm">
              {(item.levels ?? []).map((level) => (
                <li key={level.id}>
                  {level.location?.name}: {formatQuantity(level.on_hand_quantity)} on hand
                  {level.is_low_stock ? ' · low stock' : ''}
                </li>
              ))}
            </ul>
            <div className="grid gap-2">
              <Field label="Location">
                <select className={inputClass} value={locationId} onChange={(e) => setLocationId(e.target.value)}>
                  {locations.map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
                </select>
              </Field>
              <Field label="Reorder point">
                <input className={inputClass} value={reorderPoint} onChange={(e) => setReorderPoint(e.target.value)} />
              </Field>
              <Button type="button" onClick={async () => {
                await updateInventoryItemLevel(itemId, locationId, {
                  reorder_point: reorderPoint ? Number(reorderPoint) : undefined,
                });
                load();
              }}>Update thresholds</Button>
            </div>
          </Card>
          <Card title="Record movement">
            <div className="grid gap-2">
              <select className={inputClass} value={movementType} onChange={(e) => setMovementType(e.target.value)}>
                <option value="purchase_receipt">Restock</option>
                <option value="adjustment">Adjustment</option>
                <option value="waste">Waste</option>
              </select>
              <input className={inputClass} type="number" step="0.001" value={movementQty} onChange={(e) => setMovementQty(e.target.value)} />
              <Button type="button" onClick={async () => {
                const delta = movementType === 'waste' ? -Math.abs(Number(movementQty)) : Number(movementQty);
                await recordInventoryMovement({
                  inventory_item_id: itemId,
                  location_id: locationId,
                  movement_type: movementType,
                  quantity_delta: delta,
                });
                load();
              }}>Record movement</Button>
            </div>
          </Card>
          <Card title="Service consumption rules">
            <ul className="mb-2 space-y-1 text-sm">
              {(item.consumption_rules ?? []).map((r) => (
                <li key={r.id}>{r.booking_service?.name}: {formatQuantity(r.quantity_required)} {r.inventory_item?.unit_label ?? ''}</li>
              ))}
            </ul>
            {item.item_type === 'professional' ? (
              <div className="grid gap-2">
                <select className={inputClass} value={ruleServiceId} onChange={(e) => setRuleServiceId(e.target.value)}>
                  <option value="">Select service</option>
                  {services.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                </select>
                <input className={inputClass} type="number" step="0.001" value={ruleQty} onChange={(e) => setRuleQty(e.target.value)} />
                <Button type="button" onClick={async () => {
                  if (!ruleServiceId) return;
                  await createServiceConsumptionRule({
                    booking_service_id: ruleServiceId,
                    inventory_item_id: itemId,
                    quantity_required: Number(ruleQty),
                  });
                  load();
                }}>Add rule</Button>
              </div>
            ) : null}
          </Card>
          <Card title="Movement history">
            {movements.length === 0 ? <p className="text-sm text-zinc-500">No movements yet.</p> : (
              <ul className="space-y-2 text-sm">
                {movements.map((m) => (
                  <li key={m.id}>{m.movement_type}: {formatQuantity(m.quantity_delta)} → {m.quantity_after != null ? formatQuantity(m.quantity_after) : '—'}</li>
                ))}
              </ul>
            )}
          </Card>
        </div>
      ) : null}
    </AdminInventoryShell>
  );
}
