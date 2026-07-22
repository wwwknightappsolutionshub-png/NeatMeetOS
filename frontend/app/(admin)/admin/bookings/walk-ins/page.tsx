'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminBookingShell } from '@/components/admin/booking/AdminBookingShell';
import { EmptyState, ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { Client } from '@/lib/crm-types';
import type { Location } from '@/lib/identity-types';
import type { StaffProvider } from '@/lib/staff-types';
import { fetchClients } from '@/services/crm.service';
import { fetchLocations } from '@/services/identity.service';
import { fetchStaffProviders } from '@/services/staff.service';
import {
  createWalkIn,
  fetchBookableServices,
  fetchWalkIns,
  seatWalkIn,
} from '@/services/booking.service';
import type { Appointment } from '@/lib/booking-types';
import Link from 'next/link';

export default function WalkInsPage() {
  const [walkIns, setWalkIns] = useState<Appointment[]>([]);
  const [clients, setClients] = useState<Client[]>([]);
  const [providers, setProviders] = useState<StaffProvider[]>([]);
  const [locations, setLocations] = useState<Location[]>([]);
  const [services, setServices] = useState<Awaited<ReturnType<typeof fetchBookableServices>>>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({
    client_id: '',
    location_id: '',
    service_id: '',
    internal_notes: '',
    seat_immediately: false,
  });

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([
      fetchWalkIns(),
      fetchClients({ is_active: true }),
      fetchStaffProviders(),
      fetchLocations(),
      fetchBookableServices(),
    ])
      .then(([list, clientData, providerList, locs, svcList]) => {
        setWalkIns(list);
        setClients(clientData.items);
        setProviders(providerList);
        setLocations(locs);
        setServices(svcList);
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function handleCreate(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await createWalkIn({
        client_id: form.client_id,
        location_id: form.location_id,
        internal_notes: form.internal_notes || undefined,
        seat_immediately: form.seat_immediately,
        services: [{ booking_service_id: form.service_id }],
      });
      setShowForm(false);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Create failed');
    }
  }

  async function handleSeat(walkIn: Appointment) {
    const providerId = walkIn.team_member_id
      ?? providers.find((p) => p.is_bookable)?.id;
    if (!providerId) {
      setError('No bookable provider available');
      return;
    }
    const startsAt = prompt('Start time (YYYY-MM-DDTHH:mm)', new Date().toISOString().slice(0, 16));
    if (!startsAt) return;
    try {
      await seatWalkIn(walkIn.id, {
        team_member_id: providerId,
        starts_at: startsAt,
      });
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Seat failed');
    }
  }

  if (loading && walkIns.length === 0) {
    return (
      <AdminBookingShell title="Walk-ins">
        <LoadingState />
      </AdminBookingShell>
    );
  }

  return (
    <AdminBookingShell title="Walk-ins">
      {error ? <ErrorAlert message={error} /> : null}

      <div className="mb-4">
        <Button type="button" onClick={() => setShowForm(!showForm)}>
          {showForm ? 'Close form' : 'Register walk-in'}
        </Button>
      </div>

      {showForm ? (
        <div className="mb-4">
          <Card title="Register walk-in">
            <form onSubmit={handleCreate} className="grid gap-3 md:grid-cols-2">
              <Field label="Client">
                <select className={inputClass} value={form.client_id} onChange={(e) => setForm({ ...form, client_id: e.target.value })} required>
                  <option value="">Select</option>
                  {clients.map((c) => (
                    <option key={c.id} value={c.id}>{c.resolved_display_name}</option>
                  ))}
                </select>
              </Field>
              <Field label="Location">
                <select className={inputClass} value={form.location_id} onChange={(e) => setForm({ ...form, location_id: e.target.value })} required>
                  <option value="">Select</option>
                  {locations.map((l) => (
                    <option key={l.id} value={l.id}>{l.name}</option>
                  ))}
                </select>
              </Field>
              <Field label="Service">
                <select className={inputClass} value={form.service_id} onChange={(e) => setForm({ ...form, service_id: e.target.value })} required>
                  <option value="">Select</option>
                  {services.map((s) => (
                    <option key={s.id} value={s.id}>{s.name}</option>
                  ))}
                </select>
              </Field>
              <Field label="Notes">
                <input className={inputClass} value={form.internal_notes} onChange={(e) => setForm({ ...form, internal_notes: e.target.value })} />
              </Field>
              <label className="flex items-center gap-2 text-sm md:col-span-2">
                <input
                  type="checkbox"
                  checked={form.seat_immediately}
                  onChange={(e) => setForm({ ...form, seat_immediately: e.target.checked })}
                />
                Seat immediately (requires provider assignment at seat step)
              </label>
              <div className="md:col-span-2">
                <Button type="submit">Add to queue</Button>
              </div>
            </form>
          </Card>
        </div>
      ) : null}

      <Card title="Waiting queue">
        {walkIns.length === 0 ? <EmptyState message="No walk-ins in queue." /> : null}
        <ul className="divide-y divide-zinc-100">
          {walkIns.map((w) => (
            <li key={w.id} className="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
              <div>
                <p className="font-medium">{w.client?.resolved_display_name}</p>
                <p className="text-xs text-zinc-500">
                  Arrived {w.arrived_at ? new Date(w.arrived_at).toLocaleTimeString() : '—'}
                  {' · '}{w.walk_in_stage}
                  {' · '}{w.services?.map((s) => s.service_name).join(', ')}
                </p>
              </div>
              <div className="flex gap-2">
                {w.walk_in_stage === 'waiting' ? (
                  <Button type="button" onClick={() => handleSeat(w)}>Seat</Button>
                ) : null}
                <Link href={`/admin/bookings/${w.id}`} className="text-sm underline">Open</Link>
              </div>
            </li>
          ))}
        </ul>
      </Card>
    </AdminBookingShell>
  );
}
