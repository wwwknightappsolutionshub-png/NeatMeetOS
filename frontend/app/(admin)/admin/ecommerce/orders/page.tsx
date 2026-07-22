'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminEcommerceShell } from '@/components/admin/ecommerce/AdminEcommerceShell';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { ShopOrder } from '@/lib/ecommerce-types';
import { formatShopMoney } from '@/lib/ecommerce-types';
import {
  fetchAdminEcommerceOrders,
  updateAdminEcommerceOrderStatus,
} from '@/services/ecommerce.service';

export default function EcommerceOrdersPage() {
  const [orders, setOrders] = useState<ShopOrder[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    fetchAdminEcommerceOrders()
      .then(setOrders)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function markCollected(order: ShopOrder) {
    try {
      await updateAdminEcommerceOrderStatus(order.id, {
        status: 'collected',
        payment_status: 'paid_at_pickup',
      });
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Update failed');
    }
  }

  async function cancelOrder(order: ShopOrder) {
    try {
      await updateAdminEcommerceOrderStatus(order.id, { status: 'cancelled' });
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Cancel failed');
    }
  }

  return (
    <AdminEcommerceShell title="Click & collect orders">
      {error ? <ErrorAlert message={error} /> : null}
      <Card title="Orders">
        {loading ? <LoadingState /> : null}
        {!loading && orders.length === 0 ? (
          <p className="text-sm text-zinc-500">No shop orders yet.</p>
        ) : null}
        <ul className="divide-y divide-zinc-100">
          {orders.map((order) => (
            <li
              key={order.id}
              className="flex flex-wrap items-center justify-between gap-2 py-3"
            >
              <div>
                <p className="font-medium">
                  {order.order_number}{' '}
                  <span className="text-xs font-normal uppercase text-zinc-500">
                    {order.status} · {order.payment_status}
                  </span>
                </p>
                <p className="text-xs text-zinc-500">
                  {order.customer_name || 'Guest'}
                  {order.customer_email ? ` · ${order.customer_email}` : ''} ·{' '}
                  {formatShopMoney(order.total_cents)}
                </p>
              </div>
              {order.status === 'pending_pickup' ? (
                <div className="flex gap-2">
                  <Button type="button" onClick={() => void markCollected(order)}>
                    Collected & paid
                  </Button>
                  <Button
                    type="button"
                    variant="secondary"
                    onClick={() => void cancelOrder(order)}
                  >
                    Cancel
                  </Button>
                </div>
              ) : null}
            </li>
          ))}
        </ul>
      </Card>
    </AdminEcommerceShell>
  );
}
