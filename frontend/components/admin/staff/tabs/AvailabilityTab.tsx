'use client';

import { useCallback, useEffect, useState } from 'react';
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

export function AvailabilityTab({ teamMemberId, locations, workspaces }: AvailabilityTabProps) {
  const [rules, setRules] = useState<StaffAvailabilityRule[]>([]);
  const [form, setForm] = useState({
    location_id: '',
    workspace_id: '',
    day_of_week: 1,
    start_time: '09:00',
    end_time: '17:00',
  });
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    fetchStaffAvailability(teamMemberId).then(setRules).catch(() => setRules([]));
  }, [teamMemberId]);

  useEffect(() => {
    load();
    if (locations[0]) {
      setForm((f) => ({ ...f, location_id: locations[0].id }));
    }
  }, [load, locations]);

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await createStaffAvailability(teamMemberId, {
        location_id: form.location_id,
        workspace_id: form.workspace_id || undefined,
        day_of_week: form.day_of_week,
        start_time: form.start_time,
        end_time: form.end_time,
      });
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Create failed');
    }
  }

  return (
    <div className="grid gap-4 md:grid-cols-2">
      <Card title="Add availability window">
        {error ? <p className="mb-2 text-sm text-red-600">{error}</p> : null}
        <form onSubmit={handleSubmit} className="grid gap-3">
          <Field label="Day">
            <select
              className={inputClass}
              value={form.day_of_week}
              onChange={(e) => setForm({ ...form, day_of_week: Number(e.target.value) })}
            >
              {Object.entries(DAYS_OF_WEEK).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </Field>
          <Field label="Location">
            <select
              className={inputClass}
              value={form.location_id}
              onChange={(e) => setForm({ ...form, location_id: e.target.value })}
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
              value={form.workspace_id}
              onChange={(e) => setForm({ ...form, workspace_id: e.target.value })}
            >
              <option value="">Any / not specified</option>
              {workspaces.map((w) => (
                <option key={w.id} value={w.id}>
                  {w.name}
                </option>
              ))}
            </select>
          </Field>
          <Field label="Start">
            <input
              type="time"
              className={inputClass}
              value={form.start_time}
              onChange={(e) => setForm({ ...form, start_time: e.target.value })}
              required
            />
          </Field>
          <Field label="End">
            <input
              type="time"
              className={inputClass}
              value={form.end_time}
              onChange={(e) => setForm({ ...form, end_time: e.target.value })}
              required
            />
          </Field>
          <Button type="submit">Add window</Button>
        </form>
      </Card>
      <Card title="Weekly availability">
        {rules.length === 0 ? <EmptyState message="No availability windows yet." /> : null}
        <ul className="divide-y divide-zinc-100">
          {rules.map((rule) => (
            <li key={rule.id} className="flex items-start justify-between gap-2 py-3 text-sm">
              <div>
                <p className="font-medium">
                  {DAYS_OF_WEEK[rule.day_of_week]} · {rule.start_time}–{rule.end_time}
                </p>
                <p className="text-xs text-zinc-500">
                  {rule.location?.name ?? 'Location'} · {rule.workspace?.name ?? 'No workspace'}
                </p>
              </div>
              <Button
                type="button"
                variant="secondary"
                onClick={async () => {
                  await archiveStaffAvailability(teamMemberId, rule.id);
                  load();
                }}
              >
                Archive
              </Button>
            </li>
          ))}
        </ul>
      </Card>
    </div>
  );
}
