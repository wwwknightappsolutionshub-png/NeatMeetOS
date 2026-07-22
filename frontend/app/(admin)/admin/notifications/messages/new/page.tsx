'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useEffect, useState } from 'react';
import { AdminNotificationsShell } from '@/components/admin/notifications/AdminNotificationsShell';
import { ErrorAlert, Field, inputClass } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { Client } from '@/lib/crm-types';
import {
  channelLabel,
  NOTIFICATION_CHANNELS,
  purposeLabel,
  type NotificationTemplate,
} from '@/lib/notifications-types';
import { fetchClients } from '@/services/crm.service';
import {
  fetchNotificationTemplates,
  sendManualNotificationMessage,
} from '@/services/notifications.service';

const MANUAL_PURPOSES = ['manual_client_message', 'internal_note_delivery'] as const;

export default function NewNotificationMessagePage() {
  const router = useRouter();
  const [search, setSearch] = useState('');
  const [clients, setClients] = useState<Client[]>([]);
  const [selected, setSelected] = useState<Client | null>(null);
  const [templates, setTemplates] = useState<NotificationTemplate[]>([]);
  const [form, setForm] = useState({
    channel: 'email',
    purpose: 'manual_client_message',
    subject: '',
    body_text: '',
    notification_template_id: '',
    recipient_address: '',
  });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchNotificationTemplates({ is_active: true }).then(setTemplates).catch(() => setTemplates([]));
  }, []);

  useEffect(() => {
    const handle = setTimeout(() => {
      if (search.trim().length < 2) {
        setClients([]);
        return;
      }
      fetchClients({ search: search.trim() })
        .then((res) => setClients(res.items.slice(0, 8)))
        .catch(() => setClients([]));
    }, 250);
    return () => clearTimeout(handle);
  }, [search]);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!selected) {
      setError('Select a client first.');
      return;
    }
    setSaving(true);
    setError(null);
    try {
      const message = await sendManualNotificationMessage({
        client_id: selected.id,
        channel: form.channel,
        purpose: form.purpose,
        subject: form.channel === 'email' && form.subject ? form.subject : null,
        body_text: form.body_text || null,
        notification_template_id: form.notification_template_id || null,
        recipient_address: form.recipient_address || null,
      });
      router.push(`/admin/notifications/messages/${message.id}`);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Send failed');
      setSaving(false);
    }
  }

  const channelTemplates = templates.filter((t) => String(t.channel) === form.channel);

  return (
    <AdminNotificationsShell title="New manual message">
      <p className="mb-4 text-sm">
        <Link href="/admin/notifications/messages" className="text-zinc-600 hover:underline">
          ← Back to messages
        </Link>
      </p>
      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}

      <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <strong>Operational message.</strong> This sends a one-off transactional communication to a client — it is not a
        marketing campaign. Delivery is simulated and respects the client&apos;s operational preferences.
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <Card title="Compose">
            <form onSubmit={handleSubmit} className="grid gap-4">
              <Field label="Client">
                {selected ? (
                  <div className="flex items-center justify-between rounded-md border border-zinc-300 px-3 py-2 text-sm">
                    <span>
                      <span className="font-medium">{selected.resolved_display_name}</span>{' '}
                      <span className="text-zinc-500">{selected.email ?? selected.phone ?? ''}</span>
                    </span>
                    <button type="button" className="text-xs text-zinc-600 underline" onClick={() => setSelected(null)}>
                      Change
                    </button>
                  </div>
                ) : (
                  <div>
                    <input
                      className={inputClass}
                      placeholder="Search clients by name, email or phone…"
                      value={search}
                      onChange={(e) => setSearch(e.target.value)}
                    />
                    {clients.length > 0 ? (
                      <ul className="mt-1 divide-y divide-zinc-100 rounded-md border border-zinc-200">
                        {clients.map((client) => (
                          <li key={client.id}>
                            <button
                              type="button"
                              className="block w-full px-3 py-2 text-left text-sm hover:bg-zinc-50"
                              onClick={() => {
                                setSelected(client);
                                setClients([]);
                                setSearch('');
                              }}
                            >
                              <span className="font-medium">{client.resolved_display_name}</span>{' '}
                              <span className="text-zinc-500">{client.email ?? client.phone ?? ''}</span>
                            </button>
                          </li>
                        ))}
                      </ul>
                    ) : null}
                  </div>
                )}
              </Field>

              <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Channel">
                  <select
                    className={inputClass}
                    value={form.channel}
                    onChange={(e) => setForm({ ...form, channel: e.target.value, notification_template_id: '' })}
                  >
                    {NOTIFICATION_CHANNELS.map((c) => (
                      <option key={c} value={c}>{channelLabel(c)}</option>
                    ))}
                  </select>
                </Field>
                <Field label="Purpose">
                  <select
                    className={inputClass}
                    value={form.purpose}
                    onChange={(e) => setForm({ ...form, purpose: e.target.value })}
                  >
                    {MANUAL_PURPOSES.map((p) => (
                      <option key={p} value={p}>{purposeLabel(p)}</option>
                    ))}
                  </select>
                </Field>
              </div>

              {channelTemplates.length > 0 ? (
                <Field label="Template (optional)">
                  <select
                    className={inputClass}
                    value={form.notification_template_id}
                    onChange={(e) => {
                      const tpl = channelTemplates.find((t) => t.id === e.target.value);
                      setForm({
                        ...form,
                        notification_template_id: e.target.value,
                        subject: tpl?.subject ?? form.subject,
                        body_text: tpl?.body_text ?? form.body_text,
                      });
                    }}
                  >
                    <option value="">No template</option>
                    {channelTemplates.map((t) => (
                      <option key={t.id} value={t.id}>{t.name}</option>
                    ))}
                  </select>
                </Field>
              ) : null}

              {form.channel === 'email' ? (
                <Field label="Subject">
                  <input
                    className={inputClass}
                    value={form.subject}
                    onChange={(e) => setForm({ ...form, subject: e.target.value })}
                  />
                </Field>
              ) : null}

              <Field label="Message body">
                <textarea
                  className={`${inputClass} min-h-40`}
                  value={form.body_text}
                  onChange={(e) => setForm({ ...form, body_text: e.target.value })}
                  required
                />
              </Field>

              <Field label="Override recipient address (optional)">
                <input
                  className={inputClass}
                  placeholder="Defaults to client email / phone"
                  value={form.recipient_address}
                  onChange={(e) => setForm({ ...form, recipient_address: e.target.value })}
                />
              </Field>

              <div>
                <Button type="submit" disabled={saving || !selected}>
                  {saving ? 'Sending…' : 'Send message'}
                </Button>
              </div>
            </form>
          </Card>
        </div>

        <Card title="Notes">
          <ul className="space-y-2 text-sm text-zinc-600">
            <li>The message respects the client&apos;s operational preferences; blocked channels are suppressed.</li>
            <li>Leave the recipient override empty to use the client&apos;s email or phone on file.</li>
            <li>Selecting a template pre-fills the subject and body — you can still edit before sending.</li>
          </ul>
        </Card>
      </div>
    </AdminNotificationsShell>
  );
}
