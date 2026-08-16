'use client';

import Link from 'next/link';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { Appointment } from '@/lib/booking-types';
import { subscribeBookingBoard } from '@/lib/echo';
import { fetchAppointments } from '@/services/booking.service';
import { fetchShell } from '@/services/auth.service';

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
  focusDateIso = null,
}: {
  refreshToken?: number;
  focusDateIso?: string | null;
}) {
  const [view, setView] = useState<CalendarView>('day');
  const [anchor, setAnchor] = useState(() => new Date());
  const [items, setItems] = useState<Appointment[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const requestIdRef = useRef(0);

  const range = useMemo(() => rangeForView(anchor, view), [anchor, view]);
  const anchorDateKey = localIsoDate(anchor);

  const load = useCallback(async (opts?: { silent?: boolean }) => {
    const silent = Boolean(opts?.silent);
    const requestId = ++requestIdRef.current;
    if (!silent) {
      setLoading(true);
    }
    setError(null);
    try {
      const rows = await fetchAppointments({ from: range.from, to: range.to });
      if (requestId !== requestIdRef.current) return;
      setItems([...rows].sort(compareAppointmentStart));
    } catch (e) {
      if (requestId !== requestIdRef.current) return;
      setError(e instanceof Error ? e.message : 'Could not load calendar');
      if (!silent) {
        setItems([]);
      }
    } finally {
      if (requestId === requestIdRef.current) {
        setLoading(false);
      }
    }
  }, [range.from, range.to]);

  // Visible load when the calendar range/view changes.
  useEffect(() => {
    void load({ silent: false });
  }, [load]);

  // Background refresh from parent SOS / booking updates — keep current UI.
  useEffect(() => {
    if (refreshToken === 0) return;
    void load({ silent: true });
    // Intentionally only react to token bumps, not load identity (range already handled above).
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [refreshToken]);

  useEffect(() => {
    if (!focusDateIso) return;
    const parsed = new Date(`${focusDateIso}T12:00:00`);
    if (Number.isNaN(parsed.getTime())) return;
    setAnchor((prev) => {
      const nextKey = localIsoDate(parsed);
      return localIsoDate(prev) === nextKey ? prev : parsed;
    });
    setView('day');
  }, [focusDateIso]);

  // Live refresh when an online/admin booking lands on the visible day/week/month.
  useEffect(() => {
    let unsubscribe: (() => void) | null = null;
    let cancelled = false;

    fetchShell()
      .then((shell) => {
        if (cancelled || !shell.tenant?.id) return;
        unsubscribe = subscribeBookingBoard(shell.tenant.id, (payload) => {
          const fromDay = localIsoDate(new Date(range.from));
          const toDay = localIsoDate(new Date(range.to));
          const overlapsVisible =
            payload.date === anchorDateKey ||
            (payload.date >= fromDay && payload.date <= toDay);
          if (overlapsVisible) {
            void load({ silent: true });
          }
        });
      })
      .catch(() => {
        /* Echo optional */
      });

    return () => {
      cancelled = true;
      unsubscribe?.();
    };
  }, [load, range.from, range.to, anchorDateKey]);

  // Poll as a fallback when Reverb is unavailable.
  useEffect(() => {
    const id = window.setInterval(() => {
      void load({ silent: true });
    }, 60_000);
    return () => window.clearInterval(id);
  }, [load]);

  function shift(delta: number) {
    setAnchor((prev) => {
      if (view === 'day') return addDays(prev, delta);
      if (view === 'week') return addDays(prev, delta * 7);
      return new Date(prev.getFullYear(), prev.getMonth() + delta, 1);
    });
  }

  const byDay = useMemo(() => {
    const map = new Map<string, Appointment[]>();
    for (const appt of items) {
      const key = localIsoDate(new Date(appt.starts_at));
      const list = map.get(key) ?? [];
      list.push(appt);
      map.set(key, list);
    }
    for (const [key, list] of map) {
      map.set(key, [...list].sort(compareAppointmentStart));
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
                'rounded-lg px-3 py-1.5 text-sm font-semibold',
                view === v
                  ? 'bg-[var(--admin-accent)] text-white'
                  : 'border border-[var(--admin-line)] bg-white text-[var(--admin-ink)] hover:bg-[var(--admin-wash)]',
              ].join(' ')}
            >
              {v === 'day' ? 'Today' : v === 'week' ? 'Week' : 'Month'}
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

function compareAppointmentStart(a: Appointment, b: Appointment): number {
  const aStart = new Date(a.starts_at).getTime();
  const bStart = new Date(b.starts_at).getTime();
  if (aStart !== bStart) return aStart - bStart;
  const aEnd = new Date(a.ends_at).getTime();
  const bEnd = new Date(b.ends_at).getTime();
  if (aEnd !== bEnd) return aEnd - bEnd;
  return a.id.localeCompare(b.id);
}

function DayList({
  date,
  appointments,
}: {
  date: Date;
  appointments: Appointment[];
}) {
  const ordered = [...appointments].sort(compareAppointmentStart);

  if (ordered.length === 0) {
    return (
      <p className="mt-6 rounded-xl border border-dashed border-zinc-300 bg-zinc-50/80 px-4 py-8 text-center text-sm text-zinc-500">
        No bookings on {formatDayLabel(date)}.
      </p>
    );
  }

  return (
    <ul className="mt-4 space-y-2">
      {ordered.map((appt) => {
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
