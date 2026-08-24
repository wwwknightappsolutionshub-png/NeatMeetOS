'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { AdminCrmShell } from '@/components/admin/crm/AdminCrmShell';
import { EmptyState, ErrorAlert, LoadingState, inputClass } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import {
  fetchAdminClientThreads,
  fetchAdminConversations,
  markAdminClientThreadRead,
  postAdminClientThread,
  type AdminConversationSummary,
  type AdminThreadMessage,
} from '@/services/messages.service';

export default function AdminMessagesPage() {
  const [filter, setFilter] = useState<'open' | 'all'>('open');
  const [conversations, setConversations] = useState<AdminConversationSummary[]>([]);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [thread, setThread] = useState<AdminThreadMessage[]>([]);
  const [reply, setReply] = useState('');
  const [loading, setLoading] = useState(true);
  const [threadLoading, setThreadLoading] = useState(false);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadConversations = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const rows = await fetchAdminConversations(filter);
      setConversations(rows);
      setSelectedId((current) => {
        if (current && rows.some((r) => r.client_id === current)) return current;
        return rows[0]?.client_id ?? null;
      });
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load conversations');
    } finally {
      setLoading(false);
    }
  }, [filter]);

  useEffect(() => {
    void loadConversations();
  }, [filter]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (!selectedId) {
      setThread([]);
      return;
    }
    let cancelled = false;
    void (async () => {
      setThreadLoading(true);
      setError(null);
      try {
        await markAdminClientThreadRead(selectedId);
        const messages = await fetchAdminClientThreads(selectedId);
        if (!cancelled) setThread(messages);
        void loadConversations();
      } catch (e) {
        if (!cancelled) setError(e instanceof Error ? e.message : 'Failed to load thread');
      } finally {
        if (!cancelled) setThreadLoading(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [selectedId]); // eslint-disable-line react-hooks/exhaustive-deps

  const selected = conversations.find((c) => c.client_id === selectedId) ?? null;

  async function handleReply(e: React.FormEvent) {
    e.preventDefault();
    if (!selectedId || !reply.trim()) return;
    setSending(true);
    setError(null);
    try {
      const message = await postAdminClientThread(selectedId, {
        body: reply.trim(),
        channel: 'in_app',
        notify_member: true,
      });
      setThread((prev) => [...prev, message]);
      setReply('');
      await loadConversations();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not send reply');
    } finally {
      setSending(false);
    }
  }

  return (
    <AdminCrmShell title="Messages">
      <div className="space-y-4">
        <Card title="Customer chat">
          <p className="text-sm text-zinc-600">
            Open conversations from membership app chats. Reply here and the customer sees it in
            Messages.
          </p>
          <div className="mt-3 flex flex-wrap gap-2">
            <Button
              type="button"
              variant={filter === 'open' ? 'primary' : 'secondary'}
              onClick={() => setFilter('open')}
            >
              Needs reply
            </Button>
            <Button
              type="button"
              variant={filter === 'all' ? 'primary' : 'secondary'}
              onClick={() => setFilter('all')}
            >
              All chats
            </Button>
            <Button type="button" variant="secondary" onClick={() => void loadConversations()}>
              Refresh
            </Button>
          </div>
        </Card>

        {error ? <ErrorAlert message={error} /> : null}
        {loading ? <LoadingState /> : null}

        {!loading ? (
          <div className="grid gap-4 lg:grid-cols-[minmax(0,280px)_1fr]">
            <Card title="Conversations">
              {conversations.length === 0 ? (
                <EmptyState
                  message={
                    filter === 'open'
                      ? 'No chats waiting for a reply.'
                      : 'No customer chats yet.'
                  }
                />
              ) : (
                <ul className="divide-y divide-zinc-100">
                  {conversations.map((c) => (
                    <li key={c.client_id}>
                      <button
                        type="button"
                        className={`w-full px-1 py-3 text-left text-sm transition ${
                          selectedId === c.client_id ? 'bg-zinc-50' : 'hover:bg-zinc-50'
                        }`}
                        onClick={() => setSelectedId(c.client_id)}
                      >
                        <div className="flex items-center justify-between gap-2">
                          <span className="font-semibold text-zinc-900">{c.client_name}</span>
                          {c.needs_reply || c.unread_inbound_count > 0 ? (
                            <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">
                              {c.unread_inbound_count || 'Reply'}
                            </span>
                          ) : null}
                        </div>
                        <p className="mt-1 line-clamp-2 text-xs text-zinc-500">
                          {c.last_message.body}
                        </p>
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </Card>

            <Card title={selected ? selected.client_name : 'Thread'}>
              {!selectedId ? (
                <EmptyState message="Select a conversation." />
              ) : (
                <div className="space-y-4">
                  <div className="flex flex-wrap items-center gap-3 text-xs text-zinc-500">
                    {selected?.client_phone ? <span>{selected.client_phone}</span> : null}
                    {selected?.client_email ? <span>{selected.client_email}</span> : null}
                    <Link
                      href={`/admin/clients/${selectedId}`}
                      className="font-semibold text-zinc-800 underline-offset-2 hover:underline"
                    >
                      Open client profile
                    </Link>
                  </div>

                  {threadLoading ? <LoadingState /> : null}

                  <div className="max-h-[420px] space-y-2 overflow-y-auto rounded-xl border border-zinc-200 bg-zinc-50 p-3">
                    {thread.length === 0 && !threadLoading ? (
                      <p className="text-sm text-zinc-500">No messages in this thread yet.</p>
                    ) : (
                      thread.map((m) => {
                        const fromCustomer = m.direction === 'inbound';
                        return (
                          <div
                            key={m.id}
                            className={`flex ${fromCustomer ? 'justify-start' : 'justify-end'}`}
                          >
                            <div
                              className={`max-w-[85%] rounded-2xl px-3 py-2 text-sm ${
                                fromCustomer
                                  ? 'border border-zinc-200 bg-white text-zinc-900'
                                  : 'bg-zinc-900 text-white'
                              }`}
                            >
                              <p className="whitespace-pre-wrap">{m.body}</p>
                              {m.created_at ? (
                                <p
                                  className={`mt-1 text-[10px] ${
                                    fromCustomer ? 'text-zinc-500' : 'text-white/70'
                                  }`}
                                >
                                  {new Date(m.created_at).toLocaleString()}
                                </p>
                              ) : null}
                            </div>
                          </div>
                        );
                      })
                    )}
                  </div>

                  <form onSubmit={(e) => void handleReply(e)} className="space-y-2">
                    <textarea
                      className={inputClass}
                      rows={3}
                      value={reply}
                      onChange={(e) => setReply(e.target.value)}
                      placeholder="Reply to customer…"
                      maxLength={5000}
                    />
                    <Button type="submit" disabled={sending || !reply.trim()}>
                      {sending ? 'Sending…' : 'Send reply'}
                    </Button>
                  </form>
                </div>
              )}
            </Card>
          </div>
        ) : null}
      </div>
    </AdminCrmShell>
  );
}
