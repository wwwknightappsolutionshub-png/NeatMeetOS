'use client';

import { useCallback, useEffect, useState } from 'react';
import { ApiRequestError } from '@/lib/api-client';
import type { LookbookItem } from '@/lib/lookbook-types';
import { resolveMediaUrl } from '@/lib/media-url';
import { fetchPublicLookbookItems } from '@/services/lookbook.service';

export function PublicLookbookSection({ tenantSlug }: { tenantSlug: string }) {
  const [items, setItems] = useState<LookbookItem[]>([]);

  const load = useCallback(async () => {
    try {
      const rows = await fetchPublicLookbookItems(tenantSlug);
      setItems(rows.filter((i) => i.is_published !== false));
    } catch (e) {
      if (e instanceof ApiRequestError && (e.status === 403 || e.status === 404)) {
        setItems([]);
        return;
      }
      setItems([]);
    }
  }, [tenantSlug]);

  useEffect(() => {
    void load();
  }, [load]);

  if (items.length === 0) return null;

  return (
    <section id="lookbook" className="mt-12 scroll-mt-8">
      <div className="mb-5">
        <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#2f5a45]/80">
          Editorial
        </p>
        <h2 className="book-display mt-1 text-2xl font-bold">Lookbook</h2>
        <p className="mt-1 text-sm text-[var(--book-muted)]">
          Signature styles and seasonal inspiration.
        </p>
      </div>
      <div className="-mx-1 flex gap-4 overflow-x-auto px-1 pb-3 snap-x snap-mandatory">
        {items.map((item, index) => {
          const src = resolveMediaUrl(item.image_url) ?? item.image_url;
          const tall = index % 3 === 1;
          return (
            <article
              key={item.id}
              className={[
                'snap-start shrink-0 overflow-hidden border border-[var(--book-line)] bg-[var(--book-wash,#f7f5f1)]',
                tall ? 'w-[72vw] max-w-[280px] sm:w-[240px]' : 'w-[78vw] max-w-[320px] sm:w-[300px]',
              ].join(' ')}
            >
              <div className={tall ? 'aspect-[3/4]' : 'aspect-[4/5]'}>
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={src} alt={item.title || item.caption || ''} className="h-full w-full object-cover" />
              </div>
              <div className="border-t border-[var(--book-line)] px-3 py-3">
                {item.title ? (
                  <h3 className="font-serif text-lg font-semibold tracking-tight text-[var(--book-ink)]">
                    {item.title}
                  </h3>
                ) : null}
                {item.caption ? (
                  <p className="mt-1 text-sm leading-relaxed text-[var(--book-muted)]">{item.caption}</p>
                ) : null}
              </div>
            </article>
          );
        })}
      </div>
    </section>
  );
}
