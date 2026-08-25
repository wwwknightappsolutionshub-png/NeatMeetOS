'use client';

import Link from 'next/link';
import { useMemo, useState } from 'react';
import {
  noticeManageHref,
  noticeManageLabel,
  partitionMemberNotices,
  stripUrlsFromNoticeBody,
} from '@/lib/member-notice-text';
import type { MemberNotice, MemberThreadMessage } from '@/services/member-portal.service';

type MessagesView = 'notifications' | 'chat';

function NoticeCard({
  notice,
  onMarkRead,
}: {
  notice: MemberNotice;
  onMarkRead: (notice: MemberNotice) => void;
}) {
  const body = stripUrlsFromNoticeBody(notice.body, notice.href);
  const manageHref = noticeManageHref(notice);

  return (
    <div
      className={`w-full min-w-0 overflow-hidden rounded-2xl border px-3.5 py-3 text-left text-sm ${
        notice.read_at
          ? 'border-[var(--book-line)] bg-white'
          : 'border-[var(--book-moss)] bg-[var(--book-wash)]'
      }`}
    >
      <button type="button" className="w-full min-w-0 text-left" onClick={() => void onMarkRead(notice)}>
        <p className="break-words font-medium text-[var(--book-ink)]">{notice.title}</p>
        {body ? (
          <p className="mt-1 break-words text-[var(--book-muted)] [overflow-wrap:anywhere]">{body}</p>
        ) : null}
        {notice.created_at ? (
          <p className="mt-2 text-xs text-[var(--book-muted)]">
            {new Date(notice.created_at).toLocaleString()}
            {!notice.read_at ? ' · Unread' : ''}
          </p>
        ) : null}
      </button>
      {manageHref ? (
        <Link
          href={manageHref}
          className="mt-3 inline-flex max-w-full items-center justify-center rounded-xl bg-[var(--book-moss)] px-3 py-2 text-xs font-semibold text-white"
        >
          {noticeManageLabel(notice)}
        </Link>
      ) : null}
    </div>
  );
}

export function MemberMessagesPanel({
  notices,
  threadMessages,
  chatDraft,
  chatSending,
  unreadNotices,
  unreadThread,
  onMarkNoticeRead,
  onChatDraftChange,
  onSendChat,
}: {
  notices: MemberNotice[];
  threadMessages: MemberThreadMessage[];
  chatDraft: string;
  chatSending: boolean;
  unreadNotices: number;
  unreadThread: number;
  onMarkNoticeRead: (notice: MemberNotice) => Promise<void>;
  onChatDraftChange: (value: string) => void;
  onSendChat: (e: React.FormEvent) => void;
}) {
  const [view, setView] = useState<MessagesView>('notifications');
  const { bookingNotices, salonUpdates } = useMemo(
    () => partitionMemberNotices(notices),
    [notices],
  );

  return (
    <div className="min-w-0 overflow-hidden">
      <div className="grid grid-cols-2 gap-2 rounded-2xl border border-[var(--book-line)] bg-[var(--book-wash)]/60 p-1">
        <button
          type="button"
          className={`rounded-xl px-3 py-2.5 text-xs font-semibold transition ${
            view === 'notifications'
              ? 'bg-white text-[var(--book-ink)] shadow-sm'
              : 'text-[var(--book-muted)] hover:text-[var(--book-ink)]'
          }`}
          onClick={() => setView('notifications')}
        >
          Booking notifications
          {unreadNotices > 0 ? (
            <span className="ml-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[9px] font-bold text-white">
              {unreadNotices > 9 ? '9+' : unreadNotices}
            </span>
          ) : null}
        </button>
        <button
          type="button"
          className={`rounded-xl px-3 py-2.5 text-xs font-semibold transition ${
            view === 'chat'
              ? 'bg-white text-[var(--book-ink)] shadow-sm'
              : 'text-[var(--book-muted)] hover:text-[var(--book-ink)]'
          }`}
          onClick={() => setView('chat')}
        >
          Live chat
          {unreadThread > 0 ? (
            <span className="ml-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[9px] font-bold text-white">
              {unreadThread > 9 ? '9+' : unreadThread}
            </span>
          ) : null}
        </button>
      </div>

      {view === 'notifications' ? (
        <div className="mt-4 space-y-5">
          <section className="min-w-0 space-y-2">
            <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-[var(--book-moss)]">
              Appointment updates
            </p>
            {bookingNotices.length === 0 ? (
              <p className="text-sm text-[var(--book-muted)]">
                Booking confirmations and reminders will appear here.
              </p>
            ) : (
              bookingNotices.map((notice) => (
                <NoticeCard key={notice.id} notice={notice} onMarkRead={onMarkNoticeRead} />
              ))
            )}
          </section>

          {salonUpdates.length > 0 ? (
            <section className="min-w-0 space-y-2">
              <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-[var(--book-moss)]">
                Salon updates
              </p>
              {salonUpdates.map((notice) => (
                <NoticeCard key={notice.id} notice={notice} onMarkRead={onMarkNoticeRead} />
              ))}
            </section>
          ) : null}
        </div>
      ) : (
        <section className="mt-4 min-w-0 space-y-3">
          <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-[var(--book-moss)]">
            Chat with salon
          </p>
          <div className="max-h-[min(24rem,55vh)] space-y-2 overflow-y-auto overflow-x-hidden rounded-2xl border border-[var(--book-line)] bg-[var(--book-wash)]/50 p-3">
            {threadMessages.length === 0 ? (
              <p className="text-sm text-[var(--book-muted)]">
                Say hello — the salon can reply here.
              </p>
            ) : (
              threadMessages.map((message) => {
                const mine = message.direction === 'inbound';
                return (
                  <div key={message.id} className={`flex min-w-0 ${mine ? 'justify-end' : 'justify-start'}`}>
                    <div
                      className={`max-w-[85%] min-w-0 rounded-2xl px-3 py-2 text-sm ${
                        mine
                          ? 'bg-[var(--book-moss)] text-white'
                          : 'border border-[var(--book-line)] bg-white text-[var(--book-ink)]'
                      }`}
                    >
                      <p className="whitespace-pre-wrap break-words [overflow-wrap:anywhere]">
                        {message.body}
                      </p>
                      {message.created_at ? (
                        <p
                          className={`mt-1 text-[10px] ${
                            mine ? 'text-white/70' : 'text-[var(--book-muted)]'
                          }`}
                        >
                          {new Date(message.created_at).toLocaleString()}
                        </p>
                      ) : null}
                    </div>
                  </div>
                );
              })
            )}
          </div>
          <form onSubmit={onSendChat} className="flex min-w-0 gap-2">
            <input
              className="min-w-0 flex-1 rounded-md border border-[var(--book-line)] bg-white px-3 py-2.5 text-sm text-[var(--book-ink)] outline-none transition focus:border-[var(--book-moss)] focus:ring-2 focus:ring-[var(--book-moss-soft)]"
              value={chatDraft}
              onChange={(e) => onChatDraftChange(e.target.value)}
              placeholder="Write a message…"
              maxLength={2000}
            />
            <button
              type="submit"
              disabled={chatSending || !chatDraft.trim()}
              className="shrink-0 rounded-xl bg-[var(--book-moss)] px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
            >
              {chatSending ? '…' : 'Send'}
            </button>
          </form>
        </section>
      )}
    </div>
  );
}
