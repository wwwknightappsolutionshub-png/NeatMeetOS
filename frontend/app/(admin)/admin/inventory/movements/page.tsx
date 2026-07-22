'use client';

import { useEffect, useState } from 'react';
import { AdminInventoryShell } from '@/components/admin/inventory/AdminInventoryShell';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import { formatQuantity } from '@/lib/inventory-types';
import type { InventoryMovement } from '@/lib/inventory-types';
import { fetchInventoryMovements } from '@/services/inventory.service';

export default function InventoryMovementsPage() {
  const [movements, setMovements] = useState<InventoryMovement[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchInventoryMovements()
      .then(setMovements)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed'))
      .finally(() => setLoading(false));
  }, []);

  return (
    <AdminInventoryShell title="Stock movements">
      {error ? <ErrorAlert message={error} /> : null}
      {loading ? <LoadingState /> : (
        <Card title="Recent movements">
          {movements.length === 0 ? <p className="text-sm text-zinc-500">No movements recorded.</p> : (
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b text-zinc-500">
                  <th className="py-2">When</th>
                  <th className="py-2">Item</th>
                  <th className="py-2">Type</th>
                  <th className="py-2">Delta</th>
                  <th className="py-2">After</th>
                </tr>
              </thead>
              <tbody>
                {movements.map((m) => (
                  <tr key={m.id} className="border-b border-zinc-100">
                    <td className="py-2">{m.created_at ? new Date(m.created_at).toLocaleString() : '—'}</td>
                    <td className="py-2">{m.item?.name ?? m.inventory_item_id}</td>
                    <td className="py-2">{m.movement_type}</td>
                    <td className="py-2">{formatQuantity(m.quantity_delta)}</td>
                    <td className="py-2">{m.quantity_after != null ? formatQuantity(m.quantity_after) : '—'}</td>
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
