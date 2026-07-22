import type { ReactNode } from 'react';

interface AnalyticsStatCardProps {
  label: string;
  value: ReactNode;
  hint?: string;
}

export function AnalyticsStatCard({ label, value, hint }: AnalyticsStatCardProps) {
  return (
    <div className="rounded-2xl border border-[var(--admin-line)] bg-[var(--admin-surface)] p-4 shadow-[0_1px_2px_rgba(28,25,23,0.04)]">
      <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-[var(--admin-muted)]">
        {label}
      </p>
      <p className="mt-2 text-2xl font-semibold tracking-tight text-[var(--admin-ink)]">{value}</p>
      {hint ? <p className="mt-1.5 text-xs text-[var(--admin-muted)]">{hint}</p> : null}
    </div>
  );
}
