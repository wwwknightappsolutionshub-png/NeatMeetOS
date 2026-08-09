'use client';

import Link from 'next/link';
import { useCallback, useEffect, useRef, useState } from 'react';
import { AdminBookingShell } from '@/components/admin/booking/AdminBookingShell';
import { AppointmentBoardCard } from '@/components/admin/booking/AppointmentBoardCard';
import {
  EmptyState,
  ErrorAlert,
  Field,
  inputClass,
  LoadingState,
} from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { BookingDayBoard } from '@/lib/booking-types';
import { APPOINTMENT_STATUSES } from '@/lib/booking-types';
import type { Client } from '@/lib/crm-types';
import { subscribeBookingBoard } from '@/lib/echo';
import type { Location, Workspace } from '@/lib/identity-types';
import type { StaffProvider } from '@/lib/staff-types';
import { fetchShell } from '@/services/auth.service';
import { fetchClients } from '@/services/crm.service';
import { fetchLocations, fetchWorkspaces } from '@/services/identity.service';
import {
  cancelAppointment,
  createAppointment,
  createRecurrenceSeries,
  fetchBookableServices,
  fetchBookingDayBoard,
  updateAppointmentStatus,
} from '@/services/booking.service';
import { fetchStaffProviders } from '@/services/staff.service';

function formatDateInput(date: Date): string {
  return date.toISOString().slice(0, 10);
}

export default function BookingsPage() {
  const [board, setBoard] = useState<BookingDayBoard | null>(null);
  const [clients, setClients] = useState<Client[]>([]);
  const [providers, setProviders] = useState<StaffProvider[]>([]);
  const [locations, setLocations] = useState<Location[]>([]);
  const [workspaces, setWorkspaces] = useState<Workspace[]>([]);
  const [services, setServices] = useState<Awaited<ReturnType<typeof fetchBookableServices>>>([]);
  const [date, setDate] = useState(formatDateInput(new Date()));
  const [locationFilter, setLocationFilter] = useState('');
  const [providerFilter, setProviderFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({
    client_id: '',
    team_member_id: '',
    location_id: '',
    workspace_id: '',
    starts_at: '',
    service_id: '',
    client_notes: '',
    is_recurring: false,
    occurrence_count: '4',
  });
  const [recurrenceResult, setRecurrenceResult] = useState<string | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([
      fetchBookingDayBoard({
        date,
        location_id: locationFilter || undefined,
        team_member_id: providerFilter || undefined,
        status: statusFilter || undefined,
      }),
      fetchClients({ is_active: true }),
      fetchStaffProviders(),
      fetchLocations(),
      fetchWorkspaces(),
      fetchBookableServices(),
    ])
      .then(([boardData, clientData, providerList, locs, wss, svcList]) => {
        setBoard(boardData);
        setClients(clientData.items);
        setProviders(providerList);
        setLocations(locs);
        setWorkspaces(wss);
        setServices(svcList);
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, [date, locationFilter, providerFilter, statusFilter]);

  useEffect(() => {
    load();
  }, [load]);

  // Live day-board updates via Reverb (no-op when Reverb env is unset).
  const dateRef = useRef(date);
  const locationFilterRef = useRef(locationFilter);
  dateRef.current = date;
  locationFilterRef.current = locationFilter;

  useEffect(() => {
    let unsubscribe: (() => void) | null = null;
    let cancelled = false;

    fetchShell()
      .then((shell) => {
        if (cancelled || !shell.tenant?.id) return;
        unsubscribe = subscribeBookingBoard(shell.tenant.id, (payload) => {
          if (payload.date !== dateRef.current) return;
          if (
            locationFilterRef.current &&
            payload.location_id &&
            payload.location_id !== locationFilterRef.current
          ) {
            return;
          }
          load();
        });
      })
      .catch(() => {
        /* Echo optional */
      });

    return () => {
      cancelled = true;
      unsubscribe?.();
    };
  }, [load]);

  async function handleCreate(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    setRecurrenceResult(null);
    try {
      const payload = {
        client_id: form.client_id,
        team_member_id: form.team_member_id,
        location_id: form.location_id,
        workspace_id: form.workspace_id || undefined,
        starts_at: form.starts_at,
        client_notes: form.client_notes || undefined,
        services: [{ booking_service_id: form.service_id }],
      };

      if (form.is_recurring) {
        const result = await createRecurrenceSeries({
          ...payload,
          occurrence_count: Number(form.occurrence_count),
        });
        const skipped = result.skipped.length;
        setRecurrenceResult(
          `Created ${result.created_appointment_ids.length} appointments` +
            (skipped > 0 ? ` (${skipped} skipped due to conflicts)` : ''),
        );
      } else {
        await createAppointment(payload);
      }
      setShowForm(false);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Create failed');
    }
  }

  async function handleCheckIn(id: string) {
    try {
      await updateAppointmentStatus(id, 'checked_in');
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Check-in failed');
    }
  }

  async function handleNoShow(id: string) {
    const reason = prompt('No-show reason (optional)') ?? undefined;
    try {
      await updateAppointmentStatus(id, 'no_show', reason || undefined);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'No-show failed');
    }
  }

  async function handleCancel(id: string) {
    try {
      await cancelAppointment(id, 'Cancelled from day board');
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Cancel failed');
    }
  }

  const appointments = board?.appointments ?? [];

  if (loading && !board) {
    return (
      <AdminBookingShell title="Day board">
        <LoadingState />
      </AdminBookingShell>
    );
  }

  return (
    <AdminBookingShell title="Day board">
      {error ? <ErrorAlert message={error} /> : null}
      {recurrenceResult ? (
        <p className="mb-4 text-sm text-emerald-700">{recurrenceResult}</p>
      ) : null}

      <div className="mb-4 space-y-4">
        <Card title="Day filters">
          <div className="flex flex-wrap gap-3">
            <Field label="Date">
              <input type="date" className={inputClass} value={date} onChange={(e) => setDate(e.target.value)} />
            </Field>
            <Field label="Location">
              <select className={inputClass} value={locationFilter} onChange={(e) => setLocationFilter(e.target.value)}>
                <option value="">All</option>
                {locations.map((l) => (
                  <option key={l.id} value={l.id}>{l.name}</option>
                ))}
              </select>
            </Field>
            <Field label="Provider">
              <select className={inputClass} value={providerFilter} onChange={(e) => setProviderFilter(e.target.value)}>
                <option value="">All</option>
                {providers.map((p) => (
                  <option key={p.id} value={p.id}>{p.display_name}</option>
                ))}
              </select>
            </Field>
            <Field label="Status">
              <select className={inputClass} value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}>
                <option value="">All</option>
                {APPOINTMENT_STATUSES.map((s) => (
                  <option key={s} value={s}>{s.replace('_', ' ')}</option>
                ))}
              </select>
            </Field>
          </div>
          {board ? (
            <p className="mt-3 text-xs text-zinc-500">
              {board.summary.total} appointments
              {board.summary.walk_ins_waiting > 0
                ? ` · ${board.summary.walk_ins_waiting} walk-ins waiting`
                : ''}
            </p>
          ) : null}
        </Card>

        {board && board.workspace_occupancy.length > 0 ? (
          <Card title="Workspace occupancy">
            <ul className="flex flex-wrap gap-2 text-xs">
              {board.workspace_occupancy.map((w) => (
                <li key={w.workspace_id} className="rounded bg-zinc-100 px-2 py-1">
                  {w.workspace_name} ({w.workspace_type}): {w.appointments}
                </li>
              ))}
            </ul>
          </Card>
        ) : null}

        <div className="flex flex-wrap justify-between gap-2">
          <Button type="button" onClick={() => setShowForm(!showForm)}>
            {showForm ? 'Close form' : 'New appointment'}
          </Button>
          <Link href="/admin/bookings/walk-ins" className="text-sm text-zinc-600 underline">
            Walk-in queue
          </Link>
        </div>

        {showForm ? (
          <Card title="Create appointment">
            <form onSubmit={handleCreate} className="grid gap-3 md:grid-cols-2">
              <Field label="Client">
                <select className={inputClass} value={form.client_id} onChange={(e) => setForm({ ...form, client_id: e.target.value })} required>
                  <option value="">Select client</option>
                  {clients.map((c) => (
                    <option key={c.id} value={c.id}>{c.resolved_display_name}</option>
                  ))}
                </select>
              </Field>
              <Field label="Provider">
                <select className={inputClass} value={form.team_member_id} onChange={(e) => setForm({ ...form, team_member_id: e.target.value })} required>
                  <option value="">Select provider</option>
                  {providers.filter((p) => p.is_bookable).map((p) => (
                    <option key={p.id} value={p.id}>{p.display_name}</option>
                  ))}
                </select>
              </Field>
              <Field label="Location">
                <select className={inputClass} value={form.location_id} onChange={(e) => setForm({ ...form, location_id: e.target.value })} required>
                  <option value="">Select location</option>
                  {locations.map((l) => (
                    <option key={l.id} value={l.id}>{l.name}</option>
                  ))}
                </select>
              </Field>
              <Field label="Workspace (optional)">
                <select className={inputClass} value={form.workspace_id} onChange={(e) => setForm({ ...form, workspace_id: e.target.value })}>
                  <option value="">None</option>
                  {workspaces.map((w) => (
                    <option key={w.id} value={w.id}>{w.name} ({w.workspace_type})</option>
                  ))}
                </select>
              </Field>
              <Field label="Service">
                <select className={inputClass} value={form.service_id} onChange={(e) => setForm({ ...form, service_id: e.target.value })} required>
                  <option value="">Select service</option>
                  {services.map((s) => (
                    <option key={s.id} value={s.id}>{s.name} ({s.duration_minutes} min)</option>
                  ))}
                </select>
              </Field>
              <Field label="Start">
                <input type="datetime-local" className={inputClass} value={form.starts_at} onChange={(e) => setForm({ ...form, starts_at: e.target.value })} required />
              </Field>
              <Field label="Client notes">
                <input className={inputClass} value={form.client_notes} onChange={(e) => setForm({ ...form, client_notes: e.target.value })} />
              </Field>
              <label className="flex items-center gap-2 text-sm md:col-span-2">
                <input
                  type="checkbox"
                  checked={form.is_recurring}
                  onChange={(e) => setForm({ ...form, is_recurring: e.target.checked })}
                />
                Weekly recurring series
              </label>
              {form.is_recurring ? (
                <Field label="Occurrences">
                  <input
                    type="number"
                    min={2}
                    max={52}
                    className={inputClass}
                    value={form.occurrence_count}
                    onChange={(e) => setForm({ ...form, occurrence_count: e.target.value })}
                  />
                </Field>
              ) : null}
              <div className="md:col-span-2">
                <Button type="submit">Create appointment</Button>
              </div>
            </form>
          </Card>
        ) : null}
      </div>

      <Card title={`Schedule · ${date}`}>
        {appointments.length === 0 ? <EmptyState message="No appointments on this day." /> : null}
        <div className="grid gap-3 md:grid-cols-2">
          {appointments.map((appt) => (
            <AppointmentBoardCard
              key={appt.id}
              appointment={appt}
              onCheckIn={handleCheckIn}
              onNoShow={handleNoShow}
              onCancel={handleCancel}
            />
          ))}
        </div>
      </Card>
    </AdminBookingShell>
  );
}
