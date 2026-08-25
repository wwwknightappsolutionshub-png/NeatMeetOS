'use client';

import { useState } from 'react';
import type { MemberReferralPayload } from '@/services/member-portal.service';
import {
  copyTextToClipboard,
  openEmailShare,
  openFacebookShare,
  openSmsShare,
  openWhatsAppShare,
  shareTextLink,
} from '@/lib/member-share';

export function MemberReferShareSheet({
  open,
  referral,
  salonName,
  onClose,
}: {
  open: boolean;
  referral: MemberReferralPayload | null;
  salonName: string;
  onClose: () => void;
}) {
  const [notice, setNotice] = useState<string | null>(null);

  if (!open || !referral?.enabled) return null;

  const shareText = referral.message;
  const shareUrl = referral.join_url;
  const emailSubject = referral.heading || `Join ${salonName}`;

  async function shareNative() {
    setNotice(null);
    const result = await shareTextLink({
      title: emailSubject,
      text: shareText,
      url: shareUrl,
    });
    if (result === 'shared') {
      onClose();
      return;
    }
    if (result === 'cancelled') return;
    setNotice('Sharing is not supported here. Try WhatsApp or copy the link.');
  }

  async function shareCopyLink() {
    setNotice(null);
    const copied = await copyTextToClipboard(shareText);
    if (copied) {
      setNotice('Invite message copied — paste it anywhere.');
      return;
    }
    setNotice('Could not copy automatically. Select the link on the refer screen.');
  }

  function shareWhatsApp() {
    openWhatsAppShare(shareText);
    onClose();
  }

  function shareFacebook() {
    openFacebookShare(shareUrl);
    onClose();
  }

  function shareSms() {
    openSmsShare(shareText);
    onClose();
  }

  function shareEmail() {
    openEmailShare(emailSubject, shareText);
    onClose();
  }

  return (
    <div className="fixed inset-0 z-[70] flex items-end justify-center sm:items-center">
      <button
        type="button"
        className="absolute inset-0 bg-stone-950/45 backdrop-blur-[2px]"
        aria-label="Close refer share options"
        onClick={onClose}
      />
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="member-refer-share-title"
        className="relative w-full max-w-lg rounded-t-3xl border border-[var(--book-line)] bg-white px-5 py-5 shadow-2xl sm:mx-4 sm:rounded-3xl"
        style={{ paddingBottom: 'max(1.25rem, env(safe-area-inset-bottom))' }}
      >
        <p id="member-refer-share-title" className="text-center text-sm font-semibold text-[var(--book-ink)]">
          Refer a friend
        </p>
        <p className="mt-1 text-center text-xs text-[var(--book-muted)]">
          Share your invite via WhatsApp, text, email, or more.
        </p>
        <div className="mt-4 space-y-2">
          <button
            type="button"
            className="inline-flex w-full items-center justify-center rounded-xl bg-[#25D366] px-4 py-3 text-sm font-semibold text-white transition hover:opacity-90"
            onClick={shareWhatsApp}
          >
            WhatsApp
          </button>
          <button
            type="button"
            className="inline-flex w-full items-center justify-center rounded-xl bg-[#1877F2] px-4 py-3 text-sm font-semibold text-white transition hover:opacity-90"
            onClick={shareFacebook}
          >
            Facebook
          </button>
          <button
            type="button"
            className="inline-flex w-full items-center justify-center rounded-xl border border-[var(--book-line)] bg-white px-4 py-3 text-sm font-semibold text-[var(--book-ink)] transition hover:bg-[var(--book-wash)]"
            onClick={shareSms}
          >
            Text message
          </button>
          <button
            type="button"
            className="inline-flex w-full items-center justify-center rounded-xl border border-[var(--book-line)] bg-white px-4 py-3 text-sm font-semibold text-[var(--book-ink)] transition hover:bg-[var(--book-wash)]"
            onClick={shareEmail}
          >
            Email
          </button>
          <button
            type="button"
            className="inline-flex w-full items-center justify-center rounded-xl border border-[var(--book-line)] bg-white px-4 py-3 text-sm font-semibold text-[var(--book-ink)] transition hover:bg-[var(--book-wash)]"
            onClick={() => void shareCopyLink()}
          >
            Copy invite message
          </button>
          <button
            type="button"
            className="inline-flex w-full items-center justify-center rounded-xl bg-[var(--book-moss)] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[var(--book-moss-deep)]"
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
