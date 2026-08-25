'use client';

export function MemberHeaderMenuButton({
  open,
  onClick,
}: {
  open: boolean;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      className={[
        'flex h-14 w-14 shrink-0 flex-col items-center justify-center gap-0.5 rounded-2xl text-[11px] font-semibold shadow-sm transition',
        open
          ? 'bg-[var(--book-moss)] text-white'
          : 'border border-[var(--book-line)] bg-white text-[var(--book-ink)] hover:border-[var(--book-moss)] hover:bg-[var(--book-wash)]',
      ].join(' ')}
      aria-label={open ? 'Close menu' : 'Open menu'}
      aria-expanded={open}
      aria-controls="member-side-drawer"
      onClick={onClick}
    >
      <span className="text-base leading-none" aria-hidden>
        ☰
      </span>
      <span>Menu</span>
    </button>
  );
}
