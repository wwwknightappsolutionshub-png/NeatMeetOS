'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { AdminCrmShell } from '@/components/admin/crm/AdminCrmShell';
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
import type { Client, ClientTag } from '@/lib/crm-types';
import { fetchClientTags, fetchClients, createClient, setClientStatus } from '@/services/crm.service';

const MONTHS = [
  { value: 1, label: 'Jan' },
  { value: 2, label: 'Feb' },
  { value: 3, label: 'Mar' },
  { value: 4, label: 'Apr' },
  { value: 5, label: 'May' },
  { value: 6, label: 'Jun' },
  { value: 7, label: 'Jul' },
  { value: 8, label: 'Aug' },
  { value: 9, label: 'Sep' },
  { value: 10, label: 'Oct' },
  { value: 11, label: 'Nov' },
  { value: 12, label: 'Dec' },
];

function formatShortDate(iso: string | null | undefined): string {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleDateString(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
}

const emptyForm = {
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  special_event_month: '',
  special_event_day: '',
  special_event_label: '',
};

function specialEventLine(client: Client): string | null {
  if (client.special_event_label?.trim()) {
    return `Special event: ${client.special_event_label.trim()}`;
  }
  if (client.special_event_month && client.special_event_day) {
    const month = MONTHS.find((m) => m.value === client.special_event_month)?.label;
    if (month) return `Special event: ${client.special_event_day} ${month}`;
  }
  return null;
}

export default function ClientsListPage() {
  const [clients, setClients] = useState<Client[]>([]);
  const [tags, setTags] = useState<ClientTag[]>([]);
  const [search, setSearch] = useState('');
  const [tagFilter, setTagFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState<'all' | 'active' | 'inactive'>('active');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState(emptyForm);

  const load = useCallback(() => {
    setLoading(true);
    const params: Parameters<typeof fetchClients>[0] = { search: search || undefined };
    if (statusFilter === 'active') params.is_active = true;
    if (statusFilter === 'inactive') params.is_active = false;
    if (tagFilter) params.tag_ids = tagFilter;

    Promise.all([fetchClients(params), fetchClientTags()])
      .then(([data, tagList]) => {
        setClients(data.items);
        setTags(tagList);
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, [search, statusFilter, tagFilter]);

  useEffect(() => {
    load();
  }, [load]);

  async function handleCreate(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      const month = form.special_event_month ? Number(form.special_event_month) : null;
      const day = form.special_event_day ? Number(form.special_event_day) : null;
      await createClient({
        first_name: form.first_name,
        last_name: form.last_name || null,
        email: form.email || null,
        phone: form.phone || null,
        special_event_month: month,
        special_event_day: day,
        special_event_label: form.special_event_label.trim() || null,
      });
      setShowForm(false);
      setForm(emptyForm);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Create failed');
    }
  }

  async function toggleStatus(client: Client) {
    try {
      await setClientStatus(client.id, !client.is_active);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Status update failed');
    }
  }

  return (
    <AdminCrmShell title="Clients">
      <div className="space-y-4">
        <Card title="Search & filters">
          <div className="flex flex-wrap gap-3">
            <Field label="Search">
              <input
                className={inputClass}
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Name, email, phone…"
              />
            </Field>
            <Field label="Status">
              <select
                className={inputClass}
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value as typeof statusFilter)}
              >
                <option value="active">Active</option>
                <option value="inactive">Archived</option>
                <option value="all">All</option>
              </select>
            </Field>
            <Field label="Tag">
              <select
                className={inputClass}
                value={tagFilter}
                onChange={(e) => setTagFilter(e.target.value)}
              >
                <option value="">All tags</option>
                {tags.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.name}
                  </option>
                ))}
              </select>
            </Field>
            <div className="flex items-end gap-2">
              <Button type="button" onClick={load}>
                Apply
              </Button>
              <Button type="button" onClick={() => setShowForm(true)}>
                New client
              </Button>
              <Link href="/admin/clients/import">
                <Button type="button" variant="secondary">
                  Import CSV
                </Button>
              </Link>
            </div>
          </div>
        </Card>

        {error ? <ErrorAlert message={error} /> : null}

        {showForm ? (
          <Card title="New client">
            <form onSubmit={handleCreate} className="grid max-w-md gap-3">
              <Field label="First name">
                <input
                  className={inputClass}
                  value={form.first_name}
                  onChange={(e) => setForm({ ...form, first_name: e.target.value })}
                  required
                />
              </Field>
              <Field label="Last name">
                <input
                  className={inputClass}
                  value={form.last_name}
                  onChange={(e) => setForm({ ...form, last_name: e.target.value })}
                />
              </Field>
              <Field label="Email">
                <input
                  type="email"
                  className={inputClass}
                  value={form.email}
                  onChange={(e) => setForm({ ...form, email: e.target.value })}
                />
              </Field>
              <Field label="Phone">
                <input
                  className={inputClass}
                  value={form.phone}
                  onChange={(e) => setForm({ ...form, phone: e.target.value })}
                />
              </Field>
              <div className="grid grid-cols-2 gap-3">
                <Field label="Special event month">
                  <select
                    className={inputClass}
                    value={form.special_event_month}
                    onChange={(e) => setForm({ ...form, special_event_month: e.target.value })}
                  >
                    <option value="">—</option>
                    {MONTHS.map((m) => (
                      <option key={m.value} value={m.value}>
                        {m.label}
                      </option>
                    ))}
                  </select>
                </Field>
                <Field label="Special event day">
                  <select
                    className={inputClass}
                    value={form.special_event_day}
                    onChange={(e) => setForm({ ...form, special_event_day: e.target.value })}
                  >
                    <option value="">—</option>
                    {Array.from({ length: 31 }, (_, i) => i + 1).map((d) => (
                      <option key={d} value={d}>
                        {d}
                      </option>
                    ))}
                  </select>
                </Field>
              </div>
              <Field label="Special event label (optional)">
                <input
                  className={inputClass}
                  value={form.special_event_label}
                  onChange={(e) => setForm({ ...form, special_event_label: e.target.value })}
                  placeholder="Birthday, anniversary…"
                />
              </Field>
              <div className="flex gap-2">
                <Button type="submit">Create</Button>
                <Button type="button" variant="secondary" onClick={() => setShowForm(false)}>
                  Cancel
                </Button>
              </div>
            </form>
          </Card>
        ) : null}

        <Card title="Client list">
          {loading ? <LoadingState /> : null}
          {!loading && clients.length === 0 ? (
            <EmptyState message="No clients match your filters." />
          ) : null}
          <ul className="divide-y divide-zinc-100">
            {clients.map((client) => {
              const eventLine = specialEventLine(client);
              return (
                <li
                  key={client.id}
                  className="flex flex-wrap items-center justify-between gap-2 py-3"
                >
                  <div>
                    <Link
                      href={`/admin/clients/${client.id}`}
                      className="font-medium hover:underline"
                    >
                      {client.resolved_display_name}
                    </Link>
                    <p className="text-xs text-zinc-500">
                      {client.email ?? '—'} · {client.phone ?? '—'}
                    </p>
                    <p className="mt-1 text-xs text-zinc-500">
                      Joined {formatShortDate(client.created_at)}
                      {' · '}
                      Last visit {formatShortDate(client.last_visited_at)}
                      {' · '}
                      Plan{' '}
                      {client.active_membership?.plan_name?.trim()
                        ? client.active_membership.plan_name
                        : 'None'}
                    </p>
                    {eventLine ? (
                      <p className="mt-1 text-xs text-zinc-600">{eventLine}</p>
                    ) : null}
                    {client.tags && client.tags.length > 0 ? (
                      <p className="mt-1 text-xs text-zinc-500">
                        {client.tags.map((t) => t.name).join(', ')}
                      </p>
                    ) : null}
                  </div>
                  <div className="flex items-center gap-2">
                    <StatusBadge active={client.is_active} />
                    <Link href={`/admin/clients/${client.id}`}>
                      <Button type="button" variant="secondary">
                        Edit
                      </Button>
                    </Link>
                    <Button type="button" variant="secondary" onClick={() => toggleStatus(client)}>
                      {client.is_active ? 'Archive' : 'Activate'}
                    </Button>
                  </div>
                </li>
              );
            })}
          </ul>
        </Card>
      </div>
    </AdminCrmShell>
  );
}
