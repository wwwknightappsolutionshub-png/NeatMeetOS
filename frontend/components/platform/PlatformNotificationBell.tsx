'use client';

import Link from 'next/link';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { PlatformNotificationItem } from '@/lib/types';
import {
  fetchPlatformNotifications,
  markAllPlatformNotificationsRead,
  markPlatformNotificationRead,
} from '@/services/platform.service';

function formatWhen(iso: string | null): string {
  if (!iso) return '';
  try {
    return new Date(iso).toLocaleString(undefined, {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch {
    return iso;
  }
}

export function PlatformNotificationBell() {
  const [open, setOpen] = useState(false);
  const [items, setItems] = useState<PlatformNotificationItem[]>([]);
  const [unread, setUnread] = useState(0);
  const [loading, setLoading] = useState(false);
  const panelRef = useRef<HTMLDivElement>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const data = await fetchPlatformNotifications();
      setItems(data.items);
      setUnread(data.unread_count);
    } catch {
      /* ignore transient errors in header */
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
    const id = window.setInterval(() => void load(), 60_000);
    return () => window.clearInterval(id);
  }, [load]);

  useEffect(() => {
    if (!open) return;
    function onDoc(e: MouseEvent) {
      if (panelRef.current && !panelRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    }
    document.addEventListener('mousedown', onDoc);
    return () => document.removeEventListener('mousedown', onDoc);
  }, [open]);

  async function handleOpen() {
    setOpen((v) => !v);
    if (!open) await load();
  }

  async function handleMark(id: string) {
    try {
      const res = await markPlatformNotificationRead(id);
      setUnread(res.unread_count);
      setItems((prev) =>
        prev.map((n) =>
          n.id === id ? { ...n, read_at: n.read_at ?? new Date().toISOString() } : n,
        ),
      );
    } catch {
      /* ignore */
    }
  }

  async function handleMarkAll() {
    try {
      await markAllPlatformNotificationsRead();
      setUnread(0);
      setItems((prev) =>
        prev.map((n) => ({ ...n, read_at: n.read_at ?? new Date().toISOString() })),
      );
    } catch {
      /* ignore */
    }
  }

  return (
    <div className="relative" ref={panelRef}>
      <button
        type="button"
        onClick={() => void handleOpen()}
        className="relative rounded-md border border-[var(--platform-line-subtle)] bg-[var(--platform-surface)] p-2 text-[var(--platform-label)] hover:border-[var(--platform-line)] hover:text-white"
        aria-label="Notifications"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.8"
          className="h-4 w-4"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"
          />
        </svg>
        {unread > 0 ? (
          <span className="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-[var(--platform-accent)] px-1 font-mono text-[9px] font-bold text-[#041014]">
            {unread > 99 ? '99+' : unread}
          </span>
        ) : null}
      </button>

      {open ? (
        <div className="platform-ops-glow absolute right-0 z-50 mt-2 w-[min(100vw-2rem,22rem)] overflow-hidden rounded-xl border border-[var(--platform-line)] bg-[var(--platform-surface)] shadow-2xl">
          <div className="flex items-center justify-between border-b border-[var(--platform-line-subtle)] px-3 py-2.5">
            <p className="font-mono text-[10px] font-semibold uppercase tracking-[0.16em] text-[var(--platform-accent)]">
              Alerts
            </p>
            <button
              type="button"
              onClick={() => void handleMarkAll()}
              className="text-xs font-medium text-[var(--platform-label)] hover:text-white disabled:opacity-40"
              disabled={unread === 0}
            >
              Mark all read
            </button>
          </div>
          <div className="max-h-80 overflow-y-auto">
            {loading && items.length === 0 ? (
              <p className="px-3 py-6 text-center text-sm text-[var(--platform-muted)]">Loading…</p>
            ) : null}
            {!loading && items.length === 0 ? (
              <p className="px-3 py-6 text-center text-sm text-[var(--platform-muted)]">
                No alerts.
              </p>
            ) : null}
            {items.map((n) => {
              const href =
                typeof n.data?.href === 'string' ? n.data.href : '/platform/tenants';
              const unreadItem = !n.read_at;
              return (
                <Link
                  key={n.id}
                  href={href}
                  onClick={() => {
                    if (unreadItem) void handleMark(n.id);
                    setOpen(false);
                  }}
                  className={`block border-b border-[var(--platform-line-subtle)] px-3 py-3 transition hover:bg-white/[0.03] ${
                    unreadItem ? 'bg-[var(--platform-accent-soft)]' : ''
                  }`}
                >
                  <div className="flex items-start justify-between gap-2">
                    <p className="text-sm font-semibold text-white">{n.title}</p>
                    {unreadItem ? (
                      <span className="mt-1 h-2 w-2 shrink-0 rounded-full bg-[var(--platform-accent)] shadow-[0_0_8px_var(--platform-glow)]" />
                    ) : null}
                  </div>
                  <p className="mt-1 text-xs leading-relaxed text-[var(--platform-muted)]">{n.body}</p>
                  <p className="mt-1.5 font-mono text-[10px] text-[var(--platform-muted)]">
                    {formatWhen(n.created_at)}
                  </p>
                </Link>
              );
            })}
          </div>
        </div>
      ) : null}
    </div>
  );
}
