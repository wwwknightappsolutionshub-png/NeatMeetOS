'use client';

import { useCallback, useEffect, useState } from 'react';
import { EmptyState, Field, inputClass } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { StaffAbsence } from '@/lib/staff-types';
import { ABSENCE_CATEGORIES } from '@/lib/staff-types';
import { cancelStaffAbsence, createStaffAbsence, fetchStaffAbsences } from '@/services/staff.service';

interface AbsencesTabProps {
  teamMemberId: string;
}

export function AbsencesTab({ teamMemberId }: AbsencesTabProps) {
  const [absences, setAbsences] = useState<StaffAbsence[]>([]);
  const [form, setForm] = useState({
    category: 'holiday',
    starts_at: '',
    ends_at: '',
    note: '',
  });
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    fetchStaffAbsences(teamMemberId).then(setAbsences).catch(() => setAbsences([]));
  }, [teamMemberId]);

  useEffect(() => {
    load();
  }, [load]);

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await createStaffAbsence(teamMemberId, form);
      setForm({ category: 'holiday', starts_at: '', ends_at: '', note: '' });
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Create failed');
    }
  }

  return (
    <div className="grid gap-4 md:grid-cols-2">
      <Card title="Add absence">
        {error ? <p className="mb-2 text-sm text-red-600">{error}</p> : null}
        <form onSubmit={handleSubmit} className="grid gap-3">
          <Field label="Category">
            <select
              className={inputClass}
              value={form.category}
              onChange={(e) => setForm({ ...form, category: e.target.value })}
            >
              {ABSENCE_CATEGORIES.map((c) => (
                <option key={c} value={c}>
                  {c}
                </option>
              ))}
            </select>
          </Field>
          <Field label="Starts">
            <input
              type="datetime-local"
              className={inputClass}
              value={form.starts_at}
              onChange={(e) => setForm({ ...form, starts_at: e.target.value })}
              required
            />
          </Field>
          <Field label="Ends">
            <input
              type="datetime-local"
              className={inputClass}
              value={form.ends_at}
              onChange={(e) => setForm({ ...form, ends_at: e.target.value })}
              required
            />
          </Field>
          <Field label="Note">
            <textarea
              className={inputClass}
              rows={2}
              value={form.note}
              onChange={(e) => setForm({ ...form, note: e.target.value })}
            />
          </Field>
          <Button type="submit">Add absence</Button>
        </form>
      </Card>
      <Card title="Active absences">
        {absences.length === 0 ? <EmptyState message="No absences recorded." /> : null}
        <ul className="divide-y divide-zinc-100">
          {absences.map((absence) => (
            <li key={absence.id} className="flex items-start justify-between gap-2 py-3 text-sm">
              <div>
                <p className="font-medium">{absence.category}</p>
                <p className="text-xs text-zinc-500">
                  {new Date(absence.starts_at).toLocaleString()} –{' '}
                  {new Date(absence.ends_at).toLocaleString()}
                </p>
                {absence.note ? <p className="text-xs text-zinc-600">{absence.note}</p> : null}
              </div>
              <Button
                type="button"
                variant="secondary"
                onClick={async () => {
                  await cancelStaffAbsence(teamMemberId, absence.id);
                  load();
                }}
              >
                Cancel
              </Button>
            </li>
          ))}
        </ul>
      </Card>
    </div>
  );
}
