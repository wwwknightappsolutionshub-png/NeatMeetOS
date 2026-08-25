'use client';

export function MemberLogoutPrompt({
  open,
  onClose,
  onSignOutCompletely,
  onVisitBookingPage,
}: {
  open: boolean;
  onClose: () => void;
  onSignOutCompletely: () => void;
  onVisitBookingPage: () => void;
}) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[60] flex items-end justify-center sm:items-center">
      <button
        type="button"
        className="absolute inset-0 bg-stone-950/45 backdrop-blur-[2px]"
        aria-label="Dismiss sign out options"
        onClick={onClose}
      />
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="member-logout-prompt-title"
        className="relative w-full max-w-lg rounded-t-3xl border border-[var(--book-line)] bg-white px-5 py-5 shadow-2xl sm:rounded-3xl sm:mx-4"
        style={{ paddingBottom: 'max(1.25rem, env(safe-area-inset-bottom))' }}
      >
        <p
          id="member-logout-prompt-title"
          className="text-center text-sm font-semibold text-[var(--book-ink)]"
        >
          Do you want to:
        </p>
        <div className="mt-4 space-y-2">
          <button
            type="button"
            className="inline-flex w-full items-center justify-center rounded-xl bg-[var(--book-moss)] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[var(--book-moss-deep)]"
            onClick={onSignOutCompletely}
          >
            Sign out completely
          </button>
          <button
            type="button"
            className="inline-flex w-full items-center justify-center rounded-xl border border-[var(--book-line)] bg-white px-4 py-3 text-sm font-semibold text-[var(--book-ink)] transition hover:bg-[var(--book-wash)]"
            onClick={onVisitBookingPage}
          >
            Visit booking page
          </button>
        </div>
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
