'use client';

import { useState } from 'react';
import {
  fileFromImageUrl,
  openFacebookShare,
  openInstagramProfile,
  openWhatsAppShare,
  shareImageFile,
} from '@/lib/member-look-media';

export function MemberLookShareSheet({
  open,
  imageUrl,
  salonName,
  socialFacebookUrl,
  socialInstagramUrl,
  onClose,
}: {
  open: boolean;
  imageUrl: string | null;
  salonName: string;
  socialFacebookUrl?: string | null;
  socialInstagramUrl?: string | null;
  onClose: () => void;
}) {
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);

  if (!open || !imageUrl) return null;

  const resolvedImageUrl = imageUrl;
  const shareText = `My new look from ${salonName}`;

  async function loadShareFile(): Promise<File> {
    return fileFromImageUrl(resolvedImageUrl, `my-look-${Date.now()}.jpg`);
  }

  async function shareNative() {
    setBusy(true);
    setNotice(null);
    try {
      const file = await loadShareFile();
      const result = await shareImageFile(file, {
        title: shareText,
        text: shareText,
      });
      if (result === 'shared') {
        onClose();
        return;
      }
      setNotice('Sharing is not supported on this device. Try WhatsApp or save the photo first.');
    } catch (err) {
      setNotice(err instanceof Error ? err.message : 'Could not share image');
    } finally {
      setBusy(false);
    }
  }

  async function shareWhatsApp() {
    setBusy(true);
    setNotice(null);
    try {
      const file = await loadShareFile();
      const result = await shareImageFile(file, {
        title: shareText,
        text: `${shareText}\n${resolvedImageUrl}`,
      });
      if (result === 'shared' || result === 'cancelled') {
        if (result === 'shared') onClose();
        return;
      }
      openWhatsAppShare(`${shareText}\n${resolvedImageUrl}`);
      onClose();
    } catch {
      openWhatsAppShare(`${shareText}\n${resolvedImageUrl}`);
      onClose();
    } finally {
      setBusy(false);
    }
  }

  async function shareInstagram() {
    setBusy(true);
    setNotice(null);
    try {
      const file = await loadShareFile();
      const result = await shareImageFile(file, {
        title: shareText,
        text: shareText,
      });
      if (result === 'shared') {
        onClose();
        return;
      }
      if (socialInstagramUrl) {
        openInstagramProfile(socialInstagramUrl);
        setNotice('Photo saved — open Instagram and create a post from your gallery.');
      } else {
        setNotice('Use your phone share menu to post to Instagram, or save the photo first.');
      }
    } catch (err) {
      setNotice(err instanceof Error ? err.message : 'Could not prepare image for Instagram');
    } finally {
      setBusy(false);
    }
  }

  async function shareFacebook() {
    setBusy(true);
    setNotice(null);
    try {
      const file = await loadShareFile();
      const result = await shareImageFile(file, {
        title: shareText,
        text: `${shareText}\n${resolvedImageUrl}`,
      });
      if (result === 'shared') {
        onClose();
        return;
      }
      if (socialFacebookUrl) {
        openFacebookShare(resolvedImageUrl);
        onClose();
        return;
      }
      openFacebookShare(resolvedImageUrl);
      onClose();
    } catch {
      openFacebookShare(resolvedImageUrl);
      onClose();
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="fixed inset-0 z-[70] flex items-end justify-center sm:items-center">
      <button
        type="button"
        className="absolute inset-0 bg-stone-950/45 backdrop-blur-[2px]"
        aria-label="Close share options"
        onClick={onClose}
      />
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="member-look-share-title"
        className="relative w-full max-w-lg rounded-t-3xl border border-[var(--book-line)] bg-white px-5 py-5 shadow-2xl sm:mx-4 sm:rounded-3xl"
        style={{ paddingBottom: 'max(1.25rem, env(safe-area-inset-bottom))' }}
      >
        <p id="member-look-share-title" className="text-center text-sm font-semibold text-[var(--book-ink)]">
          Share your look
        </p>
        <div className="mt-4 space-y-2">
          <button
            type="button"
            disabled={busy}
            className="inline-flex w-full items-center justify-center rounded-xl bg-[#25D366] px-4 py-3 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-50"
            onClick={() => void shareWhatsApp()}
          >
            WhatsApp
          </button>
          <button
            type="button"
            disabled={busy}
            className="inline-flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-[#833AB4] via-[#FD1D1D] to-[#F77737] px-4 py-3 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-50"
            onClick={() => void shareInstagram()}
          >
            Instagram
          </button>
          <button
            type="button"
            disabled={busy}
            className="inline-flex w-full items-center justify-center rounded-xl bg-[#1877F2] px-4 py-3 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-50"
            onClick={() => void shareFacebook()}
          >
            Facebook
          </button>
          <button
            type="button"
            disabled={busy}
            className="inline-flex w-full items-center justify-center rounded-xl border border-[var(--book-line)] bg-white px-4 py-3 text-sm font-semibold text-[var(--book-ink)] transition hover:bg-[var(--book-wash)] disabled:opacity-50"
            onClick={() => void shareNative()}
          >
            More sharing options
          </button>
        </div>
        {notice ? (
          <p className="mt-3 rounded-xl border border-[var(--book-line)] bg-[var(--book-wash)] px-3 py-2 text-xs text-[var(--book-muted)]">
            {notice}
          </p>
        ) : null}
        <button
          type="button"
          className="mt-3 w-full rounded-xl px-3 py-2 text-sm font-semibold text-[var(--book-muted)] hover:text-[var(--book-ink)]"
          onClick={onClose}
        >
          Cancel
        </button>
      </div>
    </div>
  );
}
