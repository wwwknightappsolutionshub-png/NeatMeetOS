'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { AdminInventoryShell } from '@/components/admin/inventory/AdminInventoryShell';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import { formatQuantity } from '@/lib/inventory-types';
import type { InventoryLevel } from '@/lib/inventory-types';
import { fetchInventoryLevels } from '@/services/inventory.service';

export default function LowStockPage() {
  const [levels, setLevels] = useState<InventoryLevel[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchInventoryLevels({ low_stock: true })
      .then(setLevels)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed'))
      .finally(() => setLoading(false));
  }, []);

  return (
    <AdminInventoryShell title="Low stock">
      {error ? <ErrorAlert message={error} /> : null}
      {loading ? <LoadingState /> : (
        <Card title="Below reorder point">
          {levels.length === 0 ? <p className="text-sm text-zinc-500">No low-stock items.</p> : (
            <ul className="space-y-2 text-sm">
              {levels.map((l) => (
                <li key={l.id}>
                  <Link href={`/admin/inventory/items/${l.inventory_item_id}`} className="underline">
                    {l.item?.name ?? l.inventory_item_id}
                  </Link>
                  {' '}· {l.location?.name}: {formatQuantity(l.on_hand_quantity)} / reorder {formatQuantity(l.reorder_point ?? 0)}
                </li>
              ))}
            </ul>
          )}
        </Card>
      )}
    </AdminInventoryShell>
  );
}
