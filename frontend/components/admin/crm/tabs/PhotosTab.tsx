'use client';

import { useCallback, useEffect, useState } from 'react';
import { EmptyState, Field, inputClass } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { ClientPhoto } from '@/lib/crm-types';
import { PHOTO_CATEGORIES } from '@/lib/crm-types';
import { archiveClientPhoto, fetchClientPhotos, registerClientPhoto } from '@/services/crm.service';

interface PhotosTabProps {
  clientId: string;
  onChanged: () => void;
}

export function PhotosTab({ clientId, onChanged }: PhotosTabProps) {
  const [photos, setPhotos] = useState<ClientPhoto[]>([]);
  const [form, setForm] = useState({ storage_path: '', category: 'reference', caption: '' });
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    fetchClientPhotos(clientId).then(setPhotos).catch(() => setPhotos([]));
  }, [clientId]);

  useEffect(() => {
    load();
  }, [load]);

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await registerClientPhoto(clientId, form);
      setForm({ storage_path: '', category: 'reference', caption: '' });
      load();
      onChanged();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Register failed');
    }
  }

  return (
    <div className="grid gap-4 md:grid-cols-2">
      <Card title="Register photo">
        <p className="mb-3 text-xs text-zinc-500">
          Enter a storage path or URL. Full file upload pipeline is deferred.
        </p>
        {error ? <p className="mb-2 text-sm text-red-600">{error}</p> : null}
        <form onSubmit={handleSubmit} className="grid gap-3">
          <Field label="Storage path / URL">
            <input className={inputClass} value={form.storage_path} onChange={(e) => setForm({ ...form, storage_path: e.target.value })} required placeholder="/storage/… or https://…" />
          </Field>
          <Field label="Category">
            <select className={inputClass} value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })}>
              {PHOTO_CATEGORIES.map((c) => (
                <option key={c} value={c}>{c}</option>
              ))}
            </select>
          </Field>
          <Field label="Caption">
            <input className={inputClass} value={form.caption} onChange={(e) => setForm({ ...form, caption: e.target.value })} />
          </Field>
          <Button type="submit">Register photo</Button>
        </form>
      </Card>
      <Card title="Gallery">
        {photos.length === 0 ? <EmptyState message="No photos yet." /> : null}
        <ul className="divide-y divide-zinc-100">
          {photos.map((p) => (
            <li key={p.id} className="flex items-start justify-between gap-2 py-3 text-sm">
              <div>
                <p className="font-medium">{p.caption ?? p.category}</p>
                <p className="break-all text-xs text-zinc-500">{p.storage_path}</p>
                <p className="text-xs text-zinc-400">{p.uploaded_by_name ?? 'Staff'}</p>
              </div>
              <Button type="button" variant="secondary" onClick={async () => { await archiveClientPhoto(clientId, p.id); load(); onChanged(); }}>Archive</Button>
            </li>
          ))}
        </ul>
      </Card>
    </div>
  );
}
