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
import type { Location, Workspace } from '@/lib/identity-types';
import { WORKSPACE_TYPES } from '@/lib/identity-types';
import { fetchLocations } from '@/services/identity.service';
import {
  createWorkspace,
  fetchWorkspaces,
  setWorkspaceStatus,
  updateWorkspace,
} from '@/services/identity.service';

export default function WorkspacesSettingsPage() {
  const [workspaces, setWorkspaces] = useState<Workspace[]>([]);
  const [locations, setLocations] = useState<Location[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState({
    location_id: '',
    name: '',
    code: '',
    workspace_type: 'chair',
  });

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([fetchWorkspaces(), fetchLocations()])
      .then(([ws, locs]) => {
        setWorkspaces(ws);
        setLocations(locs);
        if (!form.location_id && locs[0]) {
          setForm((f) => ({ ...f, location_id: locs[0].id }));
        }
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, [form.location_id]);

  useEffect(() => {
    load();
  }, [load]);

  function startCreate() {
    setEditingId(null);
    setForm({
      location_id: locations[0]?.id ?? '',
      name: '',
      code: '',
      workspace_type: 'chair',
    });
    setShowForm(true);
  }

  function startEdit(workspace: Workspace) {
    setEditingId(workspace.id);
    setForm({
      location_id: workspace.location_id,
      name: workspace.name,
      code: workspace.code ?? '',
      workspace_type: workspace.workspace_type,
    });
    setShowForm(true);
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      if (editingId) {
        await updateWorkspace(editingId, form);
      } else {
        await createWorkspace(form);
      }
      setShowForm(false);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed');
    }
  }

  async function toggleStatus(workspace: Workspace) {
    try {
      await setWorkspaceStatus(workspace.id, !workspace.is_active);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Status update failed');
    }
  }

  const locationName = (id: string) =>
    locations.find((l) => l.id === id)?.name ?? id;

  return (
    <AdminSettingsShell title="Workspaces">
      <div className="space-y-4">
        <div className="flex justify-end">
          <Button type="button" onClick={startCreate} disabled={locations.length === 0}>
            Add workspace
          </Button>
        </div>
        {error ? <ErrorAlert message={error} /> : null}
        {showForm ? (
          <Card title={editingId ? 'Edit workspace' : 'New workspace'}>
            <form onSubmit={handleSubmit} className="grid max-w-xl gap-3">
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
              <Field label="Name">
                <input
                  className={inputClass}
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                  required
                />
              </Field>
              <Field label="Code">
                <input
                  className={inputClass}
                  value={form.code}
                  onChange={(e) => setForm({ ...form, code: e.target.value })}
                />
              </Field>
              <Field label="Type">
                <select
                  className={inputClass}
                  value={form.workspace_type}
                  onChange={(e) => setForm({ ...form, workspace_type: e.target.value })}
                >
                  {WORKSPACE_TYPES.map((t) => (
                    <option key={t} value={t}>
                      {t}
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
        <Card title="Chairs, rooms, stations & slots">
          {loading ? <LoadingState /> : null}
          {!loading && workspaces.length === 0 ? (
            <EmptyState message="No workspaces configured yet." />
          ) : null}
          <ul className="divide-y divide-zinc-100">
            {workspaces.map((ws) => (
              <li
                key={ws.id}
                className="flex flex-wrap items-center justify-between gap-2 py-3"
              >
                <div>
                  <p className="font-medium">
                    {ws.name}{' '}
                    <span className="text-xs font-normal text-zinc-500">({ws.workspace_type})</span>
                  </p>
                  <p className="text-xs text-zinc-500">
                    {locationName(ws.location_id)}
                    {ws.code ? ` · ${ws.code}` : ''}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <StatusBadge active={ws.is_active} />
                  <Button type="button" variant="secondary" onClick={() => startEdit(ws)}>
                    Edit
                  </Button>
                  <Button type="button" variant="secondary" onClick={() => toggleStatus(ws)}>
                    {ws.is_active ? 'Deactivate' : 'Activate'}
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
