'use client';

import { useState } from 'react';
import { Field, inputClass } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { StaffProvider } from '@/lib/staff-types';
import { updateStaffProfile } from '@/services/staff.service';

interface ProfileTabProps {
  provider: StaffProvider;
  onSaved: () => void;
}

export function ProfileTab({ provider, onSaved }: ProfileTabProps) {
  const profile = provider.profile;
  const [form, setForm] = useState({
    is_bookable: profile?.is_bookable ?? false,
    show_in_online_booking: profile?.show_in_online_booking ?? false,
    accepts_walk_ins: profile?.accepts_walk_ins ?? false,
    booking_display_name: profile?.booking_display_name ?? '',
    internal_notes: profile?.internal_notes ?? '',
    min_lead_time_minutes: profile?.min_lead_time_minutes?.toString() ?? '',
    buffer_minutes: profile?.buffer_minutes?.toString() ?? '',
  });
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await updateStaffProfile(provider.id, {
        is_bookable: form.is_bookable,
        show_in_online_booking: form.show_in_online_booking,
        accepts_walk_ins: form.accepts_walk_ins,
        booking_display_name: form.booking_display_name || null,
        internal_notes: form.internal_notes || null,
        min_lead_time_minutes: form.min_lead_time_minutes
          ? Number(form.min_lead_time_minutes)
          : null,
        buffer_minutes: form.buffer_minutes ? Number(form.buffer_minutes) : null,
      });
      onSaved();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed');
    }
  }

  return (
    <Card title="Booking profile">
      {error ? <p className="mb-2 text-sm text-red-600">{error}</p> : null}
      <form onSubmit={handleSubmit} className="grid max-w-lg gap-3">
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={form.is_bookable}
            onChange={(e) => setForm({ ...form, is_bookable: e.target.checked })}
          />
          Bookable provider
        </label>
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={form.show_in_online_booking}
            onChange={(e) => setForm({ ...form, show_in_online_booking: e.target.checked })}
          />
          Show in online booking
        </label>
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={form.accepts_walk_ins}
            onChange={(e) => setForm({ ...form, accepts_walk_ins: e.target.checked })}
          />
          Accepts walk-ins
        </label>
        <Field label="Booking display name">
          <input
            className={inputClass}
            value={form.booking_display_name}
            onChange={(e) => setForm({ ...form, booking_display_name: e.target.value })}
            placeholder={provider.display_name}
          />
        </Field>
        <Field label="Internal notes">
          <textarea
            className={inputClass}
            rows={3}
            value={form.internal_notes}
            onChange={(e) => setForm({ ...form, internal_notes: e.target.value })}
          />
        </Field>
        <Field label="Min lead time (minutes)">
          <input
            type="number"
            className={inputClass}
            value={form.min_lead_time_minutes}
            onChange={(e) => setForm({ ...form, min_lead_time_minutes: e.target.value })}
            placeholder="Booking module placeholder"
          />
        </Field>
        <Field label="Buffer (minutes)">
          <input
            type="number"
            className={inputClass}
            value={form.buffer_minutes}
            onChange={(e) => setForm({ ...form, buffer_minutes: e.target.value })}
            placeholder="Booking module placeholder"
          />
        </Field>
        <Button type="submit">Save profile</Button>
      </form>
    </Card>
  );
}
