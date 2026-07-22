'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminSettingsShell } from '@/components/admin/AdminSettingsShell';
import {
  EmptyState,
  ErrorAlert,
  Field,
  inputClass,
  LoadingState,
  StatusBadge,
} from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { Location, LocationOpeningHour } from '@/lib/identity-types';
import { DAYS_OF_WEEK } from '@/lib/staff-types';
import {
  createLocation,
  fetchLocations,
  setLocationStatus,
  updateLocation,
} from '@/services/identity.service';

function defaultOpeningHours(): LocationOpeningHour[] {
  return [1, 2, 3, 4, 5, 6, 7].map((day) => ({
    day_of_week: day,
    start_time: day === 7 ? null : '09:00',
    end_time: day === 7 ? null : '18:00',
    is_closed: day === 7,
  }));
}

const emptyForm = {
  name: '',
  timezone: 'Europe/London',
  contact_email: '',
  contact_phone: '',
  address: { line1: '', city: '', postcode: '', country: 'GB' },
  latitude: '',
  longitude: '',
  geofence_radius_meters: '100',
  opening_hours: defaultOpeningHours(),
};

export default function LocationsSettingsPage() {
  const [locations, setLocations] = useState<Location[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState(emptyForm);
  const [showForm, setShowForm] = useState(false);

  const load = useCallback(() => {
    setLoading(true);
    fetchLocations()
      .then(setLocations)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  function startCreate() {
    setEditingId(null);
    setForm(emptyForm);
    setShowForm(true);
  }

  function startEdit(location: Location) {
    setEditingId(location.id);
    const hours =
      location.opening_hours && location.opening_hours.length > 0
        ? [1, 2, 3, 4, 5, 6, 7].map((day) => {
            const existing = location.opening_hours?.find((h) => h.day_of_week === day);
            return (
              existing ?? {
                day_of_week: day,
                start_time: null,
                end_time: null,
                is_closed: true,
              }
            );
          })
        : defaultOpeningHours();
    setForm({
      name: location.name,
      timezone: location.timezone,
      contact_email: location.contact_email ?? '',
      contact_phone: location.contact_phone ?? '',
      address: {
        line1: location.address?.line1 ?? '',
        city: location.address?.city ?? '',
        postcode: location.address?.postcode ?? '',
        country: location.address?.country ?? 'GB',
      },
      latitude: location.latitude != null ? String(location.latitude) : '',
      longitude: location.longitude != null ? String(location.longitude) : '',
      geofence_radius_meters:
        location.geofence_radius_meters != null
          ? String(location.geofence_radius_meters)
          : '100',
      opening_hours: hours,
    });
    setShowForm(true);
  }

  function updateHour(day: number, patch: Partial<LocationOpeningHour>) {
    setForm((prev) => ({
      ...prev,
      opening_hours: prev.opening_hours.map((row) =>
        row.day_of_week === day ? { ...row, ...patch } : row,
      ),
    }));
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      const payload = {
        ...form,
        latitude: form.latitude.trim() === '' ? null : Number(form.latitude),
        longitude: form.longitude.trim() === '' ? null : Number(form.longitude),
        geofence_radius_meters:
          form.geofence_radius_meters.trim() === ''
            ? null
            : Number(form.geofence_radius_meters),
        opening_hours: form.opening_hours.map((row) =>
          row.is_closed
            ? {
                day_of_week: row.day_of_week,
                start_time: null,
                end_time: null,
                is_closed: true,
              }
            : {
                day_of_week: row.day_of_week,
                start_time: row.start_time,
                end_time: row.end_time,
                is_closed: false,
              },
        ),
      };
      if (editingId) {
        await updateLocation(editingId, payload);
      } else {
        await createLocation(payload);
      }
      setShowForm(false);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed');
    }
  }

  async function toggleStatus(location: Location) {
    try {
      await setLocationStatus(location.id, !location.is_active);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Status update failed');
    }
  }

  return (
    <AdminSettingsShell title="Locations">
      <div className="space-y-4">
        <div className="flex justify-end">
          <Button type="button" onClick={startCreate}>
            Add location
          </Button>
        </div>
        {error ? <ErrorAlert message={error} /> : null}
        {showForm ? (
          <Card title={editingId ? 'Edit location' : 'New location'}>
            <form onSubmit={handleSubmit} className="grid max-w-2xl gap-3">
              <Field label="Name">
                <input
                  className={inputClass}
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                  required
                />
              </Field>
              <Field label="Timezone">
                <input
                  className={inputClass}
                  value={form.timezone}
                  onChange={(e) => setForm({ ...form, timezone: e.target.value })}
                />
              </Field>
              <Field label="Address line 1">
                <input
                  className={inputClass}
                  value={form.address?.line1 ?? ''}
                  onChange={(e) =>
                    setForm({
                      ...form,
                      address: { ...form.address, line1: e.target.value },
                    })
                  }
                />
              </Field>
              <Field label="City">
                <input
                  className={inputClass}
                  value={form.address?.city ?? ''}
                  onChange={(e) =>
                    setForm({
                      ...form,
                      address: { ...form.address, city: e.target.value },
                    })
                  }
                />
              </Field>
              <Field label="Postcode">
                <input
                  className={inputClass}
                  value={form.address?.postcode ?? ''}
                  onChange={(e) =>
                    setForm({
                      ...form,
                      address: { ...form.address, postcode: e.target.value },
                    })
                  }
                />
              </Field>

              <div className="grid gap-3 sm:grid-cols-3">
                <Field label="Latitude">
                  <input
                    type="number"
                    step="any"
                    className={inputClass}
                    value={form.latitude}
                    onChange={(e) => setForm({ ...form, latitude: e.target.value })}
                    placeholder="e.g. 51.5074"
                  />
                </Field>
                <Field label="Longitude">
                  <input
                    type="number"
                    step="any"
                    className={inputClass}
                    value={form.longitude}
                    onChange={(e) => setForm({ ...form, longitude: e.target.value })}
                    placeholder="e.g. -0.1278"
                  />
                </Field>
                <Field label="Geofence radius (m)">
                  <input
                    type="number"
                    min={0}
                    className={inputClass}
                    value={form.geofence_radius_meters}
                    onChange={(e) =>
                      setForm({ ...form, geofence_radius_meters: e.target.value })
                    }
                    placeholder="100"
                  />
                </Field>
              </div>
              <p className="text-xs text-zinc-500">
                Used for member app check-in reminders when clients enter or leave the salon area.
              </p>

              <div className="rounded-lg border border-zinc-200 p-3">
                <p className="mb-2 text-sm font-semibold text-zinc-700">Salon opening hours</p>
                <p className="mb-3 text-xs text-zinc-500">
                  Online booking and scheduling cannot place appointments outside these hours.
                </p>
                <ul className="space-y-2">
                  {form.opening_hours.map((row) => {
                    const label = DAYS_OF_WEEK[row.day_of_week] ?? `Day ${row.day_of_week}`;
                    return (
                      <li
                        key={row.day_of_week}
                        className="grid grid-cols-[100px_80px_1fr_1fr] items-center gap-2 text-sm"
                      >
                        <span className="font-medium">{label}</span>
                        <label className="flex items-center gap-1 text-xs text-zinc-600">
                          <input
                            type="checkbox"
                            checked={!!row.is_closed}
                            onChange={(e) =>
                              updateHour(row.day_of_week, {
                                is_closed: e.target.checked,
                                start_time: e.target.checked ? null : row.start_time ?? '09:00',
                                end_time: e.target.checked ? null : row.end_time ?? '18:00',
                              })
                            }
                          />
                          Closed
                        </label>
                        <input
                          type="time"
                          className={inputClass}
                          disabled={!!row.is_closed}
                          value={row.start_time ?? ''}
                          onChange={(e) =>
                            updateHour(row.day_of_week, { start_time: e.target.value })
                          }
                        />
                        <input
                          type="time"
                          className={inputClass}
                          disabled={!!row.is_closed}
                          value={row.end_time ?? ''}
                          onChange={(e) =>
                            updateHour(row.day_of_week, { end_time: e.target.value })
                          }
                        />
                      </li>
                    );
                  })}
                </ul>
              </div>

              <div className="flex gap-2">
                <Button type="submit">Save</Button>
                <Button type="button" variant="secondary" onClick={() => setShowForm(false)}>
                  Cancel
                </Button>
              </div>
            </form>
          </Card>
        ) : null}
        <Card title="All locations">
          {loading ? <LoadingState /> : null}
          {!loading && locations.length === 0 ? (
            <EmptyState message="No locations yet. Add your first salon site." />
          ) : null}
          <ul className="divide-y divide-zinc-100">
            {locations.map((location) => (
              <li
                key={location.id}
                className="flex flex-wrap items-center justify-between gap-2 py-3"
              >
                <div>
                  <p className="font-medium">{location.name}</p>
                  <p className="text-xs text-zinc-500">
                    {location.slug}
                    {location.opening_hours?.length
                      ? ` · ${location.opening_hours.filter((h) => !h.is_closed).length} open days`
                      : ' · hours not set'}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <StatusBadge active={location.is_active} />
                  <Button type="button" variant="secondary" onClick={() => startEdit(location)}>
                    Edit
                  </Button>
                  <Button type="button" variant="secondary" onClick={() => toggleStatus(location)}>
                    {location.is_active ? 'Deactivate' : 'Activate'}
                  </Button>
                </div>
              </li>
            ))}
          </ul>
        </Card>
      </div>
    </AdminSettingsShell>
  );
}
