'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminModuleChrome } from '@/components/admin/AdminModuleChrome';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { LookbookItem } from '@/lib/lookbook-types';
import { resolveMediaUrl } from '@/lib/media-url';
import {
  fetchAdminLookbookItems,
  hideAdminLookbookItem,
  publishAdminLookbookItem,
  replaceAdminLookbookImage,
  updateAdminLookbookItem,
} from '@/services/lookbook.service';

export default function AdminLookbookPage() {
  const [items, setItems] = useState<LookbookItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editTitle, setEditTitle] = useState('');
  const [editCaption, setEditCaption] = useState('');
  const [replacingId, setReplacingId] = useState<string | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    fetchAdminLookbookItems()
      .then(setItems)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function saveEdit(item: LookbookItem) {
    setError(null);
    try {
      await updateAdminLookbookItem(item.id, {
        title: editTitle || null,
        caption: editCaption || null,
      });
      setEditingId(null);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed');
    }
  }

  async function toggleVisibility(item: LookbookItem) {
    setError(null);
    try {
      if (item.is_published) {
        await hideAdminLookbookItem(item.id);
      } else {
        await publishAdminLookbookItem(item.id);
      }
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Update failed');
    }
  }

  async function handleReplace(itemId: string, file: File | null) {
    if (!file) return;
    setReplacingId(itemId);
    setError(null);
    try {
      await replaceAdminLookbookImage(itemId, file);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Replace failed');
    } finally {
      setReplacingId(null);
    }
  }

  return (
    <AdminModuleChrome eyebrow="Lookbook" title="Editorial looks" links={[]}>
      {error ? <ErrorAlert message={error} /> : null}

      <Card title="Magazine layout">
        <p className="mb-4 text-sm text-zinc-600">
          Seeded looks ship with new boutiques. Edit titles and captions, hide or publish, and
          replace images — this stays editorial, not a square Instagram grid.
        </p>
        {loading ? <LoadingState /> : null}
        {!loading && items.length === 0 ? (
          <p className="text-sm text-zinc-500">No lookbook items yet.</p>
        ) : null}

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {items.map((item) => {
            const src = resolveMediaUrl(item.image_url) ?? item.image_url;
            return (
              <article
                key={item.id}
                className="flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white"
              >
                <div className="relative aspect-[4/5] w-full shrink-0 bg-zinc-100">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={src} alt={item.title || ''} className="h-full w-full object-cover" />
                  {!item.is_published ? (
                    <span className="absolute left-2 top-2 rounded bg-zinc-900/70 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
                      Hidden
                    </span>
                  ) : null}
                  {item.is_seeded ? (
                    <span className="absolute bottom-2 left-2 rounded bg-[#2f5a45]/90 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
                      Seeded
                    </span>
                  ) : null}
                </div>
                <div className="flex min-w-0 flex-1 flex-col gap-2 p-3">
                  {editingId === item.id ? (
                    <>
                      <Field label="Title">
                        <input
                          className={inputClass}
                          value={editTitle}
                          onChange={(e) => setEditTitle(e.target.value)}
                        />
                      </Field>
                      <Field label="Caption">
                        <textarea
                          className={inputClass}
                          rows={2}
                          value={editCaption}
                          onChange={(e) => setEditCaption(e.target.value)}
                        />
                      </Field>
                      <div className="flex flex-wrap gap-2">
                        <Button type="button" onClick={() => void saveEdit(item)}>
                          Save
                        </Button>
                        <Button type="button" variant="secondary" onClick={() => setEditingId(null)}>
                          Cancel
                        </Button>
                      </div>
                    </>
                  ) : (
                    <>
                      <div>
                        <h3 className="font-serif text-base font-semibold leading-snug text-zinc-900">
                          {item.title || 'Untitled look'}
                        </h3>
                        {item.caption ? (
                          <p className="mt-1 line-clamp-2 text-sm text-zinc-600">{item.caption}</p>
                        ) : (
                          <p className="mt-1 text-sm text-zinc-400">No caption</p>
                        )}
                        {item.category_key ? (
                          <p className="mt-1 text-[10px] uppercase tracking-wide text-zinc-400">
                            {item.category_key}
                          </p>
                        ) : null}
                      </div>
                      <div className="mt-auto flex flex-col gap-1.5">
                        <Button
                          type="button"
                          variant="secondary"
                          className="!w-full !px-2 !py-1.5 !text-xs"
                          onClick={() => {
                            setEditingId(item.id);
                            setEditTitle(item.title ?? '');
                            setEditCaption(item.caption ?? '');
                          }}
                        >
                          Edit copy
                        </Button>
                        <Button
                          type="button"
                          variant="secondary"
                          className="!w-full !px-2 !py-1.5 !text-xs"
                          onClick={() => void toggleVisibility(item)}
                        >
                          {item.is_published ? 'Hide' : 'Publish'}
                        </Button>
                        <label className="inline-flex w-full cursor-pointer items-center justify-center text-xs text-zinc-600">
                          <span className="w-full rounded-md border border-zinc-300 px-2 py-1.5 text-center hover:bg-zinc-50">
                            {replacingId === item.id ? 'Replacing…' : 'Replace image'}
                          </span>
                          <input
                            type="file"
                            accept="image/*"
                            className="sr-only"
                            disabled={replacingId === item.id}
                            onChange={(e) => {
                              void handleReplace(item.id, e.target.files?.[0] ?? null);
                              e.target.value = '';
                            }}
                          />
                        </label>
                      </div>
                    </>
                  )}
                </div>
              </article>
            );
          })}
        </div>
      </Card>
    </AdminModuleChrome>
  );
}
