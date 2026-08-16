import { api } from '@/lib/api-client';
import type {
  DepositInspect,
  PaymentRefund,
  PaymentSummary,
  PaymentTransaction,
  ReservationPaymentDocument,
  TenantPaymentsSettings,
} from '@/lib/payments-types';

const auth = { auth: true as const, tenant: true as const };

export async function fetchPayments(params?: {
  status?: string;
  transaction_type?: string;
  client_id?: string;
  appointment_id?: string;
  from?: string;
  to?: string;
}): Promise<PaymentTransaction[]> {
  const search = new URLSearchParams();
  if (params?.status) search.set('status', params.status);
  if (params?.transaction_type) search.set('transaction_type', params.transaction_type);
  if (params?.client_id) search.set('client_id', params.client_id);
  if (params?.appointment_id) search.set('appointment_id', params.appointment_id);
  if (params?.from) search.set('from', params.from);
  if (params?.to) search.set('to', params.to);
  const query = search.toString();

  return api<PaymentTransaction[]>(`/admin/payments${query ? `?${query}` : ''}`, auth);
}

export async function fetchPayment(id: string): Promise<PaymentTransaction> {
  return api<PaymentTransaction>(`/admin/payments/${id}`, auth);
}

export async function fetchPaymentSummary(params?: {
  from?: string;
  to?: string;
}): Promise<PaymentSummary> {
  const search = new URLSearchParams();
  if (params?.from) search.set('from', params.from);
  if (params?.to) search.set('to', params.to);
  const query = search.toString();

  return api<PaymentSummary>(`/admin/payments/summary${query ? `?${query}` : ''}`, auth);
}

export async function fetchFailedPayments(from?: string): Promise<PaymentTransaction[]> {
  const query = from ? `?from=${encodeURIComponent(from)}` : '';
  return api<PaymentTransaction[]>(`/admin/payments/failed${query}`, auth);
}

export async function createManualPayment(
  data: Partial<PaymentTransaction> & {
    amount_cents: number;
    transaction_type: string;
    allocations?: Array<{
      allocation_type: string;
      amount_cents: number;
      appointment_id?: string;
    }>;
  },
): Promise<PaymentTransaction> {
  return api<PaymentTransaction>('/admin/payments/manual', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function createPaymentLink(
  data: Partial<PaymentTransaction> & { amount_cents: number; transaction_type: string },
): Promise<PaymentTransaction> {
  return api<PaymentTransaction>('/admin/payments/payment-link', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function markPaymentSucceeded(id: string): Promise<PaymentTransaction> {
  return api<PaymentTransaction>(`/admin/payments/${id}/mark-succeeded`, {
    ...auth,
    method: 'POST',
  });
}

export async function markPaymentFailed(
  id: string,
  data?: { failure_code?: string; failure_message?: string },
): Promise<PaymentTransaction> {
  return api<PaymentTransaction>(`/admin/payments/${id}/mark-failed`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data ?? {}),
  });
}

export async function cancelPayment(id: string): Promise<PaymentTransaction> {
  return api<PaymentTransaction>(`/admin/payments/${id}/cancel`, {
    ...auth,
    method: 'POST',
  });
}

export async function fetchPaymentRefunds(paymentId: string): Promise<PaymentRefund[]> {
  return api<PaymentRefund[]>(`/admin/payments/${paymentId}/refunds`, auth);
}

export async function createPaymentRefund(
  paymentId: string,
  data?: { amount_cents?: number; reason?: string },
): Promise<PaymentRefund> {
  return api<PaymentRefund>(`/admin/payments/${paymentId}/refunds`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data ?? {}),
  });
}

export async function fetchAppointmentDeposit(appointmentId: string): Promise<DepositInspect> {
  return api<DepositInspect>(`/admin/appointments/${appointmentId}/deposit`, auth);
}

export async function recordAppointmentDepositPayment(
  appointmentId: string,
  data?: {
    amount_cents?: number;
    payment_method_type?: string;
    payment_method_label?: string;
    external_reference?: string;
  },
): Promise<{
  deposit_record: Record<string, unknown>;
  payment_transaction: PaymentTransaction;
  appointment: { id: string; deposit_status: string; deposit_required_cents?: number };
}> {
  return api(`/admin/appointments/${appointmentId}/deposit/pay`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data ?? {}),
  });
}

export async function waiveAppointmentDeposit(
  appointmentId: string,
  notes?: string,
): Promise<{ deposit_record: Record<string, unknown>; appointment: { deposit_status: string } }> {
  return api(`/admin/appointments/${appointmentId}/deposit/waive`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify({ notes }),
  });
}

export async function refundAppointmentDeposit(
  appointmentId: string,
  data?: { amount_cents?: number; reason?: string },
): Promise<{ refund: PaymentRefund; deposit_record: Record<string, unknown> }> {
  return api(`/admin/appointments/${appointmentId}/deposit/refund`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data ?? {}),
  });
}

export async function fetchTenantPaymentsSettings(): Promise<TenantPaymentsSettings> {
  return api<TenantPaymentsSettings>('/admin/payments/settings', auth);
}

export async function updateTenantPaymentsSettings(
  data: Partial<TenantPaymentsSettings>,
): Promise<TenantPaymentsSettings> {
  return api<TenantPaymentsSettings>('/admin/payments/settings', {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function fetchReservationPaymentDocuments(params?: {
  status?: string;
}): Promise<ReservationPaymentDocument[]> {
  const search = new URLSearchParams();
  if (params?.status) search.set('status', params.status);
  const query = search.toString();
  const data = await api<{ items: ReservationPaymentDocument[] }>(
    `/admin/payments/reservation-documents${query ? `?${query}` : ''}`,
    auth,
  );
  return data.items;
}

export async function fetchReservationPaymentDocument(
  id: string,
): Promise<ReservationPaymentDocument> {
  return api<ReservationPaymentDocument>(`/admin/payments/reservation-documents/${id}`, auth);
}

export async function confirmReservationPaymentDocument(
  id: string,
  note?: string,
): Promise<ReservationPaymentDocument> {
  return api<ReservationPaymentDocument>(`/admin/payments/reservation-documents/${id}/confirm`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify({ note }),
  });
}

export async function rejectReservationPaymentDocument(
  id: string,
  note?: string,
): Promise<ReservationPaymentDocument> {
  return api<ReservationPaymentDocument>(`/admin/payments/reservation-documents/${id}/reject`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify({ note }),
  });
}
