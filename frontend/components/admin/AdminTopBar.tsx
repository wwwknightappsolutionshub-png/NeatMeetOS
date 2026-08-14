'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useCallback, useEffect, useRef, useState } from 'react';
import { clearStoredSession, getStoredToken } from '@/lib/api-client';
import type { ShellStatus, TenantOwnerNotice } from '@/lib/types';
import {
  formatDateTime,
  purposeLabel,
  type NotificationMessage,
} from '@/lib/notifications-types';
import { fetchShell, logout } from '@/services/auth.service';
import {
  fetchOwnerNotices,
  markOwnerNoticeRead,
} from '@/services/identity.service';
import {
  fetchNotificationMessages,
  postDeskNotificationMessage,
} from '@/services/notifications.service';
import { AdminDeskChatDrawer } from '@/components/admin/AdminDeskChatDrawer';

function normalizeMessages(data: unknown): NotificationMessage[] {
  if (Array.isArray(data)) return data;
  if (data && typeof data === 'object' && Array.isArray((data as { data?: unknown }).data)) {
    return (data as { data: NotificationMessage[] }).data;
  }
  return [];
}

function initials(name?: string | null): string {
  if (!name) return '?';
  const parts = name.trim().split(/\s+/).slice(0, 2);
  return parts.map((p) => p[0]?.toUpperCase() ?? '').join('') || '?';
}

interface AdminTopBarProps {
  /** Opens the shell nav drawer on small screens. */
  onMenuClick?: () => void;
}

export function AdminTopBar({ onMenuClick }: AdminTopBarProps = {}) {
  const router = useRouter();
  const pathname = usePathname();
  const onDashboard = pathname === '/admin/dashboard';
  const [shell, setShell] = useState<ShellStatus | null>(null);
  const [notifOpen, setNotifOpen] = useState(false);
  const [profileOpen, setProfileOpen] = useState(false);
  const [chatOpen, setChatOpen] = useState(false);
  const [messages, setMessages] = useState<NotificationMessage[]>([]);
  const [notices, setNotices] = useState<TenantOwnerNotice[]>([]);
  const [signingOut, setSigningOut] = useState(false);
  const notifRef = useRef<HTMLDivElement>(null);
  const profileRef = useRef<HTMLDivElement>(null);

  const loadShellAndAlerts = useCallback(async () => {
    if (!getStoredToken()) return;
    try {
      const [shellData, raw, ownerNotices] = await Promise.all([
        fetchShell(),
        fetchNotificationMessages({}).catch(() => [] as NotificationMessage[]),
        fetchOwnerNotices().catch(() => ({ items: [], unread_count: 0 })),
      ]);
      setShell(shellData);
      setMessages(normalizeMessages(raw).slice(0, 8));
      setNotices(ownerNotices.items.slice(0, 6));
    } catch {
      // Top bar stays usable even if shell refresh fails mid-session.
    }
  }, []);

  useEffect(() => {
    void loadShellAndAlerts();
  }, [loadShellAndAlerts]);

  useEffect(() => {
    function onDocClick(e: MouseEvent) {
      const target = e.target as Node;
      if (notifRef.current && !notifRef.current.contains(target)) setNotifOpen(false);
      if (profileRef.current && !profileRef.current.contains(target)) setProfileOpen(false);
    }
    document.addEventListener('mousedown', onDocClick);
    return () => document.removeEventListener('mousedown', onDocClick);
  }, []);

  async function handleSignOut() {
    setSigningOut(true);
    try {
      await logout();
    } catch {
      clearStoredSession();
    }
    router.replace('/login');
  }

  async function openNotice(notice: TenantOwnerNotice) {
    setNotifOpen(false);
    if (!notice.read_at) {
      try {
        await markOwnerNoticeRead(notice.id);
        setNotices((prev) =>
          prev.map((n) =>
            n.id === notice.id ? { ...n, read_at: new Date().toISOString() } : n,
          ),
        );
      } catch {
        // ignore mark-read failures
      }
    }
    router.push(notice.href || '/admin/settings/subscription');
  }

  const unreadNotices = notices.filter((n) => !n.read_at).length;
  const unreadHint =
    unreadNotices +
    messages.filter((m) => m.status === 'failed' || m.purpose === 'internal_note_delivery')
      .length;

  return (
    <>
      <header className="sticky top-0 z-30 flex h-14 items-center gap-1.5 border-b border-[var(--admin-line)] bg-white/90 px-3 backdrop-blur sm:gap-2 sm:px-6">
        <div className="flex shrink-0 items-center gap-1.5">
          {onMenuClick ? (
            <button
              type="button"
              onClick={onMenuClick}
              aria-label="Open navigation"
              className="-ml-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[var(--admin-line)] bg-white text-[var(--admin-ink)] hover:bg-[var(--admin-wash)] lg:hidden"
            >
              <MenuIcon />
            </button>
          ) : null}
          <Link
            href="/admin/dashboard"
            aria-label="Home — dashboard"
            title="Home"
            className={[
              'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border text-[var(--admin-ink)]',
              onDashboard
                ? 'border-[var(--admin-accent)] bg-[var(--admin-accent)] text-white'
                : 'border-[var(--admin-line)] bg-white hover:bg-[var(--admin-wash)]',
            ].join(' ')}
          >
            <HomeIcon />
          </Link>
          <div className="min-w-0 max-sm:hidden">
            <p className="truncate text-sm font-semibold text-[var(--admin-ink)]">
              {shell?.tenant?.name ?? 'Workspace'}
            </p>
            <p className="truncate text-xs text-[var(--admin-muted)]">
              {shell?.user?.name ? `Signed in as ${shell.user.name}` : 'Admin'}
            </p>
          </div>
        </div>

        <div className="ml-auto flex min-w-0 items-center justify-end gap-1 sm:gap-1.5">
          {shell?.user?.is_platform_admin ? (
            <Link
              href="/platform"
              className="hidden h-9 items-center rounded-lg border border-[var(--admin-line)] bg-white px-2.5 text-sm font-medium text-[var(--admin-ink)] hover:bg-[var(--admin-wash)] sm:inline-flex"
            >
              Platform
            </Link>
          ) : null}
          {shell?.trial?.active ? (
            <Link
              href="/admin/settings/subscription"
              className="inline-flex h-9 max-w-[4.75rem] shrink items-center justify-center truncate rounded-lg border border-amber-200 bg-amber-50 px-1.5 text-[11px] font-semibold tabular-nums text-amber-950 hover:bg-amber-100 sm:max-w-none sm:flex-col sm:px-2.5 sm:py-1 sm:text-xs"
              title={
                shell.trial.label?.trim() ||
                (shell.trial.ends_at
                  ? `Trial ends ${new Date(shell.trial.ends_at).toLocaleDateString()}`
                  : 'Free trial')
              }
            >
              <span className="truncate sm:hidden">
                {shell.trial.day}/{shell.trial.total_days}
              </span>
              <span className="hidden truncate sm:inline">
                {shell.trial.label?.trim()
                  ? shell.trial.label
                  : `Day ${shell.trial.day} / ${shell.trial.total_days}`}
              </span>
              {shell.trial.ends_at ? (
                <span className="hidden truncate text-[10px] leading-tight text-amber-800/80 sm:block">
                  Ends{' '}
                  {new Date(shell.trial.ends_at).toLocaleDateString(undefined, {
                    month: 'short',
                    day: 'numeric',
                  })}
                </span>
              ) : null}
            </Link>
          ) : null}
          <Link
            href="/admin/settings/referrals"
            className={[
              'inline-flex h-9 shrink-0 items-center justify-center rounded-lg border-2 border-[#c4a35a] bg-[#fff8e8] px-2 text-xs font-bold tracking-wide text-[#6b4f12] shadow-[0_1px_0_rgba(196,163,90,0.35)] hover:bg-[#f7eccf] sm:px-2.5 sm:text-sm',
              // Trial badge already competes for space on small phones.
              shell?.trial?.active ? 'max-sm:hidden' : '',
            ].join(' ')}
            title="Refer & Reward"
          >
            <span className="hidden sm:inline">Refer &amp; Reward*</span>
            <span className="sm:hidden">Refer</span>
          </Link>
          <button
            type="button"
            onClick={() => setChatOpen(true)}
            className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[var(--admin-line)] bg-white text-[var(--admin-ink)] hover:bg-[var(--admin-wash)] sm:w-auto sm:gap-1.5 sm:px-2.5 sm:text-sm"
            title="Desk chat"
          >
            <ChatIcon />
            <span className="hidden sm:inline">Chat</span>
          </button>

          <div className="relative" ref={notifRef}>
            <button
              type="button"
              onClick={() => {
                setNotifOpen((v) => {
                  const next = !v;
                  if (next) void loadShellAndAlerts();
                  return next;
                });
                setProfileOpen(false);
              }}
              className="relative inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[var(--admin-line)] bg-white text-[var(--admin-ink)] hover:bg-[var(--admin-wash)]"
              title="Notifications"
              aria-label="Notifications"
              aria-expanded={notifOpen}
            >
              <BellIcon />
              {unreadHint > 0 ? (
                <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white">
                  {unreadHint > 9 ? '9+' : unreadHint}
                </span>
              ) : null}
            </button>
            {notifOpen ? (
              <div className="absolute right-0 z-50 mt-2 w-[min(22rem,calc(100vw-1.5rem))] overflow-hidden rounded-xl border border-[var(--admin-line)] bg-white shadow-lg sm:w-96">
                <div className="flex items-center justify-between border-b border-[var(--admin-line)] px-3 py-2">
                  <p className="text-sm font-semibold">Notifications</p>
                  <Link
                    href="/admin/notifications/messages"
                    className="text-xs text-[var(--admin-muted)] underline"
                    onClick={() => setNotifOpen(false)}
                  >
                    View all
                  </Link>
                </div>
                <ul className="max-h-96 overflow-y-auto">
                  {notices.length === 0 && messages.length === 0 ? (
                    <li className="px-3 py-6 text-center text-sm text-[var(--admin-muted)]">
                      No recent notifications
                    </li>
                  ) : null}
                  {notices.map((n) => (
                    <li key={n.id} className="border-b border-stone-50 last:border-0">
                      <button
                        type="button"
                        className="block w-full px-3 py-2.5 text-left hover:bg-[var(--admin-wash)]"
                        onClick={() => void openNotice(n)}
                      >
                        {n.image_url ? (
                          // eslint-disable-next-line @next/next/no-img-element
                          <img
                            src={n.image_url}
                            alt=""
                            className="mb-2 h-28 w-full rounded-lg object-cover"
                          />
                        ) : null}
                        <p className="truncate text-sm font-medium text-[var(--admin-ink)]">
                          {n.title}
                          {!n.read_at ? (
                            <span className="ml-1 inline-block h-1.5 w-1.5 rounded-full bg-amber-500 align-middle" />
                          ) : null}
                        </p>
                        <p className="line-clamp-2 text-xs text-[var(--admin-muted)]">{n.body}</p>
                        <p className="mt-0.5 text-[11px] text-stone-400">
                          {formatDateTime(n.created_at)}
                        </p>
                      </button>
                    </li>
                  ))}
                  {messages.map((m) => (
                    <li key={m.id} className="border-b border-stone-50 last:border-0">
                      <Link
                        href={`/admin/notifications/messages/${m.id}`}
                        className="block px-3 py-2.5 hover:bg-[var(--admin-wash)]"
                        onClick={() => setNotifOpen(false)}
                      >
                        <p className="truncate text-sm font-medium text-[var(--admin-ink)]">
                          {m.subject || purposeLabel(m.purpose)}
                        </p>
                        <p className="truncate text-xs text-[var(--admin-muted)]">
                          {m.body_text || m.client?.display_name || m.status}
                        </p>
                        <p className="mt-0.5 text-[11px] text-stone-400">
                          {formatDateTime(m.created_at)}
                        </p>
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
          </div>

          <div className="relative" ref={profileRef}>
            <button
              type="button"
              onClick={() => {
                setProfileOpen((v) => !v);
                setNotifOpen(false);
              }}
              className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[var(--admin-line)] bg-white text-sm text-[var(--admin-ink)] hover:bg-[var(--admin-wash)] sm:w-auto sm:gap-2 sm:pl-1.5 sm:pr-2.5"
              title="Profile"
            >
              <span className="flex h-6 w-6 items-center justify-center rounded-full bg-[var(--admin-accent)] text-[10px] font-semibold text-white">
                {initials(shell?.user?.name)}
              </span>
              <span className="hidden max-w-[7rem] truncate sm:inline">
                {shell?.user?.name ?? 'Profile'}
              </span>
            </button>
            {profileOpen ? (
              <div className="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-xl border border-[var(--admin-line)] bg-white shadow-lg">
                <div className="border-b border-[var(--admin-line)] px-3 py-2">
                  <p className="truncate text-sm font-semibold">{shell?.user?.name ?? 'User'}</p>
                  <p className="truncate text-xs text-[var(--admin-muted)]">{shell?.user?.email}</p>
                </div>
                <ul className="py-1 text-sm">
                  <li>
                    <Link
                      href="/admin/settings/account"
                      className="block px-3 py-2 hover:bg-[var(--admin-wash)]"
                      onClick={() => setProfileOpen(false)}
                    >
                      Account settings
                    </Link>
                  </li>
                  {shell?.trial?.active ? (
                    <li className="sm:hidden">
                      <Link
                        href="/admin/settings/referrals"
                        className="block px-3 py-2 hover:bg-[var(--admin-wash)]"
                        onClick={() => setProfileOpen(false)}
                      >
                        Refer &amp; Reward
                      </Link>
                    </li>
                  ) : null}
                  <li>
                    <Link
                      href="/admin/settings/team"
                      className="block px-3 py-2 hover:bg-[var(--admin-wash)]"
                      onClick={() => setProfileOpen(false)}
                    >
                      Team
                    </Link>
                  </li>
                  <li>
                    <button
                      type="button"
                      disabled={signingOut}
                      onClick={() => void handleSignOut()}
                      className="block w-full px-3 py-2 text-left text-red-700 hover:bg-red-50 disabled:opacity-50"
                    >
                      {signingOut ? 'Signing out…' : 'Sign out'}
                    </button>
                  </li>
                </ul>
              </div>
            ) : null}
          </div>
        </div>
      </header>

      <AdminDeskChatDrawer
        open={chatOpen}
        onClose={() => setChatOpen(false)}
        onPosted={() => void loadShellAndAlerts()}
        postMessage={postDeskNotificationMessage}
        fetchDeskMessages={async () =>
          normalizeMessages(await fetchNotificationMessages({ desk_only: '1' }))
        }
      />
    </>
  );
}

function MenuIcon() {
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden>
      <path
        d="M4 7h16M4 12h16M4 17h16"
        stroke="currentColor"
        strokeWidth="1.75"
        strokeLinecap="round"
      />
    </svg>
  );
}

function HomeIcon() {
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden>
      <path
        d="M4.5 11.5 12 5l7.5 6.5V19a1.5 1.5 0 0 1-1.5 1.5h-4.25v-5.25h-3.5V20.5H6A1.5 1.5 0 0 1 4.5 19v-7.5Z"
        stroke="currentColor"
        strokeWidth="1.75"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function BellIcon() {
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden>
      <path
        d="M6 9a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9Z"
        stroke="currentColor"
        strokeWidth="1.75"
        strokeLinejoin="round"
      />
      <path d="M10 19a2 2 0 0 0 4 0" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
    </svg>
  );
}

function ChatIcon() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden>
      <path
        d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v7A2.5 2.5 0 0 1 17.5 16H9l-4 3.5V6.5Z"
        stroke="currentColor"
        strokeWidth="1.75"
        strokeLinejoin="round"
      />
    </svg>
  );
}
