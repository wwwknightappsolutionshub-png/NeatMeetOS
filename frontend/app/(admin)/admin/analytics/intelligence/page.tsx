'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { AdminAnalyticsShell } from '@/components/admin/analytics/AdminAnalyticsShell';
import { AnalyticsSectionCard } from '@/components/admin/analytics/AnalyticsSectionCard';
import { AnalyticsStatCard } from '@/components/admin/analytics/AnalyticsStatCard';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import {
  formatMoneyCents,
  formatNumber,
  formatPercentRate,
  type BusinessPerformanceIntelligence,
} from '@/lib/analytics-types';
import { fetchBusinessPerformanceIntelligence } from '@/services/analytics.service';

function insightTone(severity: string): string {
  if (severity === 'warning') return 'border-amber-200 bg-amber-50 text-amber-950';
  if (severity === 'success') return 'border-emerald-200 bg-emerald-50 text-emerald-950';
  return 'border-[var(--admin-line)] bg-[var(--admin-wash)] text-[var(--admin-ink)]';
}

export default function BusinessPerformanceIntelligencePage() {
  const [data, setData] = useState<BusinessPerformanceIntelligence | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    setError(null);
    fetchBusinessPerformanceIntelligence()
      .then(setData)
      .catch((e) =>
        setError(e instanceof Error ? e.message : 'Failed to load business intelligence'),
      )
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const bp = data?.business_performance;
  const ci = data?.customer_intelligence;
  const rr = data?.repeat_revenue_opportunity;

  return (
    <AdminAnalyticsShell title="Business Performance Intelligence">
      <p className="mb-4 max-w-3xl text-sm text-[var(--admin-muted)]">
        Action-oriented view of who you served, how visible your customers are, and what to do
        today. Formulas are defined in{' '}
        <span className="font-medium text-[var(--admin-ink)]">
          docs/BUSINESS_PERFORMANCE_INTELLIGENCE_METRICS.md
        </span>
        . Deep links open existing modules — nothing is duplicated here.
      </p>

      {error ? (
        <div className="mb-4">
          <ErrorAlert message={error} />
        </div>
      ) : null}
      {loading && !data ? <LoadingState label="Loading intelligence…" /> : null}

      {data && bp && ci && rr ? (
        <div className="space-y-6">
          {/* 1. Business performance */}
          <AnalyticsSectionCard title="1. Business performance">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <AnalyticsStatCard
                label="Served today"
                value={formatNumber(bp.customers_served_today)}
              />
              <AnalyticsStatCard
                label="Served this week"
                value={formatNumber(bp.customers_served_week)}
              />
              <AnalyticsStatCard
                label="Served this month"
                value={formatNumber(bp.customers_served_month)}
              />
              <AnalyticsStatCard
                label="Revenue (MTD)"
                value={formatMoneyCents(bp.total_revenue_cents)}
                hint={`Avg spend ${formatMoneyCents(bp.average_spend_cents)}`}
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

          {/* 2. Customer intelligence */}
          <AnalyticsSectionCard title="2. Customer intelligence">
            <div className="mb-4 rounded-xl border border-[var(--admin-line)] bg-[var(--admin-wash)] p-4">
              <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                  <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-[var(--admin-muted)]">
                    Customer visibility
                  </p>
                  <p className="mt-1 text-3xl font-semibold text-[var(--admin-ink)]">
                    {formatPercentRate(ci.visibility_rate)}
                  </p>
                  <p className="mt-1 text-sm text-[var(--admin-muted)]">
                    Identified customers you can remessage vs anonymous visits you cannot.
                  </p>
                </div>
                <div className="text-sm text-[var(--admin-muted)]">
                  <p>
                    Identified:{' '}
                    <span className="font-semibold text-[var(--admin-ink)]">
                      {formatNumber(ci.identified_served_month)}
                    </span>
                  </p>
                  <p>
                    Anonymous:{' '}
                    <span className="font-semibold text-[var(--admin-ink)]">
                      {formatNumber(ci.anonymous_served_month)}
                    </span>
                  </p>
                </div>
              </div>
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
                hint="Of identifiable customers this month"
              />
              <AnalyticsStatCard
                label="First-time %"
                value={formatPercentRate(ci.first_time_rate)}
                hint="Of identifiable customers this month"
              />
              <AnalyticsStatCard
                label="Unidentified gap"
                value={formatNumber(ci.unidentified_gap_count)}
                hint="Visits missing email/WhatsApp"
              />
            </div>
          </AnalyticsSectionCard>

          {/* 3. Repeat revenue opportunity */}
          <AnalyticsSectionCard title="3. Repeat revenue opportunity">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <AnalyticsStatCard
                label="Due soon"
                value={formatNumber(rr.clients_due_soon)}
                hint={`~${rr.typical_cycle_days}-day cycle`}
              />
              <AnalyticsStatCard label="Overdue" value={formatNumber(rr.clients_overdue)} />
              <AnalyticsStatCard
                label="Estimated opportunity"
                value={formatMoneyCents(rr.estimated_opportunity_cents)}
                hint={`Using ${formatMoneyCents(rr.avg_identified_spend_cents)} avg`}
              />
              <AnalyticsStatCard
                label="CRM joiners not visited"
                value={formatNumber(rr.crm_joiners_without_visit)}
              />
            </div>
          </AnalyticsSectionCard>

          {/* 4. Action center */}
          <AnalyticsSectionCard title="4. Action center">
            <ul className="divide-y divide-[var(--admin-line)] rounded-xl border border-[var(--admin-line)] bg-[var(--admin-surface)]">
              {data.action_center.map((task) => (
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
                    className={[
                      'inline-flex shrink-0 items-center justify-center rounded-md px-3 py-2 text-sm font-semibold',
                      task.count > 0
                        ? 'bg-[var(--admin-moss,#2f5a45)] text-white hover:opacity-90'
                        : 'border border-[var(--admin-line)] text-[var(--admin-muted)]',
                    ].join(' ')}
                  >
                    Open
                  </Link>
                </li>
              ))}
            </ul>
          </AnalyticsSectionCard>

          {/* 5. Business insights */}
          <AnalyticsSectionCard title="5. Business insights">
            {data.business_insights.length === 0 ? (
              <p className="text-sm text-[var(--admin-muted)]">
                No special insights right now — keep capturing contacts and watching due/overdue
                clients.
              </p>
            ) : (
              <ul className="space-y-3">
                {data.business_insights.map((insight) => (
                  <li
                    key={insight.code}
                    className={`rounded-xl border px-4 py-3 text-sm ${insightTone(insight.severity)}`}
                  >
                    <p>{insight.message}</p>
                    {insight.action_href ? (
                      <Link
                        href={insight.action_href}
                        className="mt-2 inline-block text-sm font-semibold underline-offset-2 hover:underline"
                      >
                        Take action →
                      </Link>
                    ) : null}
                  </li>
                ))}
              </ul>
            )}
          </AnalyticsSectionCard>
        </div>
      ) : null}
    </AdminAnalyticsShell>
  );
}
