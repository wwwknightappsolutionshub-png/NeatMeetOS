'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminModuleChrome } from '@/components/admin/AdminModuleChrome';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { GalleryWork } from '@/lib/gallery-types';
import { resolveMediaUrl } from '@/lib/media-url';
import {
  deleteAdminGalleryWork,
  fetchAdminGalleryWorks,
  updateAdminGalleryWork,
  uploadAdminGalleryImage,
} from '@/services/gallery.service';

export default function AdminGalleryPage() {
  const [works, setWorks] = useState<GalleryWork[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [toast, setToast] = useState<string | null>(null);
  const [uploading, setUploading] = useState(false);
  const [caption, setCaption] = useState('');
  const [serviceTag, setServiceTag] = useState('');
  const [publishOnUpload, setPublishOnUpload] = useState(true);
  const [lightbox, setLightbox] = useState<GalleryWork | null>(null);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editCaption, setEditCaption] = useState('');
  const [editTag, setEditTag] = useState('');

  const showToast = useCallback((message: string) => {
    setToast(message);
  }, []);

  const load = useCallback(() => {
    setLoading(true);
    fetchAdminGalleryWorks()
      .then(setWorks)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
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

  async function handleUpload(file: File | null) {
    if (!file) return;
    setUploading(true);
    setError(null);
    try {
      await uploadAdminGalleryImage({
        image: file,
        caption: caption || undefined,
        service_tag: serviceTag || undefined,
        is_published: publishOnUpload,
      });
      setCaption('');
      setServiceTag('');
      showToast(publishOnUpload ? 'Work uploaded and published.' : 'Work uploaded as hidden.');
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Upload failed');
    } finally {
      setUploading(false);
    }
  }

  async function togglePublish(work: GalleryWork) {
    setError(null);
    const nextPublished = !work.is_published;
    try {
      await updateAdminGalleryWork(work.id, { is_published: nextPublished });
      showToast(nextPublished ? 'Published — now visible on public surfaces.' : 'Unpublished — hidden from public surfaces.');
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Update failed');
    }
  }

  async function saveEdit(work: GalleryWork) {
    setError(null);
    try {
      await updateAdminGalleryWork(work.id, {
        caption: editCaption || null,
        service_tag: editTag || null,
      });
      setEditingId(null);
      showToast('Gallery work updated.');
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed');
    }
  }

  async function handleDelete(id: string) {
    if (!window.confirm('Delete this gallery work?')) return;
    setError(null);
    try {
      await deleteAdminGalleryWork(id);
      if (lightbox?.id === id) setLightbox(null);
      showToast('Gallery work deleted.');
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Delete failed');
    }
  }

  return (
    <AdminModuleChrome eyebrow="Gallery" title="Works" links={[]}>
      {error ? <ErrorAlert message={error} /> : null}
      {toast ? (
        <div
          role="status"
          aria-live="polite"
          className="fixed bottom-6 right-6 z-[60] max-w-sm rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 shadow-lg"
        >
          {toast}
        </div>
      ) : null}

      <Card title="Upload work">
        <div className="grid max-w-xl gap-3">
          <Field label="Image">
            <input
              type="file"
              accept="image/*"
              className={inputClass}
              disabled={uploading}
              onChange={(e) => void handleUpload(e.target.files?.[0] ?? null)}
            />
          </Field>
          <Field label="Caption">
            <input
              className={inputClass}
              value={caption}
              onChange={(e) => setCaption(e.target.value)}
              placeholder="Optional caption"
            />
          </Field>
          <Field label="Service tag">
            <input
              className={inputClass}
              value={serviceTag}
              onChange={(e) => setServiceTag(e.target.value)}
              placeholder="e.g. Balayage"
            />
          </Field>
          <label className="flex items-center gap-2 text-sm text-zinc-700">
            <input
              type="checkbox"
              checked={publishOnUpload}
              onChange={(e) => setPublishOnUpload(e.target.checked)}
            />
            Publish immediately
          </label>
          {uploading ? <p className="text-xs text-zinc-500">Uploading…</p> : null}
        </div>
      </Card>

      <Card title="Instagram grid" className="mt-4">
        {loading ? <LoadingState /> : null}
        {!loading && works.length === 0 ? (
          <p className="text-sm text-zinc-500">No gallery works yet. Upload your first square.</p>
        ) : null}
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
          {works.map((work) => {
            const src = resolveMediaUrl(work.image_url) ?? work.image_url;
            return (
              <div
                key={work.id}
                className="overflow-hidden rounded-lg border border-zinc-200 bg-white"
              >
                <button
                  type="button"
                  className="relative aspect-square w-full overflow-hidden bg-zinc-100"
                  onClick={() => setLightbox(work)}
                >
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={src} alt={work.caption || ''} className="h-full w-full object-cover" />
                  {!work.is_published ? (
                    <span className="absolute left-2 top-2 rounded bg-zinc-900/70 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
                      Hidden
                    </span>
                  ) : null}
                </button>
                <div className="space-y-2 p-2">
                  {editingId === work.id ? (
                    <>
                      <input
                        className={inputClass}
                        value={editCaption}
                        onChange={(e) => setEditCaption(e.target.value)}
                        placeholder="Caption"
                      />
                      <input
                        className={inputClass}
                        value={editTag}
                        onChange={(e) => setEditTag(e.target.value)}
                        placeholder="Service tag"
                      />
                      <div className="flex gap-1">
                        <Button type="button" onClick={() => void saveEdit(work)}>
                          Save
                        </Button>
                        <Button type="button" variant="secondary" onClick={() => setEditingId(null)}>
                          Cancel
                        </Button>
                      </div>
                    </>
                  ) : (
                    <>
                      <p className="line-clamp-2 text-xs text-zinc-600">
                        {work.caption || 'No caption'}
                        {work.service_tag ? ` · ${work.service_tag}` : ''}
                      </p>
                      <div className="flex flex-wrap gap-1">
                        <Button
                          type="button"
                          variant="secondary"
                          onClick={() => {
                            setEditingId(work.id);
                            setEditCaption(work.caption ?? '');
                            setEditTag(work.service_tag ?? '');
                          }}
                        >
                          Edit
                        </Button>
                        <Button
                          type="button"
                          variant="secondary"
                          onClick={() => void togglePublish(work)}
                        >
                          {work.is_published ? 'Unpublish' : 'Publish'}
                        </Button>
                        <Button
                          type="button"
                          variant="secondary"
                          onClick={() => void handleDelete(work.id)}
                        >
                          Delete
                        </Button>
                      </div>
                    </>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      </Card>

      {lightbox ? (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4"
          role="dialog"
          aria-modal="true"
          onClick={() => setLightbox(null)}
        >
          <div
            className="max-h-[90vh] max-w-2xl overflow-hidden rounded-lg bg-white p-3"
            onClick={(e) => e.stopPropagation()}
          >
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              src={resolveMediaUrl(lightbox.image_url) ?? lightbox.image_url}
              alt={lightbox.caption || ''}
              className="max-h-[70vh] w-full object-contain"
            />
            <p className="mt-2 text-sm text-zinc-700">
              {lightbox.caption || 'Untitled'}
              {lightbox.service_tag ? ` · ${lightbox.service_tag}` : ''}
            </p>
            <Button type="button" className="mt-2" onClick={() => setLightbox(null)}>
              Close
            </Button>
          </div>
        </div>
      ) : null}
    </AdminModuleChrome>
  );
}
