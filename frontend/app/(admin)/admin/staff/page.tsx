'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { AdminStaffShell } from '@/components/admin/staff/AdminStaffShell';
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
import type { Location, Workspace } from '@/lib/identity-types';
import { EMPLOYMENT_TYPES } from '@/lib/identity-types';
import type { StaffProvider } from '@/lib/staff-types';
import {
  createTeamMember,
  fetchLocations,
  fetchWorkspaces,
  setTeamMemberStatus,
  updateTeamMember,
} from '@/services/identity.service';
import { fetchStaffProviders } from '@/services/staff.service';

export default function StaffListPage() {
  const [providers, setProviders] = useState<StaffProvider[]>([]);
  const [locations, setLocations] = useState<Location[]>([]);
  const [workspaces, setWorkspaces] = useState<Workspace[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState({
    email: '',
    first_name: '',
    last_name: '',
    display_name: '',
    phone: '',
    employment_type: 'employee',
    primary_location_id: '',
    workspace_ids: [] as string[],
  });

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([fetchStaffProviders(), fetchLocations(), fetchWorkspaces()])
      .then(([p, l, w]) => {
        setProviders(p);
        setLocations(l);
        setWorkspaces(w);
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  function startCreate() {
    setEditingId(null);
    setForm({
      email: '',
      first_name: '',
      last_name: '',
      display_name: '',
      phone: '',
      employment_type: 'employee',
      primary_location_id: locations[0]?.id ?? '',
      workspace_ids: [],
    });
    setShowForm(true);
  }

  function startEdit(provider: StaffProvider) {
    setEditingId(provider.id);
    setForm({
      email: '',
      first_name: provider.first_name ?? '',
      last_name: provider.last_name ?? '',
      display_name: provider.display_name,
      phone: '',
      employment_type: provider.employment_type,
      primary_location_id: provider.primary_location_id ?? '',
      workspace_ids: provider.workspace_ids ?? [],
    });
    setShowForm(true);
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      if (editingId) {
        const { email: _email, ...updateData } = form;
        await updateTeamMember(editingId, updateData);
      } else {
        await createTeamMember(form);
      }
      setShowForm(false);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed');
    }
  }

  async function toggleStatus(provider: StaffProvider) {
    try {
      await setTeamMemberStatus(provider.id, !provider.is_active);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Status update failed');
    }
  }

  if (loading && providers.length === 0) {
    return (
      <AdminStaffShell title="Staff">
        <LoadingState />
      </AdminStaffShell>
    );
  }

  return (
    <AdminStaffShell title="Staff providers">
      <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
        <p className="text-sm text-zinc-600">
          Manage providers, then set weekly availability on each profile.
        </p>
        <Button type="button" onClick={startCreate}>
          Add staff
        </Button>
      </div>
      {error ? <ErrorAlert message={error} /> : null}

      {showForm ? (
        <Card title={editingId ? 'Edit staff member' : 'New staff member'}>
          <form onSubmit={handleSubmit} className="grid max-w-xl gap-3">
            {!editingId ? (
              <Field label="Email">
                <input
                  type="email"
                  className={inputClass}
                  value={form.email}
                  onChange={(e) => setForm({ ...form, email: e.target.value })}
                  required
                />
              </Field>
            ) : null}
            <Field label="First name">
              <input
                className={inputClass}
                value={form.first_name}
                onChange={(e) => setForm({ ...form, first_name: e.target.value })}
              />
            </Field>
            <Field label="Last name">
              <input
                className={inputClass}
                value={form.last_name}
                onChange={(e) => setForm({ ...form, last_name: e.target.value })}
              />
            </Field>
            <Field label="Display name">
              <input
                className={inputClass}
                value={form.display_name}
                onChange={(e) => setForm({ ...form, display_name: e.target.value })}
                required
              />
            </Field>
            <Field label="Employment type">
              <select
                className={inputClass}
                value={form.employment_type}
                onChange={(e) => setForm({ ...form, employment_type: e.target.value })}
              >
                {EMPLOYMENT_TYPES.map((t) => (
                  <option key={t} value={t}>
                    {t.replace(/_/g, ' ')}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Primary location">
              <select
                className={inputClass}
                value={form.primary_location_id}
                onChange={(e) => setForm({ ...form, primary_location_id: e.target.value })}
              >
                <option value="">—</option>
                {locations.map((l) => (
                  <option key={l.id} value={l.id}>
                    {l.name}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Default workspace">
              <select
                className={inputClass}
                value={form.workspace_ids[0] ?? ''}
                onChange={(e) =>
                  setForm({
                    ...form,
                    workspace_ids: e.target.value ? [e.target.value] : [],
                  })
                }
              >
                <option value="">—</option>
                {workspaces.map((w) => (
                  <option key={w.id} value={w.id}>
                    {w.name}
                  </option>
                ))}
              </select>
            </Field>
            <div className="flex gap-2">
              <Button type="submit">Save</Button>
              <Button type="button" variant="secondary" onClick={() => setShowForm(false)}>
                Cancel
              </Button>
            </div>
          </form>
        </Card>
      ) : null}

      <Card title="Team members">
        {providers.length === 0 ? <EmptyState message="No team members found." /> : null}
        <ul className="divide-y divide-zinc-100">
          {providers.map((provider) => (
            <li
              key={provider.id}
              className="flex flex-wrap items-center justify-between gap-2 py-3 text-sm"
            >
              <div>
                <Link
                  href={`/admin/staff/${provider.id}`}
                  className="font-medium hover:underline"
                >
                  {provider.display_name}
                </Link>
                <p className="text-xs text-zinc-500">
                  {provider.employment_type.replace(/_/g, ' ')} ·{' '}
                  {provider.primary_location?.name ?? 'No primary location'}
                </p>
              </div>
              <div className="flex flex-wrap items-center gap-2">
                <StatusBadge active={provider.is_active} />
                {provider.is_bookable ? (
                  <span className="rounded bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">
                    Bookable
                  </span>
                ) : (
                  <span className="rounded bg-zinc-100 px-2 py-0.5 text-xs text-zinc-600">
                    Not bookable
                  </span>
                )}
                <Link
                  href={`/admin/staff/${provider.id}?tab=availability`}
                  className="rounded-md border border-zinc-300 px-2 py-1 text-xs hover:bg-zinc-50"
                >
                  Availability
                </Link>
                <Button type="button" variant="secondary" onClick={() => startEdit(provider)}>
                  Edit
                </Button>
                <Button type="button" variant="secondary" onClick={() => toggleStatus(provider)}>
                  {provider.is_active ? 'Deactivate' : 'Activate'}
                </Button>
              </div>
            </li>
          ))}
        </ul>
      </Card>
    </AdminStaffShell>
  );
}
