interface AnalyticsEmptyStateProps {
  message?: string;
}

export function AnalyticsEmptyState({ message = 'No data for this period.' }: AnalyticsEmptyStateProps) {
  return (
    <div className="rounded-md border border-dashed border-zinc-200 bg-zinc-50 px-3 py-4 text-center text-sm text-zinc-500">
      {message}
    </div>
  );
}
