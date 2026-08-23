import { api } from '@/lib/api-client';
import type { MoneyEntry, MoneyLedger, MoneySummary } from '@/lib/money-types';

const auth = { auth: true as const, tenant: true as const };

export async function fetchMoneySummary(month?: string): Promise<MoneySummary> {
  const q = month ? `?month=${encodeURIComponent(month)}` : '';
  return api<MoneySummary>(`/admin/money/summary${q}`, auth);
}

export async function fetchMoneyLedger(params: {
  from?: string;
  to?: string;
  direction?: 'all' | 'inflow' | 'outflow';
}): Promise<MoneyLedger> {
  const search = new URLSearchParams();
  if (params.from) search.set('from', params.from);
  if (params.to) search.set('to', params.to);
  if (params.direction && params.direction !== 'all') {
    search.set('direction', params.direction);
  }
  const q = search.toString() ? `?${search}` : '';
  return api<MoneyLedger>(`/admin/money/ledger${q}`, auth);
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
