'use client';

import { useEffect } from 'react';

export function MemberLookCapturePreview({
  previewUrl,
  busy,
  onRetake,
  onSave,
  onClose,
}: {
  previewUrl: string;
  busy: boolean;
  onRetake: () => void;
  onSave: () => void;
  onClose: () => void;
}) {
  useEffect(() => {
    return () => {
      URL.revokeObjectURL(previewUrl);
    };
  }, [previewUrl]);

  return (
    <div className="fixed inset-0 z-[70] flex items-end justify-center sm:items-center">
      <button
        type="button"
        className="absolute inset-0 bg-stone-950/60 backdrop-blur-[2px]"
        aria-label="Close preview"
        onClick={onClose}
      />
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="member-look-preview-title"
        className="relative w-full max-w-lg rounded-t-3xl border border-[var(--book-line)] bg-white p-4 shadow-2xl sm:mx-4 sm:rounded-3xl"
        style={{ paddingBottom: 'max(1rem, env(safe-area-inset-bottom))' }}
      >
        <p
          id="member-look-preview-title"
          className="text-center text-sm font-semibold text-[var(--book-ink)]"
        >
          Preview your look
        </p>
        <p className="mt-1 text-center text-xs text-[var(--book-muted)]">
          Save to your phone and membership gallery, or retake the photo.
        </p>
        <div className="mt-4 overflow-hidden rounded-2xl border border-[var(--book-line)] bg-[var(--book-wash)]">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={previewUrl} alt="Captured look preview" className="max-h-[55vh] w-full object-contain" />
        </div>
        <div className="mt-4 grid gap-2 sm:grid-cols-2">
          <button
            type="button"
            disabled={busy}
            className="inline-flex w-full items-center justify-center rounded-xl border border-[var(--book-line)] bg-white px-4 py-3 text-sm font-semibold text-[var(--book-ink)] transition hover:bg-[var(--book-wash)] disabled:opacity-50"
            onClick={onRetake}
          >
            Retake
          </button>
          <button
            type="button"
            disabled={busy}
            className="inline-flex w-full items-center justify-center rounded-xl bg-[var(--book-moss)] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[var(--book-moss-deep)] disabled:opacity-50"
            onClick={onSave}
          >
            {busy ? 'Saving…' : 'Save look'}
          </button>
        </div>
      </div>
    </div>
  );
}
