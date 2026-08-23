'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminModuleChrome } from '@/components/admin/AdminModuleChrome';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { formatMoneyCents } from '@/lib/analytics-types';
import {
  monthBounds,
  shiftYearMonth,
  type MoneyLedger,
  type MoneySummary,
} from '@/lib/money-types';
import {
  createMoneyEntry,
  deleteMoneyEntry,
  fetchMoneyLedger,
  fetchMoneySummary,
} from '@/services/money.service';

function todayIsoDate(): string {
  const now = new Date();
  const y = now.getFullYear();
  const m = String(now.getMonth() + 1).padStart(2, '0');
  const d = String(now.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

export default function AdminMyMoneyPage() {
  const [summary, setSummary] = useState<MoneySummary | null>(null);
  const [month, setMonth] = useState<string | undefined>(undefined);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const [cashAmount, setCashAmount] = useState('');
  const [cashDate, setCashDate] = useState(todayIsoDate);
  const [cashNote, setCashNote] = useState('');

  const [spendAmount, setSpendAmount] = useState('');
  const [spendDate, setSpendDate] = useState(todayIsoDate);
  const [spendCategory, setSpendCategory] = useState('rent');
  const [spendNote, setSpendNote] = useState('');

  const [ledger, setLedger] = useState<MoneyLedger | null>(null);
  const [ledgerLoading, setLedgerLoading] = useState(false);
  const [ledgerFrom, setLedgerFrom] = useState(todayIsoDate().slice(0, 8) + '01');
  const [ledgerTo, setLedgerTo] = useState(todayIsoDate());
  const [ledgerDirection, setLedgerDirection] = useState<'all' | 'inflow' | 'outflow'>('all');

  const loadLedger = useCallback(
    (from: string, to: string, direction: 'all' | 'inflow' | 'outflow') => {
      setLedgerLoading(true);
      fetchMoneyLedger({ from, to, direction })
        .then((data) => {
          setLedger(data);
          setLedgerFrom(data.from);
          setLedgerTo(data.to);
        })
        .catch((e) => setError(e instanceof Error ? e.message : 'Could not load cash flow table'))
        .finally(() => setLedgerLoading(false));
    },
    [],
  );

  const load = useCallback(
    (nextMonth?: string) => {
      setLoading(true);
      setError(null);
      fetchMoneySummary(nextMonth)
        .then((data) => {
          setSummary(data);
          setMonth(data.month);
          setSpendCategory((current) =>
            data.spend_categories.some((c) => c.key === current)
              ? current
              : (data.spend_categories[0]?.key ?? 'rent'),
          );
          const bounds = monthBounds(data.month);
          setLedgerFrom(bounds.from);
          setLedgerTo(bounds.to);
          loadLedger(bounds.from, bounds.to, ledgerDirection);
        })
        .catch((e) => setError(e instanceof Error ? e.message : 'Could not load My Finance So Far'))
        .finally(() => setLoading(false));
    },
    [ledgerDirection, loadLedger],
  );

  useEffect(() => {
    load();
    // Initial load only — month navigation / filters call load/loadLedger explicitly.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function handleAddCash(e: React.FormEvent) {
    e.preventDefault();
    const pounds = Number(cashAmount);
    if (!Number.isFinite(pounds) || pounds <= 0) {
      setError('Enter how much cash you earned.');
      return;
    }
    setSaving(true);
    setError(null);
    try {
      await createMoneyEntry({
        kind: 'cash_in',
        amount_pounds: pounds,
        occurred_on: cashDate,
        note: cashNote.trim() || undefined,
      });
      setCashAmount('');
      setCashNote('');
      load(month);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not save');
    } finally {
      setSaving(false);
    }
  }

  async function handleAddSpend(e: React.FormEvent) {
    e.preventDefault();
    const pounds = Number(spendAmount);
    if (!Number.isFinite(pounds) || pounds <= 0) {
      setError('Enter how much you spent.');
      return;
    }
    setSaving(true);
    setError(null);
    try {
      await createMoneyEntry({
        kind: 'spend',
        category: spendCategory,
        amount_pounds: pounds,
        occurred_on: spendDate,
        note: spendNote.trim() || undefined,
      });
      setSpendAmount('');
      setSpendNote('');
      load(month);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not save');
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(id: string) {
    setSaving(true);
    setError(null);
    try {
      await deleteMoneyEntry(id);
      load(month);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not remove');
    } finally {
      setSaving(false);
    }
  }

  function applyLedgerFilter(e: React.FormEvent) {
    e.preventDefault();
    if (!ledgerFrom || !ledgerTo) {
      setError('Pick a from and to date for the cash flow table.');
      return;
    }
    if (ledgerFrom > ledgerTo) {
      setError('“From” must be on or before “To”.');
      return;
    }
    setError(null);
    loadLedger(ledgerFrom, ledgerTo, ledgerDirection);
  }

  return (
    <AdminModuleChrome eyebrow="Commerce" title="My Finance So Far" links={[]}>
      <p className="mb-6 max-w-2xl text-sm text-zinc-600">
        See what you earned (Income), what you spent, and your account balance. This is a simple
        notebook — not tax software.
      </p>

      {error ? (
        <div className="mb-4">
          <ErrorAlert message={error} />
        </div>
      ) : null}

      <div className="mb-6 flex flex-wrap items-center gap-2">
        <Button
          type="button"
          variant="secondary"
          disabled={!month || loading}
          onClick={() => month && load(shiftYearMonth(month, -1))}
        >
          Previous month
        </Button>
        <p className="min-w-[10rem] text-center text-sm font-semibold text-zinc-900">
          {summary?.month_label ?? 'This month'}
        </p>
        <Button
          type="button"
          variant="secondary"
          disabled={!month || loading}
          onClick={() => month && load(shiftYearMonth(month, 1))}
        >
          Next month
        </Button>
      </div>

      {loading && !summary ? <LoadingState label="Loading your finance…" /> : null}

      {summary ? (
        <div className="space-y-6">
          <Card title="This month">
            <p className="text-base font-medium text-zinc-900">{summary.sentence}</p>
            <div className="mt-4 grid gap-3 sm:grid-cols-3">
              <div>
                <p className="text-xs uppercase tracking-wide text-zinc-500">Earned So Far</p>
                <p className="text-lg font-semibold">{formatMoneyCents(summary.taken_cents)}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-wide text-zinc-500">Spent</p>
                <p className="text-lg font-semibold">{formatMoneyCents(summary.spent_cents)}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-wide text-zinc-500">Your Account Balance</p>
                <p className="text-lg font-semibold">{formatMoneyCents(summary.left_cents)}</p>
              </div>
            </div>
          </Card>

          <div className="grid gap-6 lg:grid-cols-2">
            <Card title="Earned So Far (Income)">
              <ul className="mb-4 space-y-1 text-sm text-zinc-600">
                <li>
                  From cards / the app:{' '}
                  <span className="font-medium text-zinc-900">
                    {formatMoneyCents(summary.taken_breakdown.from_cards_and_app_cents)}
                  </span>
                </li>
                <li>
                  From the till:{' '}
                  <span className="font-medium text-zinc-900">
                    {formatMoneyCents(summary.taken_breakdown.from_till_cents)}
                  </span>
                </li>
                <li>
                  Cash I added:{' '}
                  <span className="font-medium text-zinc-900">
                    {formatMoneyCents(summary.taken_breakdown.cash_you_added_cents)}
                  </span>
                </li>
              </ul>

              {summary.cash_you_added.length > 0 ? (
                <ul className="mb-4 space-y-2">
                  {summary.cash_you_added.map((row) => (
                    <li
                      key={row.id}
                      className="flex items-start justify-between gap-2 rounded-md border border-zinc-200 px-3 py-2 text-sm"
                    >
                      <div>
                        <p className="font-medium">{formatMoneyCents(row.amount_cents)}</p>
                        <p className="text-xs text-zinc-500">
                          {row.occurred_on}
                          {row.note ? ` · ${row.note}` : ''}
                        </p>
                      </div>
                      <button
                        type="button"
                        className="text-xs font-semibold text-zinc-500 hover:text-zinc-800"
                        onClick={() => void handleDelete(row.id)}
                        disabled={saving}
                      >
                        Remove
                      </button>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="mb-4 text-sm text-zinc-500">
                  No extra cash added this month. Card and till money fills itself.
                </p>
              )}

              <form onSubmit={(e) => void handleAddCash(e)} className="grid gap-3">
                <p className="text-sm font-semibold text-zinc-800">Add cash I earned</p>
                <Field label="How much (£)">
                  <input
                    className={inputClass}
                    inputMode="decimal"
                    placeholder="20.00"
                    value={cashAmount}
                    onChange={(e) => setCashAmount(e.target.value)}
                  />
                </Field>
                <Field label="When">
                  <input
                    className={inputClass}
                    type="date"
                    value={cashDate}
                    onChange={(e) => setCashDate(e.target.value)}
                    required
                  />
                </Field>
                <Field label="Note (optional)">
                  <input
                    className={inputClass}
                    value={cashNote}
                    onChange={(e) => setCashNote(e.target.value)}
                    placeholder="Walk-in cash"
                  />
                </Field>
                <Button type="submit" disabled={saving}>
                  {saving ? 'Saving…' : 'Save cash earned'}
                </Button>
              </form>
            </Card>

            <Card title="Spent (money out)">
              {summary.spends.length > 0 ? (
                <ul className="mb-4 space-y-2">
                  {summary.spends.map((row) => (
                    <li
                      key={row.id}
                      className="flex items-start justify-between gap-2 rounded-md border border-zinc-200 px-3 py-2 text-sm"
                    >
                      <div>
                        <p className="font-medium">
                          {formatMoneyCents(row.amount_cents)} · {row.category_label}
                        </p>
                        <p className="text-xs text-zinc-500">
                          {row.occurred_on}
                          {row.note ? ` · ${row.note}` : ''}
                        </p>
                      </div>
                      <button
                        type="button"
                        className="text-xs font-semibold text-zinc-500 hover:text-zinc-800"
                        onClick={() => void handleDelete(row.id)}
                        disabled={saving}
                      >
                        Remove
                      </button>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="mb-4 text-sm text-zinc-500">
                  No spends logged this month. Add rent, products, ads — whatever left your pocket.
                </p>
              )}

              <form onSubmit={(e) => void handleAddSpend(e)} className="grid gap-3">
                <p className="text-sm font-semibold text-zinc-800">Add spend</p>
                <Field label="How much (£)">
                  <input
                    className={inputClass}
                    inputMode="decimal"
                    placeholder="50.00"
                    value={spendAmount}
                    onChange={(e) => setSpendAmount(e.target.value)}
                  />
                </Field>
                <Field label="What for">
                  <select
                    className={inputClass}
                    value={spendCategory}
                    onChange={(e) => setSpendCategory(e.target.value)}
                  >
                    {summary.spend_categories.map((c) => (
                      <option key={c.key} value={c.key}>
                        {c.label}
                      </option>
                    ))}
                  </select>
                </Field>
                <Field label="When">
                  <input
                    className={inputClass}
                    type="date"
                    value={spendDate}
                    onChange={(e) => setSpendDate(e.target.value)}
                    required
                  />
                </Field>
                <Field label="Note (optional)">
                  <input
                    className={inputClass}
                    value={spendNote}
                    onChange={(e) => setSpendNote(e.target.value)}
                    placeholder="August chair rent"
                  />
                </Field>
                <Button type="submit" disabled={saving}>
                  {saving ? 'Saving…' : 'Save spend'}
                </Button>
              </form>
            </Card>
          </div>

          <Card title="Coming up">
            <p className="text-sm text-zinc-700">
              Already booked for {summary.coming_up.next_month_label}:{' '}
              <span className="font-semibold">
                {formatMoneyCents(summary.coming_up.booked_cents)}
              </span>
              {summary.coming_up.booked_visits > 0
                ? ` · ${summary.coming_up.booked_visits} visit${summary.coming_up.booked_visits === 1 ? '' : 's'}`
                : ''}
            </p>
            <p className="mt-2 text-sm text-zinc-700">
              {summary.coming_up.usual_spend_months_used > 0 ? (
                <>
                  You usually spend about{' '}
                  <span className="font-semibold">
                    {formatMoneyCents(summary.coming_up.usual_spend_cents)}
                  </span>{' '}
                  a month. Rough leftover:{' '}
                  <span className="font-semibold">
                    {formatMoneyCents(summary.coming_up.rough_left_cents)}
                  </span>
                  .
                </>
              ) : (
                <>Add a few spends so we can guess your usual month.</>
              )}
            </p>
            <p className="mt-3 text-xs text-zinc-500">{summary.coming_up.warning}</p>
          </Card>

          <Card title="Cash flow (in & out)">
            <p className="mb-4 text-sm text-zinc-600">
              Every inflow and outflow in the dates you pick — cards, till, cash you added, and
              spends.
            </p>

            <form
              onSubmit={applyLedgerFilter}
              className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end"
            >
              <Field label="From">
                <input
                  className={inputClass}
                  type="date"
                  value={ledgerFrom}
                  onChange={(e) => setLedgerFrom(e.target.value)}
                  required
                />
              </Field>
              <Field label="To">
                <input
                  className={inputClass}
                  type="date"
                  value={ledgerTo}
                  onChange={(e) => setLedgerTo(e.target.value)}
                  required
                />
              </Field>
              <Field label="Show">
                <select
                  className={inputClass}
                  value={ledgerDirection}
                  onChange={(e) =>
                    setLedgerDirection(e.target.value as 'all' | 'inflow' | 'outflow')
                  }
                >
                  <option value="all">All</option>
                  <option value="inflow">Inflow only</option>
                  <option value="outflow">Outflow only</option>
                </select>
              </Field>
              <Button type="submit" variant="secondary" disabled={ledgerLoading}>
                {ledgerLoading ? 'Loading…' : 'Apply dates'}
              </Button>
            </form>

            {ledger ? (
              <div className="mb-4 grid gap-3 sm:grid-cols-3">
                <div>
                  <p className="text-xs uppercase tracking-wide text-zinc-500">Inflow</p>
                  <p className="text-lg font-semibold text-emerald-800">
                    {formatMoneyCents(ledger.inflow_cents)}
                  </p>
                </div>
                <div>
                  <p className="text-xs uppercase tracking-wide text-zinc-500">Outflow</p>
                  <p className="text-lg font-semibold text-rose-800">
                    {formatMoneyCents(ledger.outflow_cents)}
                  </p>
                </div>
                <div>
                  <p className="text-xs uppercase tracking-wide text-zinc-500">Net</p>
                  <p className="text-lg font-semibold">{formatMoneyCents(ledger.net_cents)}</p>
                </div>
              </div>
            ) : null}

            {ledgerLoading && !ledger ? (
              <LoadingState label="Loading cash flow…" />
            ) : ledger && ledger.rows.length === 0 ? (
              <p className="text-sm text-zinc-500">No cash movement in this date range.</p>
            ) : ledger ? (
              <div className="overflow-x-auto rounded-lg border border-zinc-200">
                <table className="min-w-full text-left text-sm">
                  <thead className="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
                    <tr>
                      <th className="px-3 py-2 font-semibold">Date</th>
                      <th className="px-3 py-2 font-semibold">Type</th>
                      <th className="px-3 py-2 font-semibold">Source</th>
                      <th className="px-3 py-2 font-semibold">Note</th>
                      <th className="px-3 py-2 text-right font-semibold">Amount</th>
                      <th className="px-3 py-2 font-semibold">
                        <span className="sr-only">Actions</span>
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {ledger.rows.map((row) => (
                      <tr key={row.id} className="border-t border-zinc-100">
                        <td className="whitespace-nowrap px-3 py-2 text-zinc-700">
                          {row.occurred_on}
                        </td>
                        <td className="px-3 py-2">
                          <span
                            className={
                              row.direction === 'inflow'
                                ? 'font-medium text-emerald-800'
                                : 'font-medium text-rose-800'
                            }
                          >
                            {row.direction_label}
                          </span>
                        </td>
                        <td className="px-3 py-2 text-zinc-800">{row.source}</td>
                        <td className="max-w-[12rem] truncate px-3 py-2 text-zinc-500">
                          {row.note || '—'}
                        </td>
                        <td className="whitespace-nowrap px-3 py-2 text-right font-medium text-zinc-900">
                          {row.direction === 'outflow' ? '−' : ''}
                          {formatMoneyCents(row.amount_cents)}
                        </td>
                        <td className="px-3 py-2 text-right">
                          {row.removable && row.entry_id ? (
                            <button
                              type="button"
                              className="text-xs font-semibold text-zinc-500 hover:text-zinc-800"
                              onClick={() => void handleDelete(row.entry_id!)}
                              disabled={saving}
                            >
                              Remove
                            </button>
                          ) : null}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : null}
          </Card>
        </div>
      ) : null}
    </AdminModuleChrome>
  );
}
