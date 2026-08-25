'use client';

import { useRef, useState } from 'react';
import { MemberLookCapturePreview } from '@/components/member/MemberLookCapturePreview';
import { MemberLookShareSheet } from '@/components/member/MemberLookShareSheet';
import { saveFileToDevice } from '@/lib/member-look-media';
import { resolveMediaUrl } from '@/lib/media-url';
import type { MemberLook } from '@/services/member-portal.service';

const MAX_LOOKS = 4;

export function MemberLooksGallery({
  looks,
  busy,
  salonName,
  socialFacebookUrl,
  socialInstagramUrl,
  onUpload,
  onDelete,
}: {
  looks: MemberLook[];
  busy: boolean;
  salonName: string;
  socialFacebookUrl?: string | null;
  socialInstagramUrl?: string | null;
  onUpload: (file: File) => Promise<void>;
  onDelete: (id: string) => Promise<void>;
}) {
  const inputRef = useRef<HTMLInputElement | null>(null);
  const [localError, setLocalError] = useState<string | null>(null);
  const [pendingCapture, setPendingCapture] = useState<{ file: File; previewUrl: string } | null>(
    null,
  );
  const [shareImageUrl, setShareImageUrl] = useState<string | null>(null);
  const [viewImageUrl, setViewImageUrl] = useState<string | null>(null);
  const slots = Array.from({ length: MAX_LOOKS }, (_, i) => looks[i] ?? null);

  function openCamera() {
    setLocalError(null);
    inputRef.current?.click();
  }

  function clearPendingCapture() {
    if (pendingCapture) {
      URL.revokeObjectURL(pendingCapture.previewUrl);
    }
    setPendingCapture(null);
  }

  async function handleSaveCapture() {
    if (!pendingCapture) return;
    setLocalError(null);
    try {
      saveFileToDevice(pendingCapture.file);
      await onUpload(pendingCapture.file);
      clearPendingCapture();
    } catch (err) {
      setLocalError(err instanceof Error ? err.message : 'Could not save look');
    }
  }

  return (
    <section className="space-y-3">
      <div className="flex items-start justify-between gap-2">
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-[var(--book-moss)]">
            Your style
          </p>
          <h2 className="book-display mt-0.5 text-xl font-bold text-[var(--book-ink)]">
            Here&apos;s Your New Look
          </h2>
          <p className="mt-1 text-sm text-[var(--book-muted)]">
            Never forget the last look, keep it here for reference
          </p>
        </div>
        <span className="shrink-0 rounded-full bg-[var(--book-wash)] px-2.5 py-1 text-xs font-semibold text-[var(--book-muted)]">
          {looks.length}/{MAX_LOOKS}
        </span>
      </div>

      {localError ? (
        <p className="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
          {localError}
        </p>
      ) : null}

      <input
        ref={inputRef}
        type="file"
        accept="image/*"
        capture="environment"
        className="hidden"
        onChange={(e) => {
          const file = e.target.files?.[0] ?? null;
          e.target.value = '';
          if (!file) return;
          clearPendingCapture();
          setPendingCapture({
            file,
            previewUrl: URL.createObjectURL(file),
          });
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
                <button
                  type="button"
                  className="h-full w-full"
                  aria-label={`View look ${index + 1}`}
                  onClick={() => setViewImageUrl(src)}
                >
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={src}
                    alt={look.caption || `Look ${index + 1}`}
                    className="h-full w-full object-cover"
                  />
                </button>
                <div className="absolute right-1.5 top-1.5 flex gap-1">
                  <button
                    type="button"
                    disabled={busy}
                    className="rounded-lg bg-black/55 px-2 py-1 text-[10px] font-semibold text-white disabled:opacity-50"
                    onClick={() => setShareImageUrl(src)}
                  >
                    Share
                  </button>
                  <button
                    type="button"
                    disabled={busy}
                    className="rounded-lg bg-black/55 px-2 py-1 text-[10px] font-semibold text-white disabled:opacity-50"
                    onClick={() => {
                      void onDelete(look.id).catch((err) => {
                        setLocalError(err instanceof Error ? err.message : 'Could not delete');
                      });
                    }}
                  >
                    Remove
                  </button>
                </div>
              </div>
            );
          }

          return (
            <button
              key={`empty-${index}`}
              type="button"
              disabled={busy || looks.length >= MAX_LOOKS}
              className="flex aspect-square flex-col items-center justify-center gap-1 rounded-2xl border border-dashed border-[var(--book-line)] bg-[var(--book-wash)]/70 text-sm font-semibold text-[var(--book-muted)] transition hover:border-[var(--book-moss)] hover:text-[var(--book-moss)] disabled:opacity-50"
              onClick={openCamera}
            >
              <span className="text-xl leading-none">+</span>
              Add look
            </button>
          );
        })}
      </div>

      {pendingCapture ? (
        <MemberLookCapturePreview
          previewUrl={pendingCapture.previewUrl}
          busy={busy}
          onRetake={() => {
            clearPendingCapture();
            openCamera();
          }}
          onSave={() => void handleSaveCapture()}
          onClose={clearPendingCapture}
        />
      ) : null}

      <MemberLookShareSheet
        open={Boolean(shareImageUrl)}
        imageUrl={shareImageUrl}
        salonName={salonName}
        socialFacebookUrl={socialFacebookUrl}
        socialInstagramUrl={socialInstagramUrl}
        onClose={() => setShareImageUrl(null)}
      />

      {viewImageUrl ? (
        <div className="fixed inset-0 z-[65] flex items-center justify-center p-4">
          <button
            type="button"
            className="absolute inset-0 bg-stone-950/70"
            aria-label="Close look preview"
            onClick={() => setViewImageUrl(null)}
          />
          <div className="relative max-h-[85vh] w-full max-w-md overflow-hidden rounded-2xl border border-[var(--book-line)] bg-white shadow-2xl">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={viewImageUrl} alt="Saved look" className="max-h-[70vh] w-full object-contain" />
            <div className="flex gap-2 border-t border-[var(--book-line)] p-3">
              <button
                type="button"
                className="inline-flex flex-1 items-center justify-center rounded-xl bg-[var(--book-moss)] px-3 py-2.5 text-sm font-semibold text-white"
                onClick={() => {
                  setShareImageUrl(viewImageUrl);
                  setViewImageUrl(null);
                }}
              >
                Share
              </button>
              <button
                type="button"
                className="inline-flex flex-1 items-center justify-center rounded-xl border border-[var(--book-line)] bg-white px-3 py-2.5 text-sm font-semibold text-[var(--book-ink)]"
                onClick={() => setViewImageUrl(null)}
              >
                Close
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </section>
  );
}
