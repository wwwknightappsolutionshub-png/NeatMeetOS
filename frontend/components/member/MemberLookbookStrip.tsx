'use client';

import type { LookbookItem } from '@/lib/lookbook-types';
import { resolveMediaUrl } from '@/lib/media-url';

export function MemberLookbookStrip({ items }: { items: LookbookItem[] }) {
  if (items.length === 0) return null;

  return (
    <section id="member-lookbook" className="scroll-mt-24 space-y-3">
      <div>
        <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-[var(--book-moss)]">
          Editorial
        </p>
        <h2 className="book-display mt-0.5 text-xl font-bold text-[var(--book-ink)]">Lookbook</h2>
        <p className="mt-1 text-sm text-[var(--book-muted)]">Signature styles from this salon.</p>
      </div>
      <div className="-mx-1 flex gap-3 overflow-x-auto px-1 pb-1 snap-x snap-mandatory">
        {items.map((item) => {
          const src = resolveMediaUrl(item.image_url) ?? item.image_url;
          return (
            <article
              key={item.id}
              className="snap-start w-[9.5rem] shrink-0 overflow-hidden rounded-2xl border border-[var(--book-line)] bg-[var(--book-wash)]"
            >
              <div className="aspect-[3/4]">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src={src}
                  alt={item.title || item.caption || ''}
                  className="h-full w-full object-cover"
                />
              </div>
              {(item.title || item.caption) && (
                <div className="border-t border-[var(--book-line)] px-2.5 py-2">
                  {item.title ? (
                    <p className="truncate text-xs font-semibold text-[var(--book-ink)]">{item.title}</p>
                  ) : null}
                  {item.caption ? (
                    <p className="mt-0.5 line-clamp-2 text-[11px] text-[var(--book-muted)]">
                      {item.caption}
                    </p>
                  ) : null}
                </div>
              )}
            </article>
          );
        })}
      </div>
    </section>
  );
}
