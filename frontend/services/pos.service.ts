import { api } from '@/lib/api-client';
import type {
  Checkout,
  CheckoutListItem,
  CheckoutReceipt,
  CheckoutRefund,
  EligibleAppointment,
  PosCatalogRetailItem,
  PosCatalogService,
} from '@/lib/pos-types';
import type { PaymentTransaction } from '@/lib/payments-types';

const auth = { auth: true as const, tenant: true as const };

export async function fetchCheckouts(params?: {
  status?: string;
  location_id?: string;
  client_id?: string;
}): Promise<CheckoutListItem[]> {
  const search = new URLSearchParams();
  if (params?.status) search.set('status', params.status);
  if (params?.location_id) search.set('location_id', params.location_id);
  if (params?.client_id) search.set('client_id', params.client_id);
  const query = search.toString();
  return api<CheckoutListItem[]>(`/admin/pos/checkouts${query ? `?${query}` : ''}`, auth);
}

export async function fetchCheckout(id: string): Promise<Checkout> {
  return api<Checkout>(`/admin/pos/checkouts/${id}`, auth);
}

export async function createCheckout(data: {
  location_id: string;
  client_id?: string;
  team_member_id?: string;
  notes?: string;
}): Promise<Checkout> {
  return api<Checkout>('/admin/pos/checkouts', { ...auth, method: 'POST', body: JSON.stringify(data) });
}

export async function updateCheckout(
  id: string,
  data: Partial<{ client_id: string | null; location_id: string; notes: string; status: string }>,
): Promise<Checkout> {
  return api<Checkout>(`/admin/pos/checkouts/${id}`, { ...auth, method: 'PUT', body: JSON.stringify(data) });
}

export async function addServiceLine(
  checkoutId: string,
  data: { description: string; unit_price_cents: number; booking_service_id?: string; quantity?: number },
): Promise<Checkout> {
  return api<Checkout>(`/admin/pos/checkouts/${checkoutId}/lines/service`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function addRetailLine(
  checkoutId: string,
  data: { inventory_item_id: string; quantity?: number; unit_price_cents?: number },
): Promise<Checkout> {
  return api<Checkout>(`/admin/pos/checkouts/${checkoutId}/lines/retail`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function updateCheckoutLine(
  checkoutId: string,
  lineId: string,
  data: Partial<{ description: string; quantity: number; unit_price_cents: number; discount_cents: number }>,
): Promise<Checkout> {
  return api<Checkout>(`/admin/pos/checkouts/${checkoutId}/lines/${lineId}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function removeCheckoutLine(checkoutId: string, lineId: string): Promise<Checkout> {
  return api<Checkout>(`/admin/pos/checkouts/${checkoutId}/lines/${lineId}`, { ...auth, method: 'DELETE' });
}

export async function importAppointment(checkoutId: string, appointmentId: string): Promise<Checkout> {
  return api<Checkout>(`/admin/pos/checkouts/${checkoutId}/import-appointment`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify({ appointment_id: appointmentId }),
  });
}

export async function applyDepositCredit(checkoutId: string, depositRecordId?: string): Promise<Checkout> {
  return api<Checkout>(`/admin/pos/checkouts/${checkoutId}/apply-deposit-credit`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(depositRecordId ? { deposit_record_id: depositRecordId } : {}),
  });
}

export async function removeDepositCredit(checkoutId: string): Promise<Checkout> {
  return api<Checkout>(`/admin/pos/checkouts/${checkoutId}/deposit-credit`, { ...auth, method: 'DELETE' });
}

export async function recordCheckoutPayments(
  checkoutId: string,
  tenders: Array<{ amount_cents: number; payment_method_type: string; payment_method_label?: string }>,
): Promise<Checkout> {
  return api<Checkout>(`/admin/pos/checkouts/${checkoutId}/payments`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify({ tenders }),
  });
}

export async function fetchCheckoutPayments(checkoutId: string): Promise<PaymentTransaction[]> {
  return api<PaymentTransaction[]>(`/admin/pos/checkouts/${checkoutId}/payments`, auth);
}

export async function completeCheckout(checkoutId: string): Promise<Checkout> {
  return api<Checkout>(`/admin/pos/checkouts/${checkoutId}/complete`, { ...auth, method: 'POST' });
}

export async function voidCheckout(checkoutId: string): Promise<Checkout> {
  return api<Checkout>(`/admin/pos/checkouts/${checkoutId}/void`, { ...auth, method: 'POST' });
}

export async function fetchPosCatalogServices(): Promise<PosCatalogService[]> {
  return api<PosCatalogService[]>('/admin/pos/catalog/services', auth);
}

export async function fetchPosCatalogRetail(): Promise<PosCatalogRetailItem[]> {
  return api<PosCatalogRetailItem[]>('/admin/pos/catalog/retail', auth);
}

export async function fetchCheckoutRefunds(checkoutId: string): Promise<CheckoutRefund[]> {
  return api<CheckoutRefund[]>(`/admin/pos/checkouts/${checkoutId}/refunds`, auth);
}

export async function createCheckoutRefund(
  checkoutId: string,
  data: { amount_cents?: number; reason: string; notes?: string },
): Promise<{ refund: CheckoutRefund; checkout: Checkout }> {
  return api(`/admin/pos/checkouts/${checkoutId}/refunds`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function processCheckoutReturn(
  checkoutId: string,
  data: { line_id: string; quantity: number; reason?: string; refund_immediately?: boolean },
): Promise<Checkout> {
  return api<Checkout>(`/admin/pos/checkouts/${checkoutId}/returns`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function reopenCheckout(checkoutId: string, reason: string): Promise<Checkout> {
  return api<Checkout>(`/admin/pos/checkouts/${checkoutId}/reopen`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify({ reason }),
  });
}

export async function addGiftCardLine(
  checkoutId: string,
  data: { amount_cents: number; description?: string },
): Promise<Checkout> {
  return api<Checkout>(`/admin/pos/checkouts/${checkoutId}/lines/gift-card`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function applyGiftCard(checkoutId: string, code: string, amountCents?: number): Promise<Checkout> {
  return api<Checkout>(`/admin/pos/checkouts/${checkoutId}/apply-gift-card`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify({ code, amount_cents: amountCents }),
  });
}

export async function fetchCheckoutReceipts(checkoutId: string): Promise<CheckoutReceipt[]> {
  return api<CheckoutReceipt[]>(`/admin/pos/checkouts/${checkoutId}/receipts`, auth);
}

export async function resendCheckoutReceipt(
  checkoutId: string,
  data: { delivery_method: string; delivery_target?: string },
): Promise<CheckoutReceipt> {
  return api<CheckoutReceipt>(`/admin/pos/checkouts/${checkoutId}/receipts/resend`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function fetchEligibleAppointments(params?: {
  location_id?: string;
  client_id?: string;
}): Promise<EligibleAppointment[]> {
  const search = new URLSearchParams();
  if (params?.location_id) search.set('location_id', params.location_id);
  if (params?.client_id) search.set('client_id', params.client_id);
  const query = search.toString();
  return api<EligibleAppointment[]>(`/admin/pos/appointments/eligible${query ? `?${query}` : ''}`, auth);
}

export async function fetchCheckoutMembershipOptions(checkoutId: string): Promise<import('@/lib/pos-types').CheckoutMembershipOptions> {
  return api(`/admin/pos/checkouts/${checkoutId}/membership-options`, auth);
}

export async function applyCheckoutWallet(checkoutId: string, amountCents: number): Promise<Checkout> {
  return api<Checkout>(`/admin/pos/checkouts/${checkoutId}/apply-wallet`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify({ amount_cents: amountCents }),
  });
}

export async function removeCheckoutWallet(checkoutId: string): Promise<Checkout> {
  return api<Checkout>(`/admin/pos/checkouts/${checkoutId}/remove-wallet`, { ...auth, method: 'POST' });
}

export async function applyCheckoutLoyalty(checkoutId: string, points: number): Promise<Checkout> {
  return api<Checkout>(`/admin/pos/checkouts/${checkoutId}/apply-loyalty`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify({ points }),
  });
}

export async function removeCheckoutLoyalty(checkoutId: string): Promise<Checkout> {
  return api<Checkout>(`/admin/pos/checkouts/${checkoutId}/remove-loyalty`, { ...auth, method: 'POST' });
}

export async function applyCheckoutPackage(
  checkoutId: string,
  lineId: string,
  clientPackageId: string,
  quantity?: number,
): Promise<Checkout> {
  return api<Checkout>(`/admin/pos/checkouts/${checkoutId}/lines/${lineId}/apply-package`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify({ client_package_id: clientPackageId, quantity }),
  });
}

export async function removeCheckoutPackage(checkoutId: string, lineId: string): Promise<Checkout> {
  return api<Checkout>(`/admin/pos/checkouts/${checkoutId}/lines/${lineId}/remove-package`, {
    ...auth,
    method: 'POST',
  });
}
