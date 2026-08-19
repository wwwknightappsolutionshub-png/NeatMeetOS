import { api } from '@/lib/api-client';
import type { MoneyEntry, MoneySummary } from '@/lib/money-types';

const auth = { auth: true as const, tenant: true as const };

export async function fetchMoneySummary(month?: string): Promise<MoneySummary> {
  const q = month ? `?month=${encodeURIComponent(month)}` : '';
  return api<MoneySummary>(`/admin/money/summary${q}`, auth);
}

export async function createMoneyEntry(payload: {
  kind: 'cash_in' | 'spend';
  category?: string;
  amount_pounds: number;
  occurred_on: string;
  note?: string;
}): Promise<MoneyEntry> {
  return api<MoneyEntry>('/admin/money/entries', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function deleteMoneyEntry(id: string): Promise<void> {
  await api(`/admin/money/entries/${id}`, {
    ...auth,
    method: 'DELETE',
  });
}
