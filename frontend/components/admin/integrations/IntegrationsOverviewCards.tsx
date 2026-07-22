import { AnalyticsStatCard } from '@/components/admin/analytics/AnalyticsStatCard';
import { INTEGRATIONS_LIST_WINDOW, type IntegrationsOverviewSummary } from '@/lib/integrations-types';

interface IntegrationsOverviewCardsProps {
  summary: IntegrationsOverviewSummary;
}

export function IntegrationsOverviewCards({ summary }: IntegrationsOverviewCardsProps) {
  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <AnalyticsStatCard
        label="Provider accounts"
        value={String(summary.total_accounts)}
        hint={`${summary.active_accounts} active`}
      />
      <AnalyticsStatCard
        label="Default accounts"
        value={String(summary.default_accounts)}
        hint="One default per category"
      />
      <AnalyticsStatCard
        label="Attempts (latest window)"
        value={String(summary.recent_attempts)}
        hint={
          summary.attempts_truncated
            ? `${summary.failed_attempts} failed · capped at ${INTEGRATIONS_LIST_WINDOW}`
            : `${summary.failed_attempts} failed in loaded window`
        }
      />
      <AnalyticsStatCard
        label="Webhook events (latest window)"
        value={String(summary.received_webhook_events)}
        hint={
          summary.events_truncated
            ? `Capped at ${INTEGRATIONS_LIST_WINDOW} most recent`
            : 'Append-only intake log'
        }
      />
    </div>
  );
}
