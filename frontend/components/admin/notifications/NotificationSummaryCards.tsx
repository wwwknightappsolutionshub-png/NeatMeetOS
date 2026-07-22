import { Card } from '@/components/ui/Card';
import type { NotificationReportingSummary } from '@/lib/notifications-types';

export function NotificationSummaryCards({ summary }: { summary: NotificationReportingSummary | null }) {
  const sent = (summary?.by_status?.sent ?? 0) + (summary?.by_status?.delivered ?? 0);
  const queued = (summary?.by_status?.queued ?? 0) + (summary?.by_status?.processing ?? 0);

  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <Card title="Sent (30 days)">
        <p className="text-2xl font-semibold">{summary ? sent : '—'}</p>
        <p className="mt-1 text-xs text-zinc-500">Delivered or sent</p>
      </Card>
      <Card title="Failed">
        <p className="text-2xl font-semibold">{summary ? summary.failed : '—'}</p>
        <p className="mt-1 text-xs text-zinc-500">Simulated failures</p>
      </Card>
      <Card title="Suppressed">
        <p className="text-2xl font-semibold">{summary ? summary.suppressed : '—'}</p>
        <p className="mt-1 text-xs text-zinc-500">Blocked by preference</p>
      </Card>
      <Card title="Queued">
        <p className="text-2xl font-semibold">{summary ? queued : '—'}</p>
        <p className="mt-1 text-xs text-zinc-500">Awaiting dispatch</p>
      </Card>
    </div>
  );
}
