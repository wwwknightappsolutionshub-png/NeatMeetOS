'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { AdminBookingShell } from '@/components/admin/booking/AdminBookingShell';
import { EmptyState, ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { WaitlistEntry } from '@/lib/booking-types';
import { WAITLIST_STATUSES } from '@/lib/booking-types';
import type { Client } from '@/lib/crm-types';
import type { Location } from '@/lib/identity-types';
import type { StaffProvider } from '@/lib/staff-types';
import { fetchClients } from '@/services/crm.service';
import {
  createWaitlistEntry,
  fetchBookableServices,
  fetchWaitlist,
  fulfillWaitlistEntry,
  updateWaitlistEntry,
} from '@/services/booking.service';
import { fetchLocations } from '@/services/identity.service';
import { fetchStaffProviders } from '@/services/staff.service';

export default function WaitlistPage() {
  const [entries, setEntries] = useState<WaitlistEntry[]>([]);
  const [clients, setClients] = useState<Client[]>([]);
  const [providers, setProviders] = useState<StaffProvider[]>([]);
  const [locations, setLocations] = useState<Location[]>([]);
  const [services, setServices] = useState<Awaited<ReturnType<typeof fetchBookableServices>>>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [statusFilter, setStatusFilter] = useState('');
  const [locationFilter, setLocationFilter] = useState('');
  const [providerFilter, setProviderFilter] = useState('');
  const [serviceFilter, setServiceFilter] = useState('');
  const [fulfillId, setFulfillId] = useState<string | null>(null);
  const [fulfillStartsAt, setFulfillStartsAt] = useState('');
  const [form, setForm] = useState({
    client_id: '',
    location_id: '',
    team_member_id: '',
    service_id: '',
    availability_notes: '',
    notes: '',
  });

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([
      fetchWaitlist({
        status: statusFilter || undefined,
        location_id: locationFilter || undefined,
        team_member_id: providerFilter || undefined,
        booking_service_id: serviceFilter || undefined,
      }),
      fetchClients({ is_active: true }),
      fetchStaffProviders(),
      fetchLocations(),
      fetchBookableServices(),
    ])
      .then(([list, clientData, providerList, locs, svcList]) => {
        setEntries(list);
        setClients(clientData.items);
        setProviders(providerList);
        setLocations(locs);
        setServices(svcList);
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, [statusFilter, locationFilter, providerFilter, serviceFilter]);

  useEffect(() => {
    load();
  }, [load]);

  async function handleCreate(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await createWaitlistEntry({
        client_id: form.client_id,
        location_id: form.location_id,
        team_member_id: form.team_member_id || undefined,
        availability_notes: form.availability_notes || undefined,
        notes: form.notes || undefined,
        services: form.service_id ? [{ booking_service_id: form.service_id }] : undefined,
      });
      setShowForm(false);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Create failed');
    }
  }

  async function handleFulfill(entry: WaitlistEntry) {
    if (!fulfillStartsAt) {
      setError('Start time is required');
      return;
    }
    const providerId = entry.team_member_id ?? providers.find((p) => p.is_bookable)?.id;
    if (!providerId) {
      setError('Provider is required to book');
      return;
    }
    try {
      const result = await fulfillWaitlistEntry(entry.id, {
        starts_at: fulfillStartsAt,
        team_member_id: providerId,
        location_id: entry.location_id,
      });
      setFulfillId(null);
      window.location.href = `/admin/bookings/${result.appointment.id}`;
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Fulfill failed');
    }
  }

  if (loading) {
    return (
      <AdminBookingShell title="Waitlist">
        <LoadingState />
      </AdminBookingShell>
    );
  }

  return (
    <AdminBookingShell title="Waitlist">
      {error ? <ErrorAlert message={error} /> : null}

      <Card title="Filters">
        <div className="flex flex-wrap gap-3">
          <Field label="Status">
            <select className={inputClass} value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}>
              <option value="">All</option>
              {WAITLIST_STATUSES.map((s) => (
                <option key={s} value={s}>{s}</option>
              ))}
            </select>
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
          <Field label="Service">
            <select className={inputClass} value={serviceFilter} onChange={(e) => setServiceFilter(e.target.value)}>
              <option value="">All</option>
              {services.map((s) => (
                <option key={s.id} value={s.id}>{s.name}</option>
              ))}
            </select>
          </Field>
        </div>
      </Card>

      <div className="mb-4 mt-4">
        <Button type="button" onClick={() => setShowForm(!showForm)}>
          {showForm ? 'Close form' : 'Add waitlist entry'}
        </Button>
      </div>

      {showForm ? (
        <div className="mb-4">
        <Card title="New waitlist entry">
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
            <Field label="Preferred provider">
              <select className={inputClass} value={form.team_member_id} onChange={(e) => setForm({ ...form, team_member_id: e.target.value })}>
                <option value="">Any</option>
                {providers.map((p) => (
                  <option key={p.id} value={p.id}>{p.display_name}</option>
                ))}
              </select>
            </Field>
            <Field label="Service">
              <select className={inputClass} value={form.service_id} onChange={(e) => setForm({ ...form, service_id: e.target.value })}>
                <option value="">Not specified</option>
                {services.map((s) => (
                  <option key={s.id} value={s.id}>{s.name}</option>
                ))}
              </select>
            </Field>
            <Field label="Availability notes">
              <input className={inputClass} value={form.availability_notes} onChange={(e) => setForm({ ...form, availability_notes: e.target.value })} />
            </Field>
            <Field label="Notes">
              <input className={inputClass} value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} />
            </Field>
            <div className="md:col-span-2">
              <Button type="submit">Add to waitlist</Button>
            </div>
          </form>
        </Card>
        </div>
      ) : null}

      <Card title="Entries">
        {entries.length === 0 ? <EmptyState message="No waitlist entries." /> : null}
        <ul className="divide-y divide-zinc-100">
          {entries.map((entry) => (
            <li key={entry.id} className="flex flex-wrap items-start justify-between gap-2 py-3 text-sm">
              <div>
                <p className="font-medium">{entry.client?.resolved_display_name}</p>
                <p className="text-xs text-zinc-500">
                  {entry.location?.name} · {entry.team_member?.display_name ?? 'Any provider'}
                </p>
                <p className="text-xs text-zinc-400">{entry.availability_notes ?? entry.notes}</p>
                {entry.contacted_at ? (
                  <p className="text-xs text-zinc-400">Contacted {new Date(entry.contacted_at).toLocaleString()}</p>
                ) : null}
              </div>
              <div className="flex flex-col items-end gap-2">
                <select
                  className={inputClass}
                  value={entry.status}
                  onChange={async (e) => {
                    await updateWaitlistEntry(entry.id, { status: e.target.value });
                    load();
                  }}
                >
                  {WAITLIST_STATUSES.map((s) => (
                    <option key={s} value={s}>{s}</option>
                  ))}
                </select>
                {entry.status !== 'booked' ? (
                  fulfillId === entry.id ? (
                    <div className="flex flex-wrap items-center gap-2">
                      <input
                        type="datetime-local"
                        className={inputClass}
                        value={fulfillStartsAt}
                        onChange={(e) => setFulfillStartsAt(e.target.value)}
                      />
                      <Button type="button" onClick={() => handleFulfill(entry)}>Confirm</Button>
                      <Button type="button" variant="secondary" onClick={() => setFulfillId(null)}>Cancel</Button>
                    </div>
                  ) : (
                    <Button type="button" variant="secondary" onClick={() => { setFulfillId(entry.id); setFulfillStartsAt(''); }}>
                      Book
                    </Button>
                  )
                ) : entry.fulfilled_appointment_id ? (
                  <Link href={`/admin/bookings/${entry.fulfilled_appointment_id}`} className="text-sm underline">
                    View
                  </Link>
                ) : null}
              </div>
            </li>
          ))}
        </ul>
      </Card>
    </AdminBookingShell>
  );
}
