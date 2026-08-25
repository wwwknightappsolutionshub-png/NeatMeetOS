'use client';

import type { Tab } from '@/components/member/member-nav-types';
import {
  MemberNavHomeIcon,
  MemberNavMessagesIcon,
  MemberNavReferIcon,
  MemberNavVisitsIcon,
} from '@/components/member/member-footer-icons';

const items: Array<{
  id: Tab;
  label: string;
  ariaLabel: string;
  Icon: typeof MemberNavHomeIcon;
}> = [
  { id: 'home', label: 'Home', ariaLabel: 'Home', Icon: MemberNavHomeIcon },
  { id: 'messages', label: 'Messages', ariaLabel: 'Messages', Icon: MemberNavMessagesIcon },
  { id: 'visits', label: 'Visits', ariaLabel: 'Visits', Icon: MemberNavVisitsIcon },
  {
    id: 'refer',
    label: 'Refer',
    ariaLabel: 'Refer a friend',
    Icon: MemberNavReferIcon,
  },
];

export function MemberFooterNav({
  activeTab,
  unreadMessages,
  onSelectTab,
  onReferPress,
}: {
  activeTab: Tab;
  unreadMessages: number;
  onSelectTab: (tab: Tab) => void;
  onReferPress?: () => void;
}) {
  return (
    <nav
      className="fixed inset-x-0 bottom-0 z-40 border-t border-[var(--book-line)] bg-white/95 px-1.5 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-2 shadow-[0_-8px_24px_rgba(28,25,23,0.06)] backdrop-blur"
      aria-label="Membership navigation"
    >
      <div className="mx-auto flex max-w-lg items-stretch justify-around gap-0.5">
        {items.map((item) => {
          const selected = activeTab === item.id;
          const Icon = item.Icon;
          return (
            <button
              key={item.id}
              type="button"
              aria-label={item.ariaLabel}
              aria-current={selected ? 'page' : undefined}
              className={[
                'relative flex min-w-0 flex-1 flex-col items-center gap-1 rounded-xl px-1.5 py-2.5 text-[11px] font-semibold transition sm:text-xs',
                selected
                  ? 'bg-[var(--book-moss)] text-white'
                  : 'text-[var(--book-muted)] hover:bg-[var(--book-wash)] hover:text-[var(--book-ink)]',
              ].join(' ')}
              onClick={() => {
                if (item.id === 'refer' && onReferPress) {
                  onReferPress();
                  return;
                }
                onSelectTab(item.id);
              }}
            >
              <Icon className="h-7 w-7 shrink-0" />
              <span className="truncate leading-none">{item.label}</span>
              {item.id === 'messages' && unreadMessages > 0 ? (
                <span className="absolute right-1.5 top-1 inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
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
