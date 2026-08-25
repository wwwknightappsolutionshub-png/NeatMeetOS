'use client';

import type { Tab } from '@/components/member/member-nav-types';

const items: Array<{ id: Tab | 'more'; label: string; icon: string }> = [
  { id: 'home', label: 'Home', icon: '⌂' },
  { id: 'messages', label: 'Messages', icon: '✉' },
  { id: 'visits', label: 'Visits', icon: '◷' },
  { id: 'more', label: 'More', icon: '☰' },
];

export function MemberFooterNav({
  activeTab,
  unreadMessages,
  moreOpen,
  onSelectTab,
  onOpenMore,
}: {
  activeTab: Tab;
  unreadMessages: number;
  moreOpen: boolean;
  onSelectTab: (tab: Tab) => void;
  onOpenMore: () => void;
}) {
  const primaryActive = activeTab === 'home' || activeTab === 'messages' || activeTab === 'visits';

  return (
    <nav
      className="fixed inset-x-0 bottom-0 z-40 border-t border-[var(--book-line)] bg-white/95 px-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-1.5 shadow-[0_-8px_24px_rgba(28,25,23,0.06)] backdrop-blur"
      aria-label="Membership navigation"
    >
      <div className="mx-auto flex max-w-lg items-stretch justify-around gap-1">
        {items.map((item) => {
          const isMore = item.id === 'more';
          const selected = isMore
            ? moreOpen || !primaryActive
            : !moreOpen && activeTab === item.id;
          return (
            <button
              key={item.id}
              type="button"
              className={[
                'relative flex min-w-0 flex-1 flex-col items-center gap-0.5 rounded-xl px-2 py-2 text-[11px] font-semibold transition',
                selected
                  ? 'bg-[var(--book-moss)] text-white'
                  : 'text-[var(--book-muted)] hover:bg-[var(--book-wash)] hover:text-[var(--book-ink)]',
              ].join(' ')}
              onClick={() => {
                if (item.id === 'more') onOpenMore();
                else onSelectTab(item.id);
              }}
            >
              <span className="text-base leading-none" aria-hidden>
                {item.icon}
              </span>
              <span className="truncate">{item.label}</span>
              {!isMore && item.id === 'messages' && unreadMessages > 0 ? (
                <span className="absolute right-2 top-1.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[9px] font-bold text-white">
                  {unreadMessages > 9 ? '9+' : unreadMessages}
                </span>
              ) : null}
            </button>
          );
        })}
      </div>
    </nav>
  );
}
