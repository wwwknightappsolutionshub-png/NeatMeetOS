'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminModuleChrome } from '@/components/admin/AdminModuleChrome';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { resolveMediaUrl } from '@/lib/media-url';
import type { AdminAiHairstyleSession } from '@/services/ai-hairstyle-admin.service';
import {
  acceptAdminAiHairstyleSession,
  declineAdminAiHairstyleSession,
  fetchAdminAiHairstyleQueue,
} from '@/services/ai-hairstyle-admin.service';

export default function AdminAiHairstylePage() {
  const [items, setItems] = useState<AdminAiHairstyleSession[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [toast, setToast] = useState<string | null>(null);
  const [actingId, setActingId] = useState<string | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    setError(null);
    fetchAdminAiHairstyleQueue()
      .then(setItems)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load approved looks'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    if (!toast) return;
    const id = window.setTimeout(() => setToast(null), 2800);
    return () => window.clearTimeout(id);
  }, [toast]);

  async function accept(session: AdminAiHairstyleSession) {
    setActingId(session.id);
    setError(null);
    try {
      await acceptAdminAiHairstyleSession(session.id);
      setToast('Look accepted — customer notified by email and inbox.');
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Accept failed');
    } finally {
      setActingId(null);
    }
  }

  async function decline(session: AdminAiHairstyleSession) {
    setActingId(session.id);
    setError(null);
    try {
      await declineAdminAiHairstyleSession(session.id);
      setToast('Look declined — customer notified by email and inbox.');
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Decline failed');
    } finally {
      setActingId(null);
    }
  }

  return (
    <AdminModuleChrome eyebrow="Premium" title="Approved Looks" links={[]}>
      <p className="mb-4 max-w-2xl text-sm text-zinc-600">
        Customer-approved AI looks waiting for your review. Only composite previews are shown —
        original selfies are never stored or displayed.
      </p>

      {error ? <ErrorAlert message={error} /> : null}
      {toast ? (
        <p className="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
          {toast}
        </p>
      ) : null}

      {loading ? (
        <LoadingState label="Loading approved looks…" />
      ) : items.length === 0 ? (
        <Card className="p-6">
          <p className="text-sm text-zinc-600">No looks awaiting review right now.</p>
        </Card>
      ) : (
        <ul className="space-y-4">
          {items.map((session) => {
            const name =
              session.client?.display_name ||
              [session.contact?.first_name, session.contact?.last_name].filter(Boolean).join(' ') ||
              'Customer';
            const email = session.client?.email || session.contact?.email;
            const notes = session.contact?.notes;
            const busy = actingId === session.id;
            return (
              <li key={session.id}>
                <Card className="overflow-hidden p-0">
                  <div className="flex flex-col gap-4 p-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0">
                      <p className="text-sm font-semibold text-zinc-900">{name}</p>
                      <p className="mt-0.5 text-xs text-zinc-500">
                        {email || 'No email'}
                        {session.submitted_at
                          ? ` · Submitted ${new Date(session.submitted_at).toLocaleString()}`
                          : null}
                      </p>
                      {notes ? (
                        <p className="mt-2 text-sm text-zinc-600">Notes: {notes}</p>
                      ) : null}
                    </div>
                    <div className="flex shrink-0 flex-wrap gap-2">
                      <Button
                        type="button"
                        variant="secondary"
                        disabled={busy}
                        onClick={() => void decline(session)}
                      >
                        {busy ? 'Working…' : 'Decline'}
                      </Button>
                      <Button type="button" disabled={busy} onClick={() => void accept(session)}>
                        {busy ? 'Working…' : 'Accept look'}
                      </Button>
                    </div>
                  </div>
                  <div className="grid grid-cols-2 gap-2 border-t border-zinc-100 bg-zinc-50 p-3 sm:grid-cols-4">
                    {session.selected_previews.map((preview) => (
                      <figure key={preview.id} className="overflow-hidden rounded-lg bg-white">
                        {/* eslint-disable-next-line @next/next/no-img-element */}
                        <img
                          src={resolveMediaUrl(preview.composite_image_url) || ''}
                          alt={preview.style_label ?? 'Selected look'}
                          className="aspect-[4/5] w-full object-cover"
                        />
                        <figcaption className="px-2 py-1.5 text-xs font-medium text-zinc-700">
                          {preview.style_label ?? 'Look'}
                        </figcaption>
                      </figure>
                    ))}
                  </div>
                </Card>
              </li>
            );
          })}
        </ul>
      )}
    </AdminModuleChrome>
  );
}
