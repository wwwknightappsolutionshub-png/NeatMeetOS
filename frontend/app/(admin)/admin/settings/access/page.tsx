'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminSettingsShell } from '@/components/admin/AdminSettingsShell';
import { EmptyState, ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { PermissionGroup, Role, TeamMember } from '@/lib/identity-types';
import {
  archiveRole,
  createRole,
  fetchPermissions,
  fetchRoles,
  fetchTeamMembers,
  updateRolePermissions,
  updateTeamMemberRoles,
} from '@/services/identity.service';

type Tab = 'members' | 'roles';

export default function AccessSettingsPage() {
  const [tab, setTab] = useState<Tab>('members');
  const [members, setMembers] = useState<TeamMember[]>([]);
  const [roles, setRoles] = useState<Role[]>([]);
  const [permissionGroups, setPermissionGroups] = useState<PermissionGroup[]>([]);
  const [selectedMemberId, setSelectedMemberId] = useState('');
  const [selectedRoleId, setSelectedRoleId] = useState('');
  const [memberRoleIds, setMemberRoleIds] = useState<string[]>([]);
  const [rolePermissionIds, setRolePermissionIds] = useState<string[]>([]);
  const [roleForm, setRoleForm] = useState({ name: '', slug: '' });
  const [showRoleForm, setShowRoleForm] = useState(false);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([fetchTeamMembers(), fetchRoles(), fetchPermissions()])
      .then(([m, r, p]) => {
        setMembers(m);
        setRoles(r.filter((role) => role.is_active !== false));
        setPermissionGroups(p);
        if (m[0] && !selectedMemberId) setSelectedMemberId(m[0].id);
        if (r[0] && !selectedRoleId) setSelectedRoleId(r[0].id);
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, [selectedMemberId, selectedRoleId]);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    const member = members.find((m) => m.id === selectedMemberId);
    setMemberRoleIds(member?.role_ids ?? []);
  }, [selectedMemberId, members]);

  useEffect(() => {
    const role = roles.find((r) => r.id === selectedRoleId);
    setRolePermissionIds(role?.permission_ids ?? []);
  }, [selectedRoleId, roles]);

  function toggleMemberRole(roleId: string) {
    setMemberRoleIds((prev) =>
      prev.includes(roleId) ? prev.filter((id) => id !== roleId) : [...prev, roleId],
    );
  }

  function toggleRolePermission(permissionId: string) {
    setRolePermissionIds((prev) =>
      prev.includes(permissionId)
        ? prev.filter((id) => id !== permissionId)
        : [...prev, permissionId],
    );
  }

  async function saveMemberRoles() {
    if (!selectedMemberId) return;
    setSaving(true);
    setError(null);
    setSaved(false);
    try {
      await updateTeamMemberRoles(selectedMemberId, memberRoleIds);
      setSaved(true);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  async function saveRolePermissions() {
    if (!selectedRoleId) return;
    setSaving(true);
    setError(null);
    setSaved(false);
    try {
      await updateRolePermissions(selectedRoleId, rolePermissionIds);
      setSaved(true);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  async function handleCreateRole(event: React.FormEvent) {
    event.preventDefault();
    setSaving(true);
    setError(null);
    try {
      await createRole({
        name: roleForm.name,
        slug: roleForm.slug || undefined,
        permission_ids: ['identity.view'],
      });
      setShowRoleForm(false);
      setRoleForm({ name: '', slug: '' });
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Create failed');
    } finally {
      setSaving(false);
    }
  }

  async function handleArchiveRole(role: Role) {
    if (role.is_system) return;
    try {
      await archiveRole(role.id);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Archive failed');
    }
  }

  const selectedRole = roles.find((r) => r.id === selectedRoleId);

  return (
    <AdminSettingsShell title="Access & roles">
      <div className="mb-4 flex gap-2">
        <Button
          type="button"
          variant={tab === 'members' ? 'primary' : 'secondary'}
          onClick={() => setTab('members')}
        >
          Team assignments
        </Button>
        <Button
          type="button"
          variant={tab === 'roles' ? 'primary' : 'secondary'}
          onClick={() => setTab('roles')}
        >
          Roles & permissions
        </Button>
      </div>
      {error ? <ErrorAlert message={error} /> : null}
      {loading ? <LoadingState /> : null}

      {tab === 'members' ? (
        <div className="grid gap-4 md:grid-cols-2">
          <Card title="Team member">
            {members.length === 0 ? (
              <EmptyState message="Add team members first." />
            ) : (
              <Field label="Select member">
                <select
                  className={inputClass}
                  value={selectedMemberId}
                  onChange={(e) => setSelectedMemberId(e.target.value)}
                >
                  {members.map((m) => (
                    <option key={m.id} value={m.id}>
                      {m.display_name}
                    </option>
                  ))}
                </select>
              </Field>
            )}
          </Card>
          <Card title="Assigned roles">
            <ul className="space-y-2">
              {roles.map((role) => (
                <li key={role.id} className="flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={memberRoleIds.includes(role.id)}
                    onChange={() => toggleMemberRole(role.id)}
                    id={`member-role-${role.id}`}
                  />
                  <label htmlFor={`member-role-${role.id}`}>
                    {role.name} <span className="text-zinc-500">({role.slug})</span>
                  </label>
                </li>
              ))}
            </ul>
            <div className="mt-4 flex items-center gap-3">
              <Button
                type="button"
                onClick={saveMemberRoles}
                disabled={saving || !selectedMemberId}
              >
                {saving ? 'Saving…' : 'Save assignments'}
              </Button>
              {saved ? <span className="text-sm text-emerald-600">Saved</span> : null}
            </div>
          </Card>
        </div>
      ) : (
        <div className="space-y-4">
          <div className="flex justify-end">
            <Button type="button" onClick={() => setShowRoleForm(true)}>
              Create role
            </Button>
          </div>
          {showRoleForm ? (
            <Card title="New role">
              <form onSubmit={handleCreateRole} className="grid max-w-md gap-3">
                <Field label="Name">
                  <input
                    className={inputClass}
                    value={roleForm.name}
                    onChange={(e) => setRoleForm({ ...roleForm, name: e.target.value })}
                    required
                  />
                </Field>
                <Field label="Slug (optional)">
                  <input
                    className={inputClass}
                    value={roleForm.slug}
                    onChange={(e) => setRoleForm({ ...roleForm, slug: e.target.value })}
                  />
                </Field>
                <div className="flex gap-2">
                  <Button type="submit" disabled={saving}>
                    Create
                  </Button>
                  <Button
                    type="button"
                    variant="secondary"
                    onClick={() => setShowRoleForm(false)}
                  >
                    Cancel
                  </Button>
                </div>
              </form>
            </Card>
          ) : null}
          <div className="grid gap-4 md:grid-cols-2">
            <Card title="Roles">
              <ul className="divide-y divide-zinc-100">
                {roles.map((role) => (
                  <li
                    key={role.id}
                    className={`flex items-center justify-between py-2 text-sm ${
                      selectedRoleId === role.id ? 'font-medium' : ''
                    }`}
                  >
                    <button
                      type="button"
                      className="text-left"
                      onClick={() => setSelectedRoleId(role.id)}
                    >
                      {role.name}{' '}
                      <span className="text-zinc-500">
                        ({role.slug}
                        {role.is_system ? ', system' : ''})
                      </span>
                      {role.team_member_count != null ? (
                        <span className="block text-xs text-zinc-400">
                          {role.team_member_count} member(s)
                        </span>
                      ) : null}
                    </button>
                    {!role.is_system ? (
                      <Button
                        type="button"
                        variant="secondary"
                        onClick={() => handleArchiveRole(role)}
                      >
                        Archive
                      </Button>
                    ) : null}
                  </li>
                ))}
              </ul>
            </Card>
            <Card title={`Permissions${selectedRole ? `: ${selectedRole.name}` : ''}`}>
              {permissionGroups.map((group) => (
                <div key={group.module} className="mb-4">
                  <p className="mb-1 text-xs font-medium uppercase text-zinc-500">
                    {group.module}
                  </p>
                  <ul className="space-y-1">
                    {group.permissions.map((perm) => (
                      <li key={perm.id} className="flex items-center gap-2 text-sm">
                        <input
                          type="checkbox"
                          checked={rolePermissionIds.includes(perm.id)}
                          onChange={() => toggleRolePermission(perm.id)}
                          id={`perm-${perm.id}`}
                        />
                        <label htmlFor={`perm-${perm.id}`}>{perm.name}</label>
                      </li>
                    ))}
                  </ul>
                </div>
              ))}
              <div className="mt-4 flex items-center gap-3">
                <Button
                  type="button"
                  onClick={saveRolePermissions}
                  disabled={saving || !selectedRoleId}
                >
                  {saving ? 'Saving…' : 'Save permissions'}
                </Button>
                {saved ? <span className="text-sm text-emerald-600">Saved</span> : null}
              </div>
            </Card>
          </div>
        </div>
      )}
    </AdminSettingsShell>
  );
}
