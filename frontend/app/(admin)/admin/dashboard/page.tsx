'use client';

import Link from 'next/link';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { DashboardBookingCalendar } from '@/components/admin/DashboardBookingCalendar';
import { DashboardTrendChart } from '@/components/admin/DashboardTrendChart';
import { AnalyticsSectionCard } from '@/components/admin/analytics/AnalyticsSectionCard';
import { AnalyticsStatCard } from '@/components/admin/analytics/AnalyticsStatCard';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import { getStoredToken, isUpgradeRequiredError } from '@/lib/api-client';
import {
  formatMoneyCents,
  formatNumber,
  formatRangeLabel,
  type AnalyticsOverview,
  type BookingAnalytics,
} from '@/lib/analytics-types';
import type { Appointment, BookingDayBoard, WaitlistEntry } from '@/lib/booking-types';
import type { ShellStatus } from '@/lib/types';
import { fetchAnalyticsOverview, fetchBookingAnalytics } from '@/services/analytics.service';
import { fetchShell } from '@/services/auth.service';
import { fetchBookingDayBoard, fetchWaitlist } from '@/services/booking.service';

function isoDate(d: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function lastNDaysRange(days: number): { from: string; to: string } {
  const to = new Date();
  const from = new Date();
  from.setDate(from.getDate() - (days - 1));
  return { from: isoDate(from), to: isoDate(to) };
}

function formatTime(iso: string): string {
  return new Date(iso).toLocaleTimeString(undefined, {
    hour: '2-digit',
    minute: '2-digit',
  });
}

type Urgency = 'live' | 'imminent' | 'soon' | 'later' | 'done';

function appointmentUrgency(appointment: Appointment, nowMs: number): Urgency {
  const start = new Date(appointment.starts_at).getTime();
  const end = new Date(appointment.ends_at).getTime();
  if (appointment.status === 'completed') return 'done';
  if (appointment.status === 'checked_in' || (nowMs >= start && nowMs < end)) return 'live';
  if (nowMs >= end) return 'done';
  const mins = (start - nowMs) / 60_000;
  if (mins <= 5) return 'imminent';
  if (mins <= 30) return 'soon';
  return 'later';
}

function urgencyStyles(urgency: Urgency): {
  card: string;
  badge: string;
  label: string;
} {
  switch (urgency) {
    case 'live':
      return {
        card: 'border-rose-300 bg-rose-50 ring-1 ring-rose-200',
        badge: 'bg-rose-600 text-white',
        label: 'In progress',
      };
    case 'imminent':
      return {
        card: 'border-orange-300 bg-orange-50 ring-1 ring-orange-200',
        badge: 'bg-orange-600 text-white',
        label: 'Starting soon',
      };
    case 'soon':
      return {
        card: 'border-amber-300 bg-amber-50',
        badge: 'bg-amber-500 text-white',
        label: 'Up next',
      };
    case 'done':
      return {
        card: 'border-zinc-200 bg-zinc-50',
        badge: 'bg-zinc-200 text-zinc-700',
        label: 'Done',
      };
    default:
      return {
        card: 'border-zinc-200 bg-white',
        badge: 'bg-emerald-100 text-emerald-900',
        label: 'Scheduled',
      };
  }
}

function formatCountdown(appointment: Appointment, nowMs: number): string {
  const start = new Date(appointment.starts_at).getTime();
  const end = new Date(appointment.ends_at).getTime();

  if (appointment.status === 'checked_in' || (nowMs >= start && nowMs < end)) {
    const left = Math.max(0, end - nowMs);
    return `Ends in ${formatDuration(left)}`;
  }

  if (nowMs >= end || appointment.status === 'completed') {
    return 'Finished';
  }

  const until = start - nowMs;
  if (until <= 0) return 'Starting now';
  return `Starts in ${formatDuration(until)}`;
}

function formatDuration(ms: number): string {
  const totalSec = Math.max(0, Math.round(ms / 1000));
  const hours = Math.floor(totalSec / 3600);
  const minutes = Math.floor((totalSec % 3600) / 60);
  const seconds = totalSec % 60;
  if (hours > 0) return `${hours}h ${minutes}m`;
  if (minutes > 0) return `${minutes}m ${seconds.toString().padStart(2, '0')}s`;
  return `${seconds}s`;
}

function settledValue<T>(result: PromiseSettledResult<T>): T | null {
  return result.status === 'fulfilled' ? result.value : null;
}

function settledError(result: PromiseSettledResult<unknown>): string | null {
  if (result.status !== 'rejected') return null;
  const reason = result.reason;
  if (isUpgradeRequiredError(reason)) return null;
  return reason instanceof Error ? reason.message : 'Request failed';
}

interface AttentionItem {
  label: string;
  value: string;
  href: string;
  tone: 'amber' | 'red' | 'zinc';
}

export default function AdminDashboardPage() {
  const [shell, setShell] = useState<ShellStatus | null>(null);
  const [overview, setOverview] = useState<AnalyticsOverview | null>(null);
  const [bookingsAnalytics, setBookingsAnalytics] = useState<BookingAnalytics | null>(null);
  const [board, setBoard] = useState<BookingDayBoard | null>(null);
  const [waitlist, setWaitlist] = useState<WaitlistEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [authError, setAuthError] = useState<string | null>(null);
  const [partialErrors, setPartialErrors] = useState<string[]>([]);
  const [refreshedAt, setRefreshedAt] = useState<Date | null>(null);
  const [nowMs, setNowMs] = useState(() => Date.now());

  const range = useMemo(() => lastNDaysRange(7), []);
  const today = range.to;

  useEffect(() => {
    const id = window.setInterval(() => setNowMs(Date.now()), 1000);
    return () => window.clearInterval(id);
  }, []);

  const load = useCallback(async () => {
    if (!getStoredToken()) {
      window.location.href = '/login';
      return;
    }

    setLoading(true);
    setAuthError(null);
    setPartialErrors([]);

    let shellData: ShellStatus;
    try {
      shellData = await fetchShell();
      setShell(shellData);
    } catch (e) {
      setAuthError(e instanceof Error ? e.message : 'Unauthorized');
      setLoading(false);
      return;
    }

    const analyticsOn = Boolean(shellData.features?.analytics);
    if (!analyticsOn) {
      setOverview(null);
      setBookingsAnalytics(null);
    }

    const results = await Promise.allSettled([
      analyticsOn
        ? fetchAnalyticsOverview({ from: range.from, to: range.to })
        : Promise.resolve(null),
      analyticsOn
        ? fetchBookingAnalytics({ from: range.from, to: range.to })
        : Promise.resolve(null),
      fetchBookingDayBoard({ date: today }),
      fetchWaitlist({ status: 'waiting' }),
    ]);

    const [overviewResult, bookingsResult, boardResult, waitlistResult] = results;
    setOverview(settledValue(overviewResult));
    setBookingsAnalytics(settledValue(bookingsResult));
    setBoard(settledValue(boardResult));
    setWaitlist(settledValue(waitlistResult) ?? []);

    const errors = [
      settledError(overviewResult),
      settledError(bookingsResult),
      settledError(boardResult),
      settledError(waitlistResult),
    ].filter((msg): msg is string => Boolean(msg));

    setPartialErrors(errors);
    setRefreshedAt(new Date());
    setLoading(false);
  }, [range.from, range.to, today]);

  useEffect(() => {
    void load();
  }, [load]);

  const bookHref = `/book/${shell?.tenant?.slug ?? 'demo-salon'}`;

  const todaysAppointments = useMemo(() => {
    if (!board?.appointments) return [];
    return [...board.appointments]
      .filter((a) => !['cancelled', 'no_show'].includes(a.status))
      .sort((a, b) => a.starts_at.localeCompare(b.starts_at));
  }, [board]);

  const focusAppointment = useMemo(() => {
    const active = todaysAppointments.find((a) => {
      const urgency = appointmentUrgency(a, nowMs);
      return urgency === 'live' || urgency === 'imminent' || urgency === 'soon';
    });
    if (active) return active;
    return (
      todaysAppointments.find((a) => {
        const start = new Date(a.starts_at).getTime();
        return start >= nowMs && a.status !== 'completed';
      }) ?? null
    );
  }, [todaysAppointments, nowMs]);

  const attention = useMemo((): AttentionItem[] => {
    const items: AttentionItem[] = [];
    const walkIns = board?.summary.walk_ins_waiting ?? 0;
    if (walkIns > 0) {
      items.push({
        label: 'Walk-ins waiting',
        value: formatNumber(walkIns),
        href: '/admin/bookings/walk-ins',
        tone: 'amber',
      });
    }
    if (waitlist.length > 0) {
      items.push({
        label: 'Open waitlist',
        value: formatNumber(waitlist.length),
        href: '/admin/bookings/waitlist',
        tone: 'amber',
      });
    }
    if (overview) {
      if (overview.inventory.low_stock_items_count > 0) {
        items.push({
          label: 'Low stock items',
          value: formatNumber(overview.inventory.low_stock_items_count),
          href: '/admin/inventory/low-stock',
          tone: 'red',
        });
      }
      if (overview.payments.failed_payments_count > 0) {
        items.push({
          label: 'Failed payments',
          value: formatNumber(overview.payments.failed_payments_count),
          href: '/admin/payments/failed',
          tone: 'red',
        });
      }
      if (overview.notifications.messages_failed_count > 0) {
        items.push({
          label: 'Failed notifications',
          value: formatNumber(overview.notifications.messages_failed_count),
          href: '/admin/notifications/messages',
          tone: 'red',
        });
      }
      if (overview.bookings.no_show_appointments > 0) {
        items.push({
          label: 'No-shows (period)',
          value: formatNumber(overview.bookings.no_show_appointments),
          href: '/admin/analytics/bookings',
          tone: 'zinc',
        });
      }
    }
    return items;
  }, [board, overview, waitlist.length]);

  const todayLabel = new Date(`${today}T12:00:00`).toLocaleDateString(undefined, {
    weekday: 'long',
    month: 'short',
    day: 'numeric',
  });

  if (authError) {
    return (
      <div className="grid gap-4">
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-[var(--admin-muted)]">
            Operations
          </p>
          <h1 className="mt-1 text-2xl font-semibold tracking-tight">Dashboard</h1>
        </div>
        <ErrorAlert message={`${authError}. Sign in again to continue.`} />
        <Link href="/login" className="text-sm text-[var(--admin-accent)] underline">
          Sign in
        </Link>
      </div>
    );
  }

  return (
    <div className="grid gap-5">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-[var(--admin-muted)]">
            Operations
          </p>
          <h1 className="mt-1 text-2xl font-semibold tracking-tight">Dashboard</h1>
          <p className="mt-1 text-sm text-[var(--admin-muted)]">
            {shell ? (
              <>
                {shell.tenant?.name ?? 'Tenant'} · {todayLabel}
                {shell.user?.name ? ` · ${shell.user.name}` : ''}
              </>
            ) : (
              'Loading workspace…'
            )}
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Link
            href="/admin/bookings"
            className="rounded-lg bg-[var(--admin-accent)] px-3 py-1.5 text-sm font-semibold text-white hover:brightness-110"
          >
            Day board
          </Link>
          <Link
            href="/admin/pos"
            className="rounded-lg border border-[var(--admin-line)] bg-white px-3 py-1.5 text-sm font-medium text-[var(--admin-ink)] hover:bg-[var(--admin-wash)]"
          >
            Open POS
          </Link>
          {shell?.features?.next_visit ? (
            <Link
              href="/admin/next-visit"
              className="rounded-lg border border-[var(--admin-line)] bg-white px-3 py-1.5 text-sm font-medium text-[var(--admin-ink)] hover:bg-[var(--admin-wash)]"
            >
              Next visit
            </Link>
          ) : null}
          <Link
            href={bookHref}
            target="_blank"
            rel="noreferrer"
            className="rounded-lg border border-[var(--admin-line)] bg-white px-3 py-1.5 text-sm font-medium text-[var(--admin-ink)] hover:bg-[var(--admin-wash)]"
          >
            Booking portal
          </Link>
          <button
            type="button"
            onClick={() => void load()}
            className="rounded-lg border border-[var(--admin-line)] bg-white px-3 py-1.5 text-sm font-medium text-[var(--admin-ink)] hover:bg-[var(--admin-wash)]"
          >
            Refresh
          </button>
        </div>
      </div>

      {partialErrors.length > 0 ? (
        <ErrorAlert
          message={`Some dashboard panels could not load: ${partialErrors.slice(0, 2).join(' · ')}`}
        />
      ) : null}

      {loading && !overview && !board ? (
        <LoadingState label="Loading operations…" />
      ) : (
        <>
          {focusAppointment ? (
            <UpNextBanner appointment={focusAppointment} nowMs={nowMs} />
          ) : null}

          <DashboardBookingCalendar />

          {attention.length > 0 ? (
            <AnalyticsSectionCard title="Needs attention">
              <ul className="space-y-2">
                {attention.map((item) => (
                  <li key={item.label}>
                    <Link
                      href={item.href}
                      className="flex items-center justify-between rounded-lg border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50"
                    >
                      <span className="text-zinc-700">{item.label}</span>
                      <span
                        className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                          item.tone === 'red'
                            ? 'bg-red-100 text-red-800'
                            : item.tone === 'amber'
                              ? 'bg-amber-100 text-amber-900'
                              : 'bg-zinc-100 text-zinc-700'
                        }`}
                      >
                        {item.value}
                      </span>
                    </Link>
                  </li>
                ))}
              </ul>
            </AnalyticsSectionCard>
          ) : null}

          {overview ? (
            <>
              <p className="text-xs text-zinc-500">
                Performance window: {formatRangeLabel(overview.range)}
                {refreshedAt
                  ? ` · Updated ${refreshedAt.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })}`
                  : ''}
              </p>
              <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <AnalyticsStatCard
                  label="Appointments"
                  value={formatNumber(overview.bookings.total_appointments)}
                  hint={`${formatNumber(overview.bookings.completed_appointments)} completed · ${formatNumber(overview.bookings.cancelled_appointments)} cancelled`}
                />
                <AnalyticsStatCard
                  label="Collected"
                  value={formatMoneyCents(overview.payments.total_payment_collected_cents)}
                  hint={`${formatMoneyCents(overview.pos.gross_sales_cents)} POS sales`}
                />
                <AnalyticsStatCard
                  label="New clients"
                  value={formatNumber(overview.clients.new_clients_in_period)}
                  hint={`${formatNumber(overview.clients.total_clients)} total clients`}
                />
              </div>
            </>
          ) : null}

          <div className="grid gap-4 lg:grid-cols-5">
            <div className="lg:col-span-3">
              <DashboardTrendChart
                daily={bookingsAnalytics?.daily ?? []}
                title="Booking trend"
              />
            </div>
            <div className="lg:col-span-2">
              {overview ? (
                <AnalyticsSectionCard title="Pulse" href="/admin/analytics">
                  <dl className="grid gap-2 text-sm">
                    <PulseRow
                      label="No-shows"
                      value={formatNumber(overview.bookings.no_show_appointments)}
                    />
                    <PulseRow
                      label="Walk-ins (period)"
                      value={formatNumber(overview.bookings.walk_in_appointments)}
                    />
                    <PulseRow
                      label="Active memberships"
                      value={formatNumber(overview.memberships.active_memberships)}
                    />
                    <PulseRow
                      label="Notifications sent"
                      value={formatNumber(overview.notifications.messages_sent_count)}
                    />
                    <PulseRow
                      label="Marketing sent"
                      value={formatNumber(overview.marketing.messages_sent_count)}
                    />
                  </dl>
                </AnalyticsSectionCard>
              ) : (
                <AnalyticsSectionCard title="Needs attention">
                  {attention.length === 0 ? (
                    <p className="text-sm text-zinc-500">Nothing urgent right now.</p>
                  ) : (
                    <p className="text-sm text-zinc-500">See attention list above.</p>
                  )}
                </AnalyticsSectionCard>
              )}
            </div>
          </div>

          {board && board.workspace_occupancy.length > 0 ? (
            <AnalyticsSectionCard title="Workspace load today">
              <ul className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                {board.workspace_occupancy.map((ws) => (
                  <li
                    key={ws.workspace_id}
                    className="flex items-center justify-between rounded-lg border border-zinc-200 px-3 py-2 text-sm"
                  >
                    <span className="truncate text-zinc-700">
                      {ws.workspace_name}
                      <span className="ml-1 text-xs text-zinc-400">({ws.workspace_type})</span>
                    </span>
                    <span className="font-semibold text-zinc-900">{ws.appointments}</span>
                  </li>
                ))}
              </ul>
            </AnalyticsSectionCard>
          ) : null}
        </>
      )}
    </div>
  );
}

function UpNextBanner({
  appointment,
  nowMs,
}: {
  appointment: Appointment;
  nowMs: number;
}) {
  const urgency = appointmentUrgency(appointment, nowMs);
  const styles = urgencyStyles(urgency);

  return (
    <Link
      href={`/admin/bookings/${appointment.id}`}
      className={`block rounded-2xl border px-4 py-4 shadow-sm transition hover:brightness-[0.99] ${styles.card}`}
    >
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-zinc-600">
            {urgency === 'live' ? 'Happening now' : 'Up next'}
          </p>
          <p className="mt-1 truncate text-lg font-semibold tracking-tight text-zinc-900">
            {appointment.client?.resolved_display_name ?? 'Client'}
          </p>
          <p className="mt-0.5 truncate text-sm text-zinc-600">
            {formatTime(appointment.starts_at)}–{formatTime(appointment.ends_at)}
            {appointment.team_member?.display_name
              ? ` · ${appointment.team_member.display_name}`
              : ''}
            {appointment.services?.[0]?.service_name
              ? ` · ${appointment.services[0].service_name}`
              : ''}
          </p>
        </div>
        <div className="shrink-0 text-right">
          <span
            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${styles.badge} ${
              urgency === 'live' || urgency === 'imminent' ? 'animate-pulse' : ''
            }`}
          >
            {styles.label}
          </span>
          <p className="mt-2 text-sm font-bold tabular-nums text-zinc-900">
            {formatCountdown(appointment, nowMs)}
          </p>
        </div>
      </div>
    </Link>
  );
}

function PulseRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between border-b border-zinc-100 pb-2 last:border-0 last:pb-0">
      <dt className="text-zinc-500">{label}</dt>
      <dd className="font-medium text-zinc-900">{value}</dd>
    </div>
  );
}
