'use client';

import { useCallback, useEffect, useState } from 'react';
import { ApiRequestError } from '@/lib/api-client';
import type { GalleryWork } from '@/lib/gallery-types';
import { resolveMediaUrl } from '@/lib/media-url';
import { fetchPublicGalleryWorks } from '@/services/gallery.service';

export function PublicGallerySection({ tenantSlug }: { tenantSlug: string }) {
  const [works, setWorks] = useState<GalleryWork[]>([]);
  const [lightbox, setLightbox] = useState<GalleryWork | null>(null);

  const load = useCallback(async () => {
    try {
      const items = await fetchPublicGalleryWorks(tenantSlug);
      setWorks(items.filter((w) => w.is_published !== false));
    } catch (e) {
      if (e instanceof ApiRequestError && (e.status === 403 || e.status === 404)) {
        setWorks([]);
        return;
      }
      setWorks([]);
    }
  }, [tenantSlug]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    if (!lightbox) return;
    const onKey = (ev: KeyboardEvent) => {
      if (ev.key === 'Escape') setLightbox(null);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [lightbox]);

  if (works.length === 0) return null;

  return (
    <section id="gallery" className="mt-12 scroll-mt-8">
      <div className="mb-4">
        <h2 className="book-display text-2xl font-bold">Gallery</h2>
        <p className="mt-1 text-sm text-[var(--book-muted)]">Recent work from the chair.</p>
      </div>
      <div className="grid grid-cols-2 gap-1.5 sm:grid-cols-3 md:grid-cols-4">
        {works.map((work) => {
          const src = resolveMediaUrl(work.image_url) ?? work.image_url;
          return (
            <button
              key={work.id}
              type="button"
              className="group relative aspect-square overflow-hidden bg-stone-200"
              onClick={() => setLightbox(work)}
              aria-label={work.caption || work.service_tag || 'Open gallery image'}
            >
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src={src}
                alt={work.caption || ''}
                className="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
              />
            </button>
          );
        })}
      </div>

      {lightbox ? (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
          role="dialog"
          aria-modal="true"
          aria-label="Gallery image"
          onClick={() => setLightbox(null)}
        >
          <button
            type="button"
            className="absolute right-4 top-4 rounded-full bg-white/10 px-3 py-1.5 text-sm font-semibold text-white hover:bg-white/20"
            onClick={() => setLightbox(null)}
          >
            Close
          </button>
          <div
            className="max-h-[90vh] max-w-3xl overflow-hidden"
            onClick={(e) => e.stopPropagation()}
          >
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              src={resolveMediaUrl(lightbox.image_url) ?? lightbox.image_url}
              alt={lightbox.caption || ''}
              className="max-h-[80vh] w-full object-contain"
            />
            {(lightbox.caption || lightbox.service_tag) && (
              <p className="mt-3 text-center text-sm text-white/90">
                {lightbox.caption}
                {lightbox.service_tag ? (
                  <span className="text-white/60">
                    {lightbox.caption ? ' · ' : ''}
                    {lightbox.service_tag}
                  </span>
                ) : null}
              </p>
            )}
          </div>
        </div>
      ) : null}
    </section>
  );
}
