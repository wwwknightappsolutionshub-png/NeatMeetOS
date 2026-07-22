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
import type { Location, TeamMember, Workspace } from '@/lib/identity-types';
import { EMPLOYMENT_TYPES } from '@/lib/identity-types';
import { fetchLocations, fetchWorkspaces } from '@/services/identity.service';
import {
  createTeamMember,
  fetchTeamMembers,
  setTeamMemberStatus,
  updateTeamMember,
} from '@/services/identity.service';

export default function TeamSettingsPage() {
  const [members, setMembers] = useState<TeamMember[]>([]);
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
    Promise.all([fetchTeamMembers(), fetchLocations(), fetchWorkspaces()])
      .then(([m, l, w]) => {
        setMembers(m);
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

  function startEdit(member: TeamMember) {
    setEditingId(member.id);
    setForm({
      email: member.email ?? '',
      first_name: member.first_name ?? '',
      last_name: member.last_name ?? '',
      display_name: member.display_name,
      phone: member.phone ?? '',
      employment_type: member.employment_type,
      primary_location_id: member.primary_location_id ?? '',
      workspace_ids: member.workspace_ids ?? [],
    });
    setShowForm(true);
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      if (editingId) {
        const { email: _e, ...updateData } = form;
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

  async function toggleStatus(member: TeamMember) {
    try {
      await setTeamMemberStatus(member.id, !member.is_active);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Status update failed');
    }
  }

  return (
    <AdminSettingsShell title="Team members">
      <div className="space-y-4">
        <div className="flex justify-end">
          <Button type="button" onClick={startCreate}>
            Add team member
          </Button>
        </div>
        {error ? <ErrorAlert message={error} /> : null}
        {showForm ? (
          <Card title={editingId ? 'Edit team member' : 'New team member'}>
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
              <Field label="Employment type">
                <select
                  className={inputClass}
                  value={form.employment_type}
                  onChange={(e) => setForm({ ...form, employment_type: e.target.value })}
                >
                  {EMPLOYMENT_TYPES.map((t) => (
                    <option key={t} value={t}>
                      {t.replace('_', ' ')}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Primary location">
                <select
                  className={inputClass}
                  value={form.primary_location_id}
                  onChange={(e) =>
                    setForm({ ...form, primary_location_id: e.target.value })
                  }
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
                      {w.name} ({w.workspace_type})
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
        <Card title="Team">
          {loading ? <LoadingState /> : null}
          {!loading && members.length === 0 ? (
            <EmptyState message="No team members yet." />
          ) : null}
          <ul className="divide-y divide-zinc-100">
            {members.map((member) => (
              <li
                key={member.id}
                className="flex flex-wrap items-center justify-between gap-2 py-3"
              >
                <div>
                  <p className="font-medium">{member.display_name}</p>
                  <p className="text-xs text-zinc-500">
                    {member.email} · {member.employment_type.replace('_', ' ')}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <StatusBadge active={member.is_active} />
                  <Button type="button" variant="secondary" onClick={() => startEdit(member)}>
                    Edit
                  </Button>
                  <Button type="button" variant="secondary" onClick={() => toggleStatus(member)}>
                    {member.is_active ? 'Deactivate' : 'Activate'}
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
