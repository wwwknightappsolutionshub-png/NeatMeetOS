'use client';

import type { Tab } from '@/components/member/member-nav-types';

const drawerItems: Array<{ id: Tab; label: string }> = [
  { id: 'points', label: 'Points' },
  { id: 'membership', label: 'Plans' },
  { id: 'shop', label: 'Shop' },
  { id: 'gifts', label: 'Gifts' },
  { id: 'refer', label: 'Refer' },
];

export function MemberSideDrawer({
  open,
  salonName,
  activeTab,
  onClose,
  onSelectTab,
  onScrollLookbook,
  onLogout,
}: {
  open: boolean;
  salonName: string;
  activeTab: Tab;
  onClose: () => void;
  onSelectTab: (tab: Tab) => void;
  onScrollLookbook: () => void;
  onLogout: () => void;
}) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex justify-end">
      <button
        type="button"
        className="absolute inset-0 bg-stone-950/45 backdrop-blur-[2px]"
        aria-label="Close menu"
        onClick={onClose}
      />
      <aside
        className="relative flex h-full w-[min(20rem,86vw)] flex-col border-l border-[var(--book-line)] bg-white shadow-2xl transition-transform duration-200 ease-out"
        id="member-side-drawer"
        role="dialog"
        aria-modal="true"
        aria-label="Membership menu"
      >
        <div className="border-b border-[var(--book-line)] px-5 py-4">
          <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-[var(--book-moss)]">
            Menu
          </p>
          <p className="mt-1 truncate text-lg font-semibold text-[var(--book-ink)]">{salonName}</p>
        </div>
        <div className="flex-1 space-y-1 overflow-y-auto px-3 py-3">
          {drawerItems.map((item) => (
            <button
              key={item.id}
              type="button"
              className={[
                'block w-full rounded-xl px-3 py-3 text-left text-sm font-semibold transition',
                activeTab === item.id
                  ? 'bg-[var(--book-moss)] text-white'
                  : 'text-[var(--book-ink)] hover:bg-[var(--book-wash)]',
              ].join(' ')}
              onClick={() => {
                onSelectTab(item.id);
                onClose();
              }}
            >
              {item.label}
            </button>
          ))}
          <button
            type="button"
            className="block w-full rounded-xl px-3 py-3 text-left text-sm font-semibold text-[var(--book-ink)] hover:bg-[var(--book-wash)]"
            onClick={() => {
              onScrollLookbook();
              onClose();
            }}
          >
            Lookbook
          </button>
        </div>
        <div className="border-t border-[var(--book-line)] p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
          <button
            type="button"
            className="w-full rounded-xl border border-[var(--book-line)] px-3 py-3 text-sm font-semibold text-[var(--book-ink)] hover:bg-[var(--book-wash)]"
            onClick={() => {
              onClose();
              onLogout();
            }}
          >
            Log out
          </button>
        </div>
      </aside>
    </div>
  );
}
