'use client';

import { useCallback, useEffect, useState } from 'react';
import { formatDateTime, type NotificationMessage } from '@/lib/notifications-types';

interface AdminDeskChatDrawerProps {
  open: boolean;
  onClose: () => void;
  onPosted?: () => void;
  fetchDeskMessages: () => Promise<NotificationMessage[]>;
  postMessage: (body: string) => Promise<NotificationMessage>;
}

/**
 * In-app desk chat for admin/support — backed by Notifications internal notes
 * (tenant-scoped desk feed), not a separate chat product.
 */
export function AdminDeskChatDrawer({
  open,
  onClose,
  onPosted,
  fetchDeskMessages,
  postMessage,
}: AdminDeskChatDrawerProps) {
  const [messages, setMessages] = useState<NotificationMessage[]>([]);
  const [body, setBody] = useState('');
  const [loading, setLoading] = useState(false);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const rows = await fetchDeskMessages();
      setMessages([...rows].reverse());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not load desk chat');
    } finally {
      setLoading(false);
    }
  }, [fetchDeskMessages]);

  useEffect(() => {
    if (open) void load();
  }, [open, load]);

  async function handleSend() {
    const text = body.trim();
    if (!text) return;
    setSending(true);
    setError(null);
    try {
      const created = await postMessage(text);
      setMessages((prev) => [...prev, created]);
      setBody('');
      onPosted?.();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not post message');
    } finally {
      setSending(false);
    }
  }

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-40 flex justify-end">
      <button
        type="button"
        className="absolute inset-0 bg-zinc-900/30"
        aria-label="Close desk chat"
        onClick={onClose}
      />
      <aside className="relative flex h-full w-full max-w-md flex-col border-l border-zinc-200 bg-white shadow-xl">
        <div className="flex items-center justify-between border-b border-zinc-200 px-4 py-3">
          <div>
            <p className="text-sm font-semibold text-zinc-900">Desk chat</p>
            <p className="text-xs text-zinc-500">Team / support notes for this salon</p>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-md px-2 py-1 text-sm text-zinc-600 hover:bg-zinc-100"
          >
            Close
          </button>
        </div>

        <div className="flex-1 space-y-3 overflow-y-auto px-4 py-4">
          {loading ? <p className="text-sm text-zinc-500">Loading…</p> : null}
          {error ? <p className="text-sm text-red-600">{error}</p> : null}
          {!loading && messages.length === 0 ? (
            <p className="text-sm text-zinc-500">
              No desk notes yet. Post a handoff for reception or support.
            </p>
          ) : null}
          {messages.map((m) => (
            <div key={m.id} className="rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5">
              <div className="mb-1 flex items-center justify-between gap-2">
                <p className="text-xs font-semibold text-zinc-800">
                  {m.created_by?.display_name ?? m.recipient_name ?? 'Desk'}
                </p>
                <p className="text-[11px] text-zinc-400">{formatDateTime(m.created_at)}</p>
              </div>
              <p className="whitespace-pre-wrap text-sm text-zinc-700">{m.body_text}</p>
            </div>
          ))}
        </div>

        <div className="border-t border-zinc-200 p-3">
          <textarea
            className="mb-2 w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-500"
            rows={3}
            placeholder="Write a desk note for the team…"
            value={body}
            onChange={(e) => setBody(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
                e.preventDefault();
                void handleSend();
              }
            }}
          />
          <div className="flex items-center justify-between gap-2">
            <p className="text-[11px] text-zinc-400">Ctrl/⌘ + Enter to send</p>
            <button
              type="button"
              disabled={sending || !body.trim()}
              onClick={() => void handleSend()}
              className="rounded-md bg-zinc-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
            >
              {sending ? 'Sending…' : 'Send'}
            </button>
          </div>
        </div>
      </aside>
    </div>
  );
}
