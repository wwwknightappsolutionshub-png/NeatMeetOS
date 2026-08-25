'use client';

import { useRef, useState } from 'react';
import { resolveMediaUrl } from '@/lib/media-url';
import type { MemberLook } from '@/services/member-portal.service';

const MAX_LOOKS = 4;

export function MemberLooksGallery({
  looks,
  busy,
  onUpload,
  onDelete,
}: {
  looks: MemberLook[];
  busy: boolean;
  onUpload: (file: File) => Promise<void>;
  onDelete: (id: string) => Promise<void>;
}) {
  const inputRef = useRef<HTMLInputElement | null>(null);
  const [localError, setLocalError] = useState<string | null>(null);
  const slots = Array.from({ length: MAX_LOOKS }, (_, i) => looks[i] ?? null);

  async function handleFile(file: File | null) {
    if (!file) return;
    setLocalError(null);
    try {
      await onUpload(file);
    } catch (err) {
      setLocalError(err instanceof Error ? err.message : 'Could not upload look');
    }
  }

  return (
    <section className="space-y-3">
      <div className="flex items-start justify-between gap-2">
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-[var(--book-moss)]">
            Your style
          </p>
          <h2 className="book-display mt-0.5 text-xl font-bold text-[var(--book-ink)]">My looks</h2>
          <p className="mt-1 text-sm text-[var(--book-muted)]">
            Save up to {MAX_LOOKS} photos of your last looks.
          </p>
        </div>
        <span className="shrink-0 rounded-full bg-[var(--book-wash)] px-2.5 py-1 text-xs font-semibold text-[var(--book-muted)]">
          {looks.length}/{MAX_LOOKS}
        </span>
      </div>

      {localError ? (
        <p className="mb-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
          {localError}
        </p>
      ) : null}

      <input
        ref={inputRef}
        type="file"
        accept="image/*"
        className="hidden"
        onChange={(e) => {
          const file = e.target.files?.[0] ?? null;
          e.target.value = '';
          void handleFile(file);
        }}
      />

      <div className="grid grid-cols-2 gap-2.5">
        {slots.map((look, index) => {
          if (look) {
            const src = resolveMediaUrl(look.image_url) ?? look.image_url;
            return (
              <div
                key={look.id}
                className="relative aspect-square overflow-hidden rounded-2xl border border-[var(--book-line)] bg-[var(--book-wash)]"
              >
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={src} alt={look.caption || `Look ${index + 1}`} className="h-full w-full object-cover" />
                <button
                  type="button"
                  disabled={busy}
                  className="absolute right-1.5 top-1.5 rounded-lg bg-black/55 px-2 py-1 text-[10px] font-semibold text-white disabled:opacity-50"
                  onClick={() => {
                    void onDelete(look.id).catch((err) => {
                      setLocalError(err instanceof Error ? err.message : 'Could not delete');
                    });
                  }}
                >
                  Remove
                </button>
              </div>
            );
          }

          return (
            <button
              key={`empty-${index}`}
              type="button"
              disabled={busy || looks.length >= MAX_LOOKS}
              className="flex aspect-square flex-col items-center justify-center gap-1 rounded-2xl border border-dashed border-[var(--book-line)] bg-[var(--book-wash)]/70 text-sm font-semibold text-[var(--book-muted)] transition hover:border-[var(--book-moss)] hover:text-[var(--book-moss)] disabled:opacity-50"
              onClick={() => inputRef.current?.click()}
            >
              <span className="text-xl leading-none">+</span>
              Add look
            </button>
          );
        })}
      </div>
    </section>
  );
}
