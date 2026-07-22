'use client';

import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { AddRetailLineForm, AddServiceLineForm } from '@/components/admin/pos/AddServiceLineForm';
import { AdminPosShell } from '@/components/admin/pos/AdminPosShell';
import { AppointmentImportPanel } from '@/components/admin/pos/AppointmentImportPanel';
import { CheckoutHeaderCard } from '@/components/admin/pos/CheckoutHeaderCard';
import { CheckoutLinesTable } from '@/components/admin/pos/CheckoutLinesTable';
import { CheckoutTotalsCard } from '@/components/admin/pos/CheckoutTotalsCard';
import { CompletedCheckoutPanel } from '@/components/admin/pos/CompletedCheckoutPanel';
import { DepositCreditPanel } from '@/components/admin/pos/DepositCreditPanel';
import { GiftCardRedemptionPanel, GiftCardSalePanel } from '@/components/admin/pos/GiftCardPanel';
import { MembershipBenefitsPanel } from '@/components/admin/pos/MembershipBenefitsPanel';
import { PaymentPanel } from '@/components/admin/pos/PaymentPanel';
import type { Checkout, EligibleAppointment, PosCatalogRetailItem, PosCatalogService } from '@/lib/pos-types';
import { isCheckoutEditable, isCheckoutTerminal } from '@/lib/pos-types';
import {
  addGiftCardLine,
  addRetailLine,
  addServiceLine,
  applyDepositCredit,
  applyGiftCard,
  completeCheckout,
  fetchCheckout,
  fetchEligibleAppointments,
  fetchPosCatalogRetail,
  fetchPosCatalogServices,
  importAppointment,
  recordCheckoutPayments,
  removeCheckoutLine,
  removeDepositCredit,
  voidCheckout,
} from '@/services/pos.service';

export default function PosCheckoutDetailPage() {
  const params = useParams<{ checkoutId: string }>();
  const checkoutId = params.checkoutId;

  const [checkout, setCheckout] = useState<Checkout | null>(null);
  const [appointments, setAppointments] = useState<EligibleAppointment[]>([]);
  const [services, setServices] = useState<PosCatalogService[]>([]);
  const [retail, setRetail] = useState<PosCatalogRetailItem[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  const reload = useCallback(async () => {
    const data = await fetchCheckout(checkoutId);
    setCheckout(data);
    if (data.location?.id) {
      const eligible = await fetchEligibleAppointments({ location_id: data.location.id });
      setAppointments(eligible);
    }
  }, [checkoutId]);

  useEffect(() => {
    Promise.all([reload(), fetchPosCatalogServices(), fetchPosCatalogRetail()])
      .then(([, svc, items]) => {
        setServices(svc);
        setRetail(items);
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load checkout'));
  }, [reload]);

  const editable = checkout ? isCheckoutEditable(checkout.status) : false;

  const wrap = async (fn: () => Promise<Checkout>) => {
    setActionLoading(true);
    setError(null);
    try {
      const updated = await fn();
      setCheckout(updated);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Action failed');
    } finally {
      setActionLoading(false);
    }
  };

  if (!checkout) {
    return (
      <AdminPosShell title="Checkout">
        <p className="text-sm text-zinc-500">{error ?? 'Loading checkout…'}</p>
      </AdminPosShell>
    );
  }

  return (
    <AdminPosShell title={`Checkout ${checkout.checkout_number}`}>
      <div className="grid gap-4 lg:grid-cols-3">
        <div className="grid gap-4 lg:col-span-2">
          <CheckoutHeaderCard checkout={checkout} />
          {error ? <p className="text-sm text-red-600">{error}</p> : null}
          <CheckoutLinesTable
            lines={checkout.lines ?? []}
            editable={editable}
            onRemove={(lineId) => wrap(() => removeCheckoutLine(checkoutId, lineId).then(() => fetchCheckout(checkoutId)))}
          />
          {editable ? (
            <>
              <AppointmentImportPanel
                appointments={appointments}
                disabled={actionLoading}
                onImport={(appointmentId) => wrap(() => importAppointment(checkoutId, appointmentId))}
              />
              <AddServiceLineForm
                services={services}
                disabled={actionLoading}
                onAdd={(data) => wrap(() => addServiceLine(checkoutId, data))}
              />
              <AddRetailLineForm
                items={retail}
                disabled={actionLoading}
                onAdd={(data) => wrap(() => addRetailLine(checkoutId, data))}
              />
              <GiftCardSalePanel
                disabled={actionLoading}
                onAdd={(data) => wrap(() => addGiftCardLine(checkoutId, data))}
              />
            </>
          ) : null}
        </div>
        <div className="grid gap-4">
          <CheckoutTotalsCard checkout={checkout} />
          {editable ? (
            <>
              <DepositCreditPanel
                checkout={checkout}
                disabled={actionLoading}
                onApply={() => wrap(() => applyDepositCredit(checkoutId))}
                onRemove={() => wrap(() => removeDepositCredit(checkoutId))}
              />
              <MembershipBenefitsPanel
                checkout={checkout}
                disabled={actionLoading}
                onUpdated={setCheckout}
              />
              <GiftCardRedemptionPanel
                checkout={checkout}
                disabled={actionLoading}
                onApply={(code, amountCents) => wrap(() => applyGiftCard(checkoutId, code, amountCents))}
              />
              <PaymentPanel
                checkout={checkout}
                disabled={actionLoading}
                onPay={(tenders) => wrap(() => recordCheckoutPayments(checkoutId, tenders))}
              />
              <div className="flex flex-wrap gap-2">
                <button
                  type="button"
                  disabled={actionLoading || checkout.amount_due_cents > 0}
                  className="rounded bg-emerald-700 px-4 py-2 text-white disabled:opacity-50"
                  onClick={() => wrap(() => completeCheckout(checkoutId))}
                >
                  Complete checkout
                </button>
                <button
                  type="button"
                  disabled={actionLoading || checkout.amount_paid_cents > 0}
                  className="rounded border border-zinc-300 px-4 py-2 disabled:opacity-50"
                  onClick={() => wrap(() => voidCheckout(checkoutId))}
                >
                  Void
                </button>
              </div>
            </>
          ) : isCheckoutTerminal(checkout.status) || checkout.status === 'partially_refunded' ? (
            <CompletedCheckoutPanel checkout={checkout} onUpdated={setCheckout} />
          ) : null}
        </div>
      </div>
    </AdminPosShell>
  );
}
