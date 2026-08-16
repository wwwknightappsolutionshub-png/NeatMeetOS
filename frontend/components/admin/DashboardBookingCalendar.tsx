'use client';

import Link from 'next/link';
import { useCallback, useEffect, useMemo, useState } from 'react';
import type { Appointment } from '@/lib/booking-types';
import { fetchAppointments } from '@/services/booking.service';

type CalendarView = 'day' | 'week' | 'month';

function pad(n: number): string {
  return String(n).padStart(2, '0');
}

/** Local calendar date YYYY-MM-DD (not UTC). */
export function localIsoDate(d: Date): string {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function startOfWeek(d: Date): Date {
  const x = new Date(d.getFullYear(), d.getMonth(), d.getDate());
  const day = x.getDay(); // 0 Sun
  const diff = day === 0 ? -6 : 1 - day; // Monday start
  x.setDate(x.getDate() + diff);
  return x;
}

function addDays(d: Date, n: number): Date {
  const x = new Date(d.getFullYear(), d.getMonth(), d.getDate());
  x.setDate(x.getDate() + n);
  return x;
}

function startOfMonth(d: Date): Date {
  return new Date(d.getFullYear(), d.getMonth(), 1);
}

function daysInMonth(d: Date): number {
  return new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();
}

function formatTime(iso: string): string {
  return new Date(iso).toLocaleTimeString(undefined, {
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatDayLabel(d: Date): string {
  return d.toLocaleDateString(undefined, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  });
}

function formatMonthLabel(d: Date): string {
  return d.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
}

/** Color legend: green upcoming, red cancelled, blue pending/postponed-like. */
export function appointmentTone(status: string): {
  bg: string;
  border: string;
  text: string;
  label: string;
} {
  switch (status) {
    case 'cancelled':
    case 'no_show':
      return {
        bg: 'bg-red-50',
        border: 'border-red-300',
        text: 'text-red-800',
        label: status === 'no_show' ? 'No-show' : 'Cancelled',
      };
    case 'pending':
      return {
        bg: 'bg-sky-50',
        border: 'border-sky-300',
        text: 'text-sky-900',
        label: 'Pending',
      };
    case 'completed':
      return {
        bg: 'bg-zinc-100',
        border: 'border-zinc-300',
        text: 'text-zinc-700',
        label: 'Completed',
      };
    case 'checked_in':
      return {
        bg: 'bg-emerald-100',
        border: 'border-emerald-400',
        text: 'text-emerald-900',
        label: 'Checked in',
      };
    case 'confirmed':
    default:
      return {
        bg: 'bg-emerald-50',
        border: 'border-emerald-300',
        text: 'text-emerald-900',
        label: status === 'confirmed' ? 'Upcoming' : status.replace('_', ' '),
      };
  }
}

function rangeForView(anchor: Date, view: CalendarView): { from: string; to: string } {
  // Backend compares timestamps in UTC. Convert local day/week/month bounds to ISO
  // with offset so evening local appointments are not dropped from the query window.
  const bounds = (startLocal: Date, endLocal: Date) => ({
    from: startLocal.toISOString(),
    to: endLocal.toISOString(),
  });

  if (view === 'day') {
    const start = new Date(anchor.getFullYear(), anchor.getMonth(), anchor.getDate(), 0, 0, 0, 0);
    const end = new Date(anchor.getFullYear(), anchor.getMonth(), anchor.getDate(), 23, 59, 59, 999);
    return bounds(start, end);
  }
  if (view === 'week') {
    const weekStart = startOfWeek(anchor);
    const weekEnd = addDays(weekStart, 6);
    const start = new Date(
      weekStart.getFullYear(),
      weekStart.getMonth(),
      weekStart.getDate(),
      0,
      0,
      0,
      0,
    );
    const end = new Date(
      weekEnd.getFullYear(),
      weekEnd.getMonth(),
      weekEnd.getDate(),
      23,
      59,
      59,
      999,
    );
    return bounds(start, end);
  }
  const first = startOfMonth(anchor);
  const last = new Date(anchor.getFullYear(), anchor.getMonth(), daysInMonth(anchor));
  const start = new Date(first.getFullYear(), first.getMonth(), first.getDate(), 0, 0, 0, 0);
  const end = new Date(last.getFullYear(), last.getMonth(), last.getDate(), 23, 59, 59, 999);
  return bounds(start, end);
}

export function DashboardBookingCalendar({
  refreshToken = 0,
  focusDateIso,
}: {
  refreshToken?: number;
  /** YYYY-MM-DD — jump day view here when a new booking lands. */
  focusDateIso?: string | null;
}) {
  const [view, setView] = useState<CalendarView>('day');
  const [anchor, setAnchor] = useState(() => new Date());
  const [items, setItems] = useState<Appointment[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [nextHint, setNextHint] = useState<{ date: string; label: string } | null>(null);

  const range = useMemo(() => rangeForView(anchor, view), [anchor, view]);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const rows = await fetchAppointments({ from: range.from, to: range.to });
      setItems(
        [...rows].sort((a, b) => a.starts_at.localeCompare(b.starts_at)),
      );

      // If day view is empty, find the next upcoming booking so staff can jump to it.
      if (view === 'day' && rows.length === 0) {
        const from = new Date(anchor.getFullYear(), anchor.getMonth(), anchor.getDate(), 0, 0, 0, 0);
        const to = addDays(from, 14);
        to.setHours(23, 59, 59, 999);
        const upcoming = await fetchAppointments({
          from: from.toISOString(),
          to: to.toISOString(),
        });
        const next = [...upcoming]
          .filter((a) => !['cancelled', 'no_show'].includes(a.status))
          .sort((a, b) => a.starts_at.localeCompare(b.starts_at))[0];
        if (next) {
          const d = new Date(next.starts_at);
          setNextHint({
            date: localIsoDate(d),
            label: `${formatDayLabel(d)} · ${formatTime(next.starts_at)}`,
          });
        } else {
          setNextHint(null);
        }
      } else {
        setNextHint(null);
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not load calendar');
      setItems([]);
      setNextHint(null);
    } finally {
      setLoading(false);
    }
  }, [range.from, range.to, view, anchor]);

  useEffect(() => {
    void load();
  }, [load, refreshToken]);

  useEffect(() => {
    if (!focusDateIso) return;
    const [y, m, d] = focusDateIso.split('-').map(Number);
    if (!y || !m || !d) return;
    setView('day');
    setAnchor(new Date(y, m - 1, d));
  }, [focusDateIso]);

  // Keep the board live while staff stay on the dashboard.
  useEffect(() => {
    const id = window.setInterval(() => void load(), 20_000);
    return () => window.clearInterval(id);
  }, [load]);

  function shift(delta: number) {
    setAnchor((prev) => {
      if (view === 'day') return addDays(prev, delta);
      if (view === 'week') return addDays(prev, delta * 7);
      return new Date(prev.getFullYear(), prev.getMonth() + delta, 1);
    });
  }

  function jumpToHint() {
    if (!nextHint) return;
    const [y, m, d] = nextHint.date.split('-').map(Number);
    setView('day');
    setAnchor(new Date(y, m - 1, d));
  }

  const byDay = useMemo(() => {
    const map = new Map<string, Appointment[]>();
    for (const appt of items) {
      const key = localIsoDate(new Date(appt.starts_at));
      const list = map.get(key) ?? [];
      list.push(appt);
      map.set(key, list);
    }
    return map;
  }, [items]);

  const weekDays = useMemo(() => {
    const start = startOfWeek(anchor);
    return Array.from({ length: 7 }, (_, i) => addDays(start, i));
  }, [anchor]);

  const monthCells = useMemo(() => {
    const first = startOfMonth(anchor);
    const start = startOfWeek(first);
    return Array.from({ length: 42 }, (_, i) => addDays(start, i));
  }, [anchor]);

  const title =
    view === 'month'
      ? formatMonthLabel(anchor)
      : view === 'week'
        ? `${formatDayLabel(weekDays[0])} – ${formatDayLabel(weekDays[6])}`
        : formatDayLabel(anchor);

  return (
    <section className="rounded-2xl border border-[var(--admin-line)] bg-white p-4 shadow-sm sm:p-5">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-[var(--admin-muted)]">
            Bookings calendar
          </p>
          <h2 className="mt-1 text-lg font-semibold tracking-tight text-[var(--admin-ink)]">
            {title}
          </h2>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {(['day', 'week', 'month'] as const).map((v) => (
            <button
              key={v}
              type="button"
              onClick={() => setView(v)}
              className={[
                'rounded-lg px-3 py-1.5 text-sm font-semibold capitalize',
                view === v
                  ? 'bg-[var(--admin-accent)] text-white'
                  : 'border border-[var(--admin-line)] bg-white text-[var(--admin-ink)] hover:bg-[var(--admin-wash)]',
              ].join(' ')}
            >
              {v}
            </button>
          ))}
        </div>
      </div>

      <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => shift(-1)}
            className="rounded-lg border border-[var(--admin-line)] px-2.5 py-1.5 text-sm font-medium hover:bg-[var(--admin-wash)]"
          >
            ←
          </button>
          <button
            type="button"
            onClick={() => setAnchor(new Date())}
            className="rounded-lg border border-[var(--admin-line)] px-2.5 py-1.5 text-sm font-medium hover:bg-[var(--admin-wash)]"
          >
            Today
          </button>
          <button
            type="button"
            onClick={() => shift(1)}
            className="rounded-lg border border-[var(--admin-line)] px-2.5 py-1.5 text-sm font-medium hover:bg-[var(--admin-wash)]"
          >
            →
          </button>
        </div>
        <div className="flex flex-wrap gap-2 text-[11px] font-medium">
          <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-800">
            <span className="h-2 w-2 rounded-full bg-emerald-500" /> Upcoming
          </span>
          <span className="inline-flex items-center gap-1.5 rounded-full bg-sky-50 px-2 py-0.5 text-sky-900">
            <span className="h-2 w-2 rounded-full bg-sky-500" /> Pending
          </span>
          <span className="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2 py-0.5 text-red-800">
            <span className="h-2 w-2 rounded-full bg-red-500" /> Cancelled
          </span>
        </div>
      </div>

      {error ? (
        <p className="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
          {error}
        </p>
      ) : null}

      {nextHint ? (
        <button
          type="button"
          onClick={jumpToHint}
          className="mt-4 w-full rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2.5 text-left text-sm text-emerald-950 hover:bg-emerald-100"
        >
          <span className="font-semibold">Next booking:</span> {nextHint.label}
          <span className="ml-2 text-emerald-800 underline">Open day →</span>
        </button>
      ) : null}

      {loading ? (
        <p className="mt-6 text-sm text-zinc-500">Loading calendar…</p>
      ) : view === 'day' ? (
        <DayList date={anchor} appointments={byDay.get(localIsoDate(anchor)) ?? []} />
      ) : view === 'week' ? (
        <div className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-7">
          {weekDays.map((day) => {
            const key = localIsoDate(day);
            const list = byDay.get(key) ?? [];
            return (
              <button
                key={key}
                type="button"
                onClick={() => {
                  setAnchor(day);
                  setView('day');
                }}
                className="min-h-[7.5rem] rounded-xl border border-zinc-200 bg-zinc-50/60 p-2 text-left hover:border-[var(--admin-accent)]/40"
              >
                <p className="text-xs font-semibold text-zinc-700">{formatDayLabel(day)}</p>
                <ul className="mt-2 space-y-1">
                  {list.length === 0 ? (
                    <li className="text-[11px] text-zinc-400">—</li>
                  ) : (
                    list.slice(0, 4).map((a) => {
                      const tone = appointmentTone(a.status);
                      return (
                        <li
                          key={a.id}
                          className={`truncate rounded border px-1.5 py-0.5 text-[10px] font-medium ${tone.bg} ${tone.border} ${tone.text}`}
                        >
                          {formatTime(a.starts_at)}{' '}
                          {a.client?.resolved_display_name ?? 'Client'}
                        </li>
                      );
                    })
                  )}
                  {list.length > 4 ? (
                    <li className="text-[10px] text-zinc-500">+{list.length - 4} more</li>
                  ) : null}
                </ul>
              </button>
            );
          })}
        </div>
      ) : (
        <div className="mt-4">
          <div className="mb-1 grid grid-cols-7 gap-1 text-center text-[10px] font-semibold uppercase tracking-wide text-zinc-500">
            {['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((d) => (
              <div key={d}>{d}</div>
            ))}
          </div>
          <div className="grid grid-cols-7 gap-1">
            {monthCells.map((day) => {
              const key = localIsoDate(day);
              const inMonth = day.getMonth() === anchor.getMonth();
              const list = byDay.get(key) ?? [];
              const isToday = key === localIsoDate(new Date());
              return (
                <button
                  key={key}
                  type="button"
                  onClick={() => {
                    setAnchor(day);
                    setView('day');
                  }}
                  className={[
                    'min-h-[4.25rem] rounded-lg border p-1 text-left sm:min-h-[5.5rem]',
                    inMonth ? 'border-zinc-200 bg-white' : 'border-transparent bg-zinc-50/50',
                    isToday ? 'ring-2 ring-[var(--admin-accent)]/40' : '',
                  ].join(' ')}
                >
                  <p
                    className={[
                      'text-[11px] font-semibold',
                      inMonth ? 'text-zinc-800' : 'text-zinc-400',
                    ].join(' ')}
                  >
                    {day.getDate()}
                  </p>
                  <div className="mt-1 flex flex-wrap gap-0.5">
                    {list.slice(0, 3).map((a) => {
                      const dot =
                        a.status === 'cancelled' || a.status === 'no_show'
                          ? 'bg-red-500'
                          : a.status === 'pending'
                            ? 'bg-sky-500'
                            : 'bg-emerald-500';
                      return (
                        <span
                          key={a.id}
                          title={`${formatTime(a.starts_at)} ${a.client?.resolved_display_name ?? ''}`}
                          className={`h-1.5 w-1.5 rounded-full ${dot}`}
                        />
                      );
                    })}
                    {list.length > 3 ? (
                      <span className="text-[9px] text-zinc-500">+{list.length - 3}</span>
                    ) : null}
                  </div>
                </button>
              );
            })}
          </div>
        </div>
      )}

      <div className="mt-4 flex justify-end">
        <Link
          href="/admin/bookings"
          className="text-sm font-medium text-[var(--admin-accent)] underline-offset-2 hover:underline"
        >
          Open day board
        </Link>
      </div>
    </section>
  );
}

function DayList({
  date,
  appointments,
}: {
  date: Date;
  appointments: Appointment[];
}) {
  if (appointments.length === 0) {
    return (
      <p className="mt-6 rounded-xl border border-dashed border-zinc-300 bg-zinc-50/80 px-4 py-8 text-center text-sm text-zinc-500">
        No bookings on {formatDayLabel(date)}.
      </p>
    );
  }

  return (
    <ul className="mt-4 space-y-2">
      {appointments.map((appt) => {
        const tone = appointmentTone(appt.status);
        return (
          <li key={appt.id}>
            <Link
              href={`/admin/bookings/${appt.id}`}
              className={`flex items-start justify-between gap-3 rounded-xl border px-3 py-2.5 transition hover:brightness-[0.99] ${tone.bg} ${tone.border}`}
            >
              <div className="min-w-0">
                <p className={`truncate text-sm font-semibold ${tone.text}`}>
                  {appt.client?.resolved_display_name ?? 'Client'}
                </p>
                <p className="truncate text-xs text-zinc-600">
                  {formatTime(appt.starts_at)}–{formatTime(appt.ends_at)}
                  {appt.team_member?.display_name
                    ? ` · ${appt.team_member.display_name}`
                    : ''}
                  {appt.services?.[0]?.service_name
                    ? ` · ${appt.services[0].service_name}`
                    : ''}
                </p>
              </div>
              <span
                className={`shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${tone.text} bg-white/70`}
              >
                {tone.label}
              </span>
            </Link>
          </li>
        );
      })}
    </ul>
  );
}
