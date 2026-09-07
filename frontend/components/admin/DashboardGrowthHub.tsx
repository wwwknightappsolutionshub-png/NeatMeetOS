'use client';

import Link from 'next/link';
import { AnalyticsSectionCard } from '@/components/admin/analytics/AnalyticsSectionCard';
import { AnalyticsStatCard } from '@/components/admin/analytics/AnalyticsStatCard';
import {
  formatMoneyCents,
  formatNumber,
  formatPercentRate,
  type BusinessPerformanceIntelligence,
} from '@/lib/analytics-types';

function insightTone(severity: string): string {
  if (severity === 'warning') return 'border-amber-200 bg-amber-50 text-amber-950';
  if (severity === 'success') return 'border-emerald-200 bg-emerald-50 text-emerald-950';
  return 'border-[var(--admin-line)] bg-[var(--admin-wash)] text-[var(--admin-ink)]';
}

type Props = {
  data: BusinessPerformanceIntelligence;
};

/**
 * Dashboard growth hub — presentational only.
 * Consumes Business Performance Intelligence payload; does not alter BPI formulas
 * or existing dashboard ops widgets.
 */
export function DashboardGrowthHub({ data }: Props) {
  const bp = data.business_performance;
  const ci = data.customer_intelligence;
  const rr = data.repeat_revenue_opportunity;
  const actions = data.action_center.filter((task) => task.count > 0);
  const insights = data.business_insights;

  return (
    <section className="space-y-4" aria-label="Business growth summary">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-[var(--admin-moss,#2f5a45)]">
            Growth
          </p>
          <h2 className="mt-1 text-lg font-semibold tracking-tight text-[var(--admin-ink)]">
            Business growth summary
          </h2>
          <p className="mt-1 text-sm text-[var(--admin-muted)]">
            Live salon metrics from Business Performance Intelligence — month to date unless noted.
          </p>
        </div>
        <Link
          href="/admin/analytics/intelligence"
          className="text-sm font-semibold text-[var(--admin-moss,#2f5a45)] underline-offset-2 hover:underline"
        >
          Full intelligence →
        </Link>
      </div>

      <AnalyticsSectionCard title="Performance snapshot">
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <AnalyticsStatCard
            label="Served today"
            value={formatNumber(bp.customers_served_today)}
          />
          <AnalyticsStatCard
            label="Served this month"
            value={formatNumber(bp.customers_served_month)}
            hint={`${formatNumber(bp.customers_served_week)} this week`}
          />
          <AnalyticsStatCard
            label="Revenue (MTD)"
            value={formatMoneyCents(bp.total_revenue_cents)}
            hint={`Avg spend ${formatMoneyCents(bp.average_spend_cents)}`}
          />
          <AnalyticsStatCard
            label="Repeat opportunity"
            value={formatMoneyCents(rr.estimated_opportunity_cents)}
            hint={`${formatNumber(rr.clients_due_soon)} due · ${formatNumber(rr.clients_overdue)} overdue`}
          />
          <AnalyticsStatCard
            label="New customers (MTD)"
            value={formatNumber(bp.new_customers_month)}
          />
          <AnalyticsStatCard
            label="Returning (MTD)"
            value={formatNumber(bp.returning_customers_month)}
          />
          <AnalyticsStatCard label="Walk-ins (MTD)" value={formatNumber(bp.walk_ins_month)} />
          <AnalyticsStatCard
            label="Online bookings (MTD)"
            value={formatNumber(bp.online_bookings_month)}
          />
        </div>
      </AnalyticsSectionCard>

      <div className="grid gap-4 lg:grid-cols-2">
        <AnalyticsSectionCard title="Customer insights" href="/admin/analytics/intelligence">
          <div className="mb-4 rounded-xl border border-[var(--admin-line)] bg-[var(--admin-wash)] p-4">
            <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-[var(--admin-muted)]">
              Customer visibility
            </p>
            <p className="mt-1 text-3xl font-semibold tabular-nums text-[var(--admin-ink)]">
              {formatPercentRate(ci.visibility_rate)}
            </p>
            <p className="mt-1 text-sm text-[var(--admin-muted)]">
              Identified {formatNumber(ci.identified_served_month)} · Anonymous{' '}
              {formatNumber(ci.anonymous_served_month)}
            </p>
            <div className="mt-3 h-2 overflow-hidden rounded-full bg-stone-200">
              <div
                className="h-full rounded-full bg-[var(--admin-moss,#2f5a45)]"
                style={{ width: `${Math.min(100, Math.round(ci.visibility_rate * 100))}%` }}
              />
            </div>
          </div>
          <div className="grid gap-3 sm:grid-cols-3">
            <AnalyticsStatCard
              label="Returning %"
              value={formatPercentRate(ci.returning_rate)}
            />
            <AnalyticsStatCard
              label="First-time %"
              value={formatPercentRate(ci.first_time_rate)}
            />
            <AnalyticsStatCard
              label="Unidentified gap"
              value={formatNumber(ci.unidentified_gap_count)}
            />
          </div>
          {insights.length > 0 ? (
            <ul className="mt-4 space-y-2">
              {insights.slice(0, 4).map((insight) => (
                <li
                  key={insight.code}
                  className={`rounded-xl border px-3 py-2.5 text-sm ${insightTone(insight.severity)}`}
                >
                  <p>{insight.message}</p>
                  {insight.action_href ? (
                    <Link
                      href={insight.action_href}
                      className="mt-1.5 inline-block text-sm font-semibold underline-offset-2 hover:underline"
                    >
                      Take action →
                    </Link>
                  ) : null}
                </li>
              ))}
            </ul>
          ) : (
            <p className="mt-4 text-sm text-[var(--admin-muted)]">
              No special insights right now — keep capturing contacts and watching due clients.
            </p>
          )}
        </AnalyticsSectionCard>

        <AnalyticsSectionCard title="Action centre" href="/admin/analytics/intelligence">
          {actions.length === 0 ? (
            <p className="text-sm text-[var(--admin-muted)]">
              No growth actions need attention right now. Day-to-day ops alerts stay below in Needs
              attention.
            </p>
          ) : (
            <ul className="divide-y divide-[var(--admin-line)] rounded-xl border border-[var(--admin-line)] bg-[var(--admin-surface)]">
              {actions.map((task) => (
                <li
                  key={task.key}
                  className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                  <div className="min-w-0">
                    <p className="font-semibold text-[var(--admin-ink)]">
                      {task.label}{' '}
                      <span className="tabular-nums text-[var(--admin-muted)]">
                        ({formatNumber(task.count)})
                      </span>
                    </p>
                    <p className="mt-0.5 text-sm text-[var(--admin-muted)]">{task.why}</p>
                  </div>
                  <Link
                    href={task.href}
                    className="inline-flex shrink-0 items-center justify-center rounded-md bg-[var(--admin-moss,#2f5a45)] px-3 py-2 text-sm font-semibold text-white hover:opacity-90"
                  >
                    Open
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </AnalyticsSectionCard>
      </div>
    </section>
  );
}
