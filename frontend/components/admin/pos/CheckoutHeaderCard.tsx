'use client';

import { Card } from '@/components/ui/Card';
import type { Checkout } from '@/lib/pos-types';
import { checkoutStatusLabel, formatMoneyCents } from '@/lib/pos-types';

interface CheckoutHeaderCardProps {
  checkout: Checkout;
}

export function CheckoutHeaderCard({ checkout }: CheckoutHeaderCardProps) {
  return (
    <Card title={`Checkout ${checkout.checkout_number}`}>
      <div className="grid gap-2 text-sm text-zinc-700 md:grid-cols-2">
        <p><span className="text-zinc-500">Status:</span> {checkoutStatusLabel(checkout.status)}</p>
        <p><span className="text-zinc-500">Location:</span> {checkout.location?.name ?? '—'}</p>
        <p><span className="text-zinc-500">Client:</span> {checkout.client?.display_name ?? 'Walk-in / unassigned'}</p>
        <p><span className="text-zinc-500">Cashier:</span> {checkout.cashier?.display_name ?? '—'}</p>
        {checkout.notes ? <p className="md:col-span-2"><span className="text-zinc-500">Notes:</span> {checkout.notes}</p> : null}
        {checkout.linked_appointments && checkout.linked_appointments.length > 0 ? (
          <p className="md:col-span-2">
            <span className="text-zinc-500">Appointments:</span>{' '}
            {checkout.linked_appointments.map((a) => a.booking_reference).join(', ')}
          </p>
        ) : null}
        {checkout.completed_at ? (
          <p className="md:col-span-2 text-emerald-700">Completed · {new Date(checkout.completed_at).toLocaleString()}</p>
        ) : null}
      </div>
    </Card>
  );
}
