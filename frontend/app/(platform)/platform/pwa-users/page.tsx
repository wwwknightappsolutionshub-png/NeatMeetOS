'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { ErrorAlert } from '@/components/admin/ui';
import {
  PlatformButton,
  PlatformCard,
  PlatformField,
  PlatformPageIntro,
  platformInputClass,
} from '@/components/platform/ui';
import type { PlatformPwaUserRow } from '@/lib/types';
import {
  fetchPlatformPwaUsers,
  pushPlatformPwaUsers,
} from '@/services/platform.service';

function formatSeen(iso: string | null): string {
  if (!iso) return '—';
  try {
    return new Date(iso).toLocaleString(undefined, {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch {
    return iso;
  }
}

export default function PlatformPwaUsersPage() {
  const [rows, setRows] = useState<PlatformPwaUserRow[]>([]);
  const [type, setType] = useState<'all' | 'admin' | 'member'>('all');
  const [selected, setSelected] = useState<Record<string, boolean>>({});
  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [url, setUrl] = useState('/admin/dashboard');
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setRows(await fetchPlatformPwaUsers(type === 'all' ? undefined : type));
      setSelected({});
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load PWA users');
    } finally {
      setLoading(false);
    }
  }, [type]);

  useEffect(() => {
    void load();
  }, [load]);

  const selectedIds = useMemo(
    () => Object.entries(selected).filter(([, v]) => v).map(([id]) => id),
    [selected],
  );

  async function handlePush(allMatching: boolean) {
    setSending(true);
    setError(null);
    setNotice(null);
    try {
      const result = await pushPlatformPwaUsers({
        title: title.trim(),
        body: body.trim(),
        url: url.trim() || null,
        type: type === 'all' ? null : type,
        subscription_ids: allMatching ? undefined : selectedIds,
      });
      setNotice(
        `Push targeted ${result.targeted}: ${result.sent} sent, ${result.failed} failed, ${result.skipped} skipped.`,
      );
      setTitle('');
      setBody('');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Push failed');
    } finally {
      setSending(false);
    }
  }

  return (
    <div className="mx-auto grid max-w-5xl gap-5">
      <PlatformPageIntro
        title="PWA users"
        description="Installed admin workspace and member app subscribers across all tenants. Send a push to selected devices or everyone in the current filter."
      />

      <PlatformCard>
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
          <div className="sm:w-56">
            <PlatformField label="Audience">
              <select
                value={type}
                onChange={(e) => setType(e.target.value as 'all' | 'admin' | 'member')}
                className={platformInputClass}
              >
                <option value="all">All PWA users</option>
                <option value="admin">Admin workspace</option>
                <option value="member">Member apps</option>
              </select>
            </PlatformField>
          </div>
          <PlatformButton onClick={() => void load()}>Refresh</PlatformButton>
        </div>
      </PlatformCard>

      <PlatformCard title="Compose push">
        <div className="space-y-3 text-sm">
          <PlatformField label="Title">
            <input
              className={platformInputClass}
              placeholder="Push title"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              maxLength={160}
            />
          </PlatformField>
          <PlatformField label="Message body">
            <textarea
              className={platformInputClass}
              rows={3}
              placeholder="Message body"
              value={body}
              onChange={(e) => setBody(e.target.value)}
              maxLength={4000}
            />
          </PlatformField>
          <PlatformField label="Open URL (optional)">
            <input
              className={platformInputClass}
              placeholder="/admin/dashboard"
              value={url}
              onChange={(e) => setUrl(e.target.value)}
            />
          </PlatformField>
          <div className="flex flex-wrap gap-2 pt-1">
            <PlatformButton
              disabled={sending || !title.trim() || !body.trim() || selectedIds.length === 0}
              onClick={() => void handlePush(false)}
            >
              {sending ? 'Sending…' : `Push selected (${selectedIds.length})`}
            </PlatformButton>
            <PlatformButton
              variant="secondary"
              disabled={sending || !title.trim() || !body.trim() || rows.length === 0}
              onClick={() => void handlePush(true)}
            >
              Push all in filter ({rows.length})
            </PlatformButton>
          </div>
        </div>
      </PlatformCard>

      {error ? <ErrorAlert message={error} /> : null}
      {notice ? (
        <div className="rounded-lg border border-emerald-400/40 bg-emerald-500/15 px-4 py-3 text-sm text-emerald-50">
          {notice}
        </div>
      ) : null}

      {loading ? <p className="text-sm text-stone-300">Loading PWA users…</p> : null}

      {!loading ? (
        <PlatformCard padded={false}>
          {rows.length === 0 ? (
            <p className="px-5 py-8 text-center text-sm text-stone-300">No PWA subscriptions yet.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="min-w-full text-left text-sm">
                <thead className="border-b border-[var(--platform-line)] text-[11px] uppercase tracking-[0.12em] text-stone-300">
                  <tr>
                    <th className="px-4 py-3" />
                    <th className="px-4 py-3 font-semibold">Type</th>
                    <th className="px-4 py-3 font-semibold">User</th>
                    <th className="px-4 py-3 font-semibold">Tenant</th>
                    <th className="px-4 py-3 font-semibold">Last seen</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row) => (
                    <tr
                      key={`${row.type}-${row.id}`}
                      className="border-b border-white/5 last:border-0"
                    >
                      <td className="px-4 py-3">
                        <input
                          type="checkbox"
                          className="h-4 w-4 rounded border-stone-500"
                          checked={Boolean(selected[row.id])}
                          onChange={(e) =>
                            setSelected((prev) => ({ ...prev, [row.id]: e.target.checked }))
                          }
                        />
                      </td>
                      <td className="px-4 py-3 capitalize text-stone-200">{row.type}</td>
                      <td className="px-4 py-3">
                        <p className="font-medium text-white">{row.display_name ?? '—'}</p>
                        <p className="text-xs text-stone-400">{row.email ?? ''}</p>
                      </td>
                      <td className="px-4 py-3 text-stone-200">
                        {row.tenant_name ?? '—'}
                        {row.tenant_slug ? (
                          <span className="block text-xs text-stone-400">{row.tenant_slug}</span>
                        ) : null}
                      </td>
                      <td className="px-4 py-3 text-stone-300">{formatSeen(row.last_seen_at)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </PlatformCard>
      ) : null}
    </div>
  );
}
