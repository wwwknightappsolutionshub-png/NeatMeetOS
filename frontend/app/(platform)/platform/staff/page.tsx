'use client';

import { FormEvent, useCallback, useEffect, useState } from 'react';
import {
  PlatformButton,
  PlatformCard,
  PlatformField,
  PlatformPageIntro,
  platformInputClass,
} from '@/components/platform/ui';
import type { PlatformRoleSlug, PlatformStaffUser } from '@/services/platform.service';
import {
  createPlatformStaff,
  fetchPlatformStaff,
  revokePlatformStaff,
  updatePlatformStaff,
  updatePlatformStaffPassword,
} from '@/services/platform.service';

export default function PlatformStaffPage() {
  const [items, setItems] = useState<PlatformStaffUser[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState({
    name: '',
    email: '',
    password: '',
    platform_role: 'manager' as 'manager' | 'support',
  });
  const [creating, setCreating] = useState(false);
  const [passwordEdits, setPasswordEdits] = useState<
    Record<number, { password: string; password_confirmation: string }>
  >({});

  const load = useCallback(() => {
    setLoading(true);
    fetchPlatformStaff()
      .then((data) => setItems(data.items))
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load staff'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function handleCreate(e: FormEvent) {
    e.preventDefault();
    setCreating(true);
    setError(null);
    try {
      await createPlatformStaff(form);
      setForm({ name: '', email: '', password: '', platform_role: 'manager' });
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Create failed');
    } finally {
      setCreating(false);
    }
  }

  async function handleRoleChange(id: number, platform_role: PlatformRoleSlug) {
    setError(null);
    try {
      await updatePlatformStaff(id, { platform_role });
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Role update failed');
    }
  }

  async function handlePassword(id: number) {
    const edit = passwordEdits[id];
    if (!edit?.password) return;
    setError(null);
    try {
      await updatePlatformStaffPassword(id, edit);
      setPasswordEdits((prev) => {
        const next = { ...prev };
        delete next[id];
        return next;
      });
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Password reset failed');
    }
  }

  async function handleRevoke(id: number) {
    if (!window.confirm('Revoke platform access for this user?')) return;
    setError(null);
    try {
      await revokePlatformStaff(id);
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Revoke failed');
    }
  }

  return (
    <div className="mx-auto max-w-4xl space-y-5">
      <PlatformPageIntro
        title="Platform staff"
        description="Invite managers and support users with lower access. Only owners can manage this list."
      />

      {error ? (
        <div className="rounded-lg border border-red-500/40 bg-red-950/40 px-3 py-2 text-sm text-red-200">
          {error}
        </div>
      ) : null}

      <PlatformCard title="Add staff">
        <form onSubmit={handleCreate} className="grid gap-3 sm:grid-cols-2">
          <PlatformField label="Name">
            <input
              className={platformInputClass}
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              required
              minLength={2}
            />
          </PlatformField>
          <PlatformField label="Email">
            <input
              type="email"
              className={platformInputClass}
              value={form.email}
              onChange={(e) => setForm({ ...form, email: e.target.value })}
              required
            />
          </PlatformField>
          <PlatformField label="Temporary password">
            <input
              type="password"
              className={platformInputClass}
              value={form.password}
              onChange={(e) => setForm({ ...form, password: e.target.value })}
              required
              minLength={8}
            />
          </PlatformField>
          <PlatformField label="Role">
            <select
              className={platformInputClass}
              value={form.platform_role}
              onChange={(e) =>
                setForm({
                  ...form,
                  platform_role: e.target.value as 'manager' | 'support',
                })
              }
            >
              <option value="manager">Manager — operate tenants & campaigns</option>
              <option value="support">Support — read-only</option>
            </select>
          </PlatformField>
          <div className="sm:col-span-2">
            <PlatformButton type="submit" disabled={creating}>
              {creating ? 'Creating…' : 'Add staff member'}
            </PlatformButton>
          </div>
        </form>
      </PlatformCard>

      <PlatformCard title="Current staff" padded={false}>
        {loading ? (
          <p className="p-5 text-sm text-stone-400">Loading…</p>
        ) : items.length === 0 ? (
          <p className="p-5 text-sm text-stone-400">No platform staff yet.</p>
        ) : (
          <ul className="divide-y divide-white/10">
            {items.map((member) => {
              const pwd = passwordEdits[member.id] ?? {
                password: '',
                password_confirmation: '',
              };
              return (
                <li key={member.id} className="space-y-3 p-5">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p className="font-medium text-white">{member.name}</p>
                      <p className="text-sm text-stone-400">{member.email}</p>
                    </div>
                    <select
                      className={`${platformInputClass} w-auto min-w-[10rem]`}
                      value={member.platform_role ?? 'owner'}
                      onChange={(e) =>
                        void handleRoleChange(
                          member.id,
                          e.target.value as PlatformRoleSlug,
                        )
                      }
                    >
                      <option value="owner">Owner</option>
                      <option value="manager">Manager</option>
                      <option value="support">Support</option>
                    </select>
                  </div>
                  <div className="grid gap-2 sm:grid-cols-[1fr_1fr_auto_auto]">
                    <input
                      type="password"
                      className={platformInputClass}
                      placeholder="New password"
                      value={pwd.password}
                      onChange={(e) =>
                        setPasswordEdits((prev) => ({
                          ...prev,
                          [member.id]: {
                            ...pwd,
                            password: e.target.value,
                          },
                        }))
                      }
                    />
                    <input
                      type="password"
                      className={platformInputClass}
                      placeholder="Confirm password"
                      value={pwd.password_confirmation}
                      onChange={(e) =>
                        setPasswordEdits((prev) => ({
                          ...prev,
                          [member.id]: {
                            ...pwd,
                            password_confirmation: e.target.value,
                          },
                        }))
                      }
                    />
                    <PlatformButton
                      variant="secondary"
                      disabled={!pwd.password}
                      onClick={() => void handlePassword(member.id)}
                    >
                      Set password
                    </PlatformButton>
                    <PlatformButton
                      variant="secondary"
                      onClick={() => void handleRevoke(member.id)}
                    >
                      Revoke
                    </PlatformButton>
                  </div>
                </li>
              );
            })}
          </ul>
        )}
      </PlatformCard>
    </div>
  );
}
