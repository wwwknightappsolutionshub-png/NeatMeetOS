'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { EmptyState, Field, inputClass } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { Location, Workspace } from '@/lib/identity-types';
import type { StaffAvailabilityRule } from '@/lib/staff-types';
import { DAYS_OF_WEEK } from '@/lib/staff-types';
import {
  archiveStaffAvailability,
  createStaffAvailability,
  fetchStaffAvailability,
} from '@/services/staff.service';

interface AvailabilityTabProps {
  teamMemberId: string;
  locations: Location[];
  workspaces: Workspace[];
}

const SLOT_INTERVALS = [15, 30, 60] as const;
const DAY_START = '06:00';
const DAY_END = '22:00';

function timeToMinutes(time: string): number {
  const [h, m] = time.slice(0, 5).split(':').map(Number);
  return h * 60 + m;
}

function minutesToTime(total: number): string {
  const h = Math.floor(total / 60);
  const m = total % 60;
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
}

function buildSlotStarts(intervalMinutes: number, from = DAY_START, to = DAY_END): string[] {
  const start = timeToMinutes(from);
  const end = timeToMinutes(to);
  const slots: string[] = [];
  for (let t = start; t + intervalMinutes <= end; t += intervalMinutes) {
    slots.push(minutesToTime(t));
  }
  return slots;
}

/** Expand a stored window into slot start times for the given interval. */
function expandWindowToSlots(
  startTime: string,
  endTime: string,
  intervalMinutes: number,
): string[] {
  const start = timeToMinutes(startTime.slice(0, 5));
  const end = timeToMinutes(endTime.slice(0, 5));
  const slots: string[] = [];
  for (let t = start; t + intervalMinutes <= end; t += intervalMinutes) {
    slots.push(minutesToTime(t));
  }
  return slots;
}

/** Merge sorted slot starts into contiguous start–end windows. */
function mergeSlotsToWindows(
  selected: string[],
  intervalMinutes: number,
): Array<{ start_time: string; end_time: string }> {
  const sorted = [...selected].sort((a, b) => timeToMinutes(a) - timeToMinutes(b));
  if (sorted.length === 0) return [];

  const windows: Array<{ start_time: string; end_time: string }> = [];
  let rangeStart = sorted[0];
  let prev = sorted[0];

  for (let i = 1; i < sorted.length; i++) {
    const current = sorted[i];
    if (timeToMinutes(current) === timeToMinutes(prev) + intervalMinutes) {
      prev = current;
      continue;
    }
    windows.push({
      start_time: rangeStart,
      end_time: minutesToTime(timeToMinutes(prev) + intervalMinutes),
    });
    rangeStart = current;
    prev = current;
  }

  windows.push({
    start_time: rangeStart,
    end_time: minutesToTime(timeToMinutes(prev) + intervalMinutes),
  });

  return windows;
}

function sameWorkspace(a: string | null | undefined, b: string): boolean {
  const left = a ?? '';
  const right = b || '';
  return left === right;
}

function dayLabel(day: number): string {
  return DAYS_OF_WEEK[day] ?? `Day ${day}`;
}

export function AvailabilityTab({ teamMemberId, locations, workspaces }: AvailabilityTabProps) {
  const [rules, setRules] = useState<StaffAvailabilityRule[]>([]);
  const [locationId, setLocationId] = useState('');
  const [workspaceId, setWorkspaceId] = useState('');
  const [dayOfWeek, setDayOfWeek] = useState(1);
  const [intervalMinutes, setIntervalMinutes] = useState<(typeof SLOT_INTERVALS)[number]>(30);
  const [selectedSlots, setSelectedSlots] = useState<Set<string>>(new Set());
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const load = useCallback(() => {
    fetchStaffAvailability(teamMemberId)
      .then(setRules)
      .catch(() => setRules([]));
  }, [teamMemberId]);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    if (locations[0] && !locationId) {
      setLocationId(locations[0].id);
    }
  }, [locations, locationId]);

  const slotOptions = useMemo(
    () => buildSlotStarts(intervalMinutes),
    [intervalMinutes],
  );

  const scopedRules = useMemo(
    () =>
      rules.filter(
        (rule) =>
          rule.is_active &&
          rule.location_id === locationId &&
          sameWorkspace(rule.workspace_id, workspaceId) &&
          rule.day_of_week === dayOfWeek,
      ),
    [rules, locationId, workspaceId, dayOfWeek],
  );

  const scopeKey = useMemo(
    () =>
      `${dayOfWeek}|${locationId}|${workspaceId}|${intervalMinutes}|${scopedRules
        .map((r) => `${r.id}:${r.start_time}-${r.end_time}`)
        .join(',')}`,
    [dayOfWeek, locationId, workspaceId, intervalMinutes, scopedRules],
  );

  // Hydrate selected slots from saved windows whenever day/location/workspace/interval or saved rules change.
  useEffect(() => {
    const next = new Set<string>();
    for (const rule of scopedRules) {
      for (const slot of expandWindowToSlots(rule.start_time, rule.end_time, intervalMinutes)) {
        next.add(slot);
      }
    }
    setSelectedSlots(next);
    setNotice(null);
    setError(null);
    // scopeKey captures day/location/workspace/interval + persisted rule fingerprints.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [scopeKey]);

  function toggleSlot(slot: string) {
    setSelectedSlots((prev) => {
      const next = new Set(prev);
      if (next.has(slot)) next.delete(slot);
      else next.add(slot);
      return next;
    });
    setNotice(null);
  }

  function selectAllVisible() {
    setSelectedSlots(new Set(slotOptions));
    setNotice(null);
  }

  function clearSelection() {
    setSelectedSlots(new Set());
    setNotice(null);
  }

  async function handleSave(event: React.FormEvent) {
    event.preventDefault();
    if (!locationId) {
      setError('Choose a location.');
      return;
    }

    setSaving(true);
    setError(null);
    setNotice(null);

    try {
      const windows = mergeSlotsToWindows([...selectedSlots], intervalMinutes);

      // Replace this day’s windows for the same location + workspace.
      for (const rule of scopedRules) {
        await archiveStaffAvailability(teamMemberId, rule.id);
      }

      for (const window of windows) {
        await createStaffAvailability(teamMemberId, {
          location_id: locationId,
          workspace_id: workspaceId || undefined,
          day_of_week: dayOfWeek,
          start_time: window.start_time,
          end_time: window.end_time,
        });
      }

      load();
      setNotice(
        windows.length === 0
          ? `${dayLabel(dayOfWeek)} cleared — no slots selected.`
          : `Saved ${selectedSlots.size} slot${selectedSlots.size === 1 ? '' : 's'} for ${dayLabel(dayOfWeek)}.`,
      );
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not save availability');
    } finally {
      setSaving(false);
    }
  }

  async function handleArchiveRule(ruleId: string) {
    setError(null);
    try {
      await archiveStaffAvailability(teamMemberId, ruleId);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Archive failed');
    }
  }

  const weeklyByDay = useMemo(() => {
    const map = new Map<number, StaffAvailabilityRule[]>();
    for (const day of Object.keys(DAYS_OF_WEEK).map(Number)) {
      map.set(day, []);
    }
    for (const rule of rules.filter((r) => r.is_active)) {
      const list = map.get(rule.day_of_week) ?? [];
      list.push(rule);
      map.set(rule.day_of_week, list);
    }
    return map;
  }, [rules]);

  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <Card title="Schedule time slots">
        {error ? <p className="mb-2 text-sm text-red-600">{error}</p> : null}
        {notice ? <p className="mb-2 text-sm text-emerald-700">{notice}</p> : null}
        <form onSubmit={(e) => void handleSave(e)} className="grid gap-3">
          <div className="flex flex-wrap gap-1.5">
            {Object.entries(DAYS_OF_WEEK).map(([value, label]) => {
              const day = Number(value);
              const active = dayOfWeek === day;
              return (
                <button
                  key={value}
                  type="button"
                  onClick={() => setDayOfWeek(day)}
                  className={`rounded-lg px-2.5 py-1.5 text-xs font-semibold transition ${
                    active
                      ? 'bg-[var(--admin-accent)] text-white'
                      : 'border border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50'
                  }`}
                >
                  {label.slice(0, 3)}
                </button>
              );
            })}
          </div>

          <Field label="Location">
            <select
              className={inputClass}
              value={locationId}
              onChange={(e) => setLocationId(e.target.value)}
              required
            >
              {locations.map((l) => (
                <option key={l.id} value={l.id}>
                  {l.name}
                </option>
              ))}
            </select>
          </Field>

          <Field label="Workspace (optional)">
            <select
              className={inputClass}
              value={workspaceId}
              onChange={(e) => setWorkspaceId(e.target.value)}
            >
              <option value="">Any / not specified</option>
              {workspaces.map((w) => (
                <option key={w.id} value={w.id}>
                  {w.name}
                </option>
              ))}
            </select>
          </Field>

          <Field label="Slot length">
            <select
              className={inputClass}
              value={intervalMinutes}
              onChange={(e) =>
                setIntervalMinutes(Number(e.target.value) as (typeof SLOT_INTERVALS)[number])
              }
            >
              {SLOT_INTERVALS.map((mins) => (
                <option key={mins} value={mins}>
                  {mins} minutes
                </option>
              ))}
            </select>
          </Field>

          <div>
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
              <p className="text-sm font-medium text-zinc-700">
                {dayLabel(dayOfWeek)} slots
                <span className="ml-2 font-normal text-zinc-500">
                  ({selectedSlots.size} selected)
                </span>
              </p>
              <div className="flex gap-2">
                <button
                  type="button"
                  className="text-xs font-semibold text-[var(--admin-accent)] hover:underline"
                  onClick={selectAllVisible}
                >
                  Select all
                </button>
                <button
                  type="button"
                  className="text-xs font-semibold text-zinc-500 hover:underline"
                  onClick={clearSelection}
                >
                  Clear
                </button>
              </div>
            </div>
            <div className="grid max-h-72 grid-cols-3 gap-1.5 overflow-y-auto rounded-xl border border-zinc-200 bg-zinc-50/80 p-2 sm:grid-cols-4">
              {slotOptions.map((slot) => {
                const on = selectedSlots.has(slot);
                return (
                  <button
                    key={slot}
                    type="button"
                    aria-pressed={on}
                    onClick={() => toggleSlot(slot)}
                    className={`rounded-lg px-2 py-2 text-xs font-semibold tabular-nums transition ${
                      on
                        ? 'bg-[var(--admin-accent)] text-white shadow-sm'
                        : 'border border-zinc-200 bg-white text-zinc-600 hover:border-[var(--admin-accent)]/40'
                    }`}
                  >
                    {slot}
                  </button>
                );
              })}
            </div>
            <p className="mt-2 text-xs text-zinc-500">
              Tap slots to mark when this provider can take bookings. Contiguous slots save as one
              availability block for online booking.
            </p>
          </div>

          <Button type="submit" disabled={saving || !locationId}>
            {saving ? 'Saving…' : `Save ${dayLabel(dayOfWeek)} slots`}
          </Button>
        </form>
      </Card>

      <Card title="Weekly availability">
        {rules.filter((r) => r.is_active).length === 0 ? (
          <EmptyState message="No availability slots yet. Select times on the left and save." />
        ) : null}
        <div className="space-y-4">
          {[...weeklyByDay.entries()].map(([day, dayRules]) => {
            if (dayRules.length === 0) return null;
            return (
              <div key={day}>
                <p className="text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  {dayLabel(day)}
                </p>
                <ul className="mt-1 divide-y divide-zinc-100">
                  {dayRules.map((rule) => {
                    const chips = expandWindowToSlots(rule.start_time, rule.end_time, 30);
                    return (
                      <li
                        key={rule.id}
                        className="flex items-start justify-between gap-2 py-2.5 text-sm"
                      >
                        <div className="min-w-0">
                          <p className="font-medium">
                            {rule.start_time}–{rule.end_time}
                          </p>
                          <p className="text-xs text-zinc-500">
                            {rule.location?.name ?? 'Location'} ·{' '}
                            {rule.workspace?.name ?? 'No workspace'}
                          </p>
                          {chips.length > 0 ? (
                            <div className="mt-1.5 flex flex-wrap gap-1">
                              {chips.slice(0, 12).map((chip) => (
                                <span
                                  key={chip}
                                  className="rounded-md bg-zinc-100 px-1.5 py-0.5 text-[10px] font-semibold tabular-nums text-zinc-600"
                                >
                                  {chip}
                                </span>
                              ))}
                              {chips.length > 12 ? (
                                <span className="text-[10px] text-zinc-400">
                                  +{chips.length - 12} more
                                </span>
                              ) : null}
                            </div>
                          ) : null}
                        </div>
                        <Button
                          type="button"
                          variant="secondary"
                          onClick={() => void handleArchiveRule(rule.id)}
                        >
                          Archive
                        </Button>
                      </li>
                    );
                  })}
                </ul>
              </div>
            );
          })}
        </div>
      </Card>
    </div>
  );
}
