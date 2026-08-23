export interface MoneyCategoryOption {
  key: string;
  label: string;
}

export interface MoneyEntry {
  id: string;
  kind: 'cash_in' | 'spend';
  category: string;
  category_label: string;
  amount_cents: number;
  occurred_on: string;
  note: string | null;
  created_at: string | null;
}

export interface MoneyComingUp {
  next_month: string;
  next_month_label: string;
  booked_cents: number;
  booked_visits: number;
  usual_spend_cents: number;
  usual_spend_months_used: number;
  rough_left_cents: number;
  warning: string;
}

export interface MoneyLedgerRow {
  id: string;
  direction: 'inflow' | 'outflow';
  direction_label: string;
  source: string;
  amount_cents: number;
  occurred_on: string;
  note: string | null;
  removable: boolean;
  entry_id: string | null;
}

export interface MoneyLedger {
  from: string;
  to: string;
  direction: 'all' | 'inflow' | 'outflow';
  inflow_cents: number;
  outflow_cents: number;
  net_cents: number;
  rows: MoneyLedgerRow[];
}

export interface MoneySummary {
  month: string;
  month_label: string;
  taken_cents: number;
  spent_cents: number;
  left_cents: number;
  sentence: string;
  taken_breakdown: {
    from_cards_and_app_cents: number;
    from_till_cents: number;
    cash_you_added_cents: number;
  };
  cash_you_added: MoneyEntry[];
  spends: MoneyEntry[];
  spend_categories: MoneyCategoryOption[];
  coming_up: MoneyComingUp;
}

export function shiftYearMonth(ym: string, delta: number): string {
  const [year, month] = ym.split('-').map(Number);
  const date = new Date(Date.UTC(year, month - 1 + delta, 1));
  const y = date.getUTCFullYear();
  const m = String(date.getUTCMonth() + 1).padStart(2, '0');
  return `${y}-${m}`;
}

/** First/last day of a YYYY-MM month (UTC calendar dates as ISO strings). */
export function monthBounds(ym: string): { from: string; to: string } {
  const [year, month] = ym.split('-').map(Number);
  const from = `${ym}-01`;
  const lastDay = new Date(Date.UTC(year, month, 0)).getUTCDate();
  const to = `${ym}-${String(lastDay).padStart(2, '0')}`;
  return { from, to };
}
