import { AnalyticsEmptyState } from './AnalyticsEmptyState';
import { formatDay } from '@/lib/analytics-types';

interface DailyColumn {
  key: string;
  label: string;
  format?: (value: number) => string;
}

interface AnalyticsDailySeriesTableProps<T extends { date: string }> {
  rows: T[];
  columns: DailyColumn[];
  /** Hide days where every metric column is 0 to reduce noise. */
  hideEmptyDays?: boolean;
  emptyMessage?: string;
}

function cell(row: { date: string }, key: string): number {
  return Number((row as Record<string, unknown>)[key] ?? 0);
}

export function AnalyticsDailySeriesTable<T extends { date: string }>({
  rows,
  columns,
  hideEmptyDays = true,
  emptyMessage,
}: AnalyticsDailySeriesTableProps<T>) {
  const visible = hideEmptyDays
    ? rows.filter((row) => columns.some((col) => cell(row, col.key) !== 0))
    : rows;

  if (visible.length === 0) {
    return <AnalyticsEmptyState message={emptyMessage} />;
  }

  return (
    <div className="max-h-80 overflow-y-auto">
      <table className="w-full text-left text-sm">
        <thead className="sticky top-0 bg-white">
          <tr className="border-b text-zinc-500">
            <th className="py-2">Day</th>
            {columns.map((col) => (
              <th key={col.key} className="py-2 text-right">
                {col.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {visible.map((row) => (
            <tr key={row.date} className="border-b border-zinc-100">
              <td className="py-1.5">{formatDay(row.date)}</td>
              {columns.map((col) => {
                const raw = cell(row, col.key);
                return (
                  <td key={col.key} className="py-1.5 text-right tabular-nums">
                    {col.format ? col.format(raw) : raw}
                  </td>
                );
              })}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
