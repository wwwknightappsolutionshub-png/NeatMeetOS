interface KeyValueRow {
  label: string;
  value: string | number;
}

interface AnalyticsKeyValueTableProps {
  rows: KeyValueRow[];
}

export function AnalyticsKeyValueTable({ rows }: AnalyticsKeyValueTableProps) {
  return (
    <ul className="space-y-1 text-sm">
      {rows.map((row) => (
        <li key={row.label} className="flex justify-between gap-3">
          <span className="text-zinc-600">{row.label}</span>
          <span className="font-medium">{row.value}</span>
        </li>
      ))}
    </ul>
  );
}
