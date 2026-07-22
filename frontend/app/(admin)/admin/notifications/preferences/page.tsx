'use client';

import { useEffect, useState } from 'react';
import { AdminNotificationsShell } from '@/components/admin/notifications/AdminNotificationsShell';
import { NotificationPreferencesCard } from '@/components/admin/notifications/NotificationPreferencesCard';
import { ErrorAlert, inputClass } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import type { Client } from '@/lib/crm-types';
import type { NotificationPreference } from '@/lib/notifications-types';
import { fetchClients } from '@/services/crm.service';
import {
  fetchClientNotificationPreferences,
  syncClientNotificationPreferencesFromConsent,
  updateClientNotificationPreferences,
} from '@/services/notifications.service';

export default function NotificationPreferencesPage() {
  const [search, setSearch] = useState('');
  const [clients, setClients] = useState<Client[]>([]);
  const [selected, setSelected] = useState<Client | null>(null);
  const [preference, setPreference] = useState<NotificationPreference | null>(null);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [syncing, setSyncing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

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

  function selectClient(client: Client) {
    setSelected(client);
    setClients([]);
    setSearch('');
    setPreference(null);
    setError(null);
    setNotice(null);
    setLoading(true);
    fetchClientNotificationPreferences(client.id)
      .then(setPreference)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load preferences'))
      .finally(() => setLoading(false));
  }

  async function handleSave(data: Partial<NotificationPreference>) {
    if (!selected) return;
    setSaving(true);
    setError(null);
    setNotice(null);
    try {
      const updated = await updateClientNotificationPreferences(selected.id, data);
      setPreference(updated);
      setNotice('Preferences saved.');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  async function handleSync() {
    if (!selected) return;
    setSyncing(true);
    setError(null);
    setNotice(null);
    try {
      const updated = await syncClientNotificationPreferencesFromConsent(selected.id);
      setPreference(updated);
      setNotice('Synced from CRM consent.');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Sync failed');
    } finally {
      setSyncing(false);
    }
  }

  return (
    <AdminNotificationsShell title="Communication preferences">
      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}
      {notice ? (
        <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{notice}</div>
      ) : null}

      <Card title="Select a client">
        {selected ? (
          <div className="flex items-center justify-between rounded-md border border-zinc-300 px-3 py-2 text-sm">
            <span>
              <span className="font-medium">{selected.resolved_display_name}</span>{' '}
              <span className="text-zinc-500">{selected.email ?? selected.phone ?? ''}</span>
            </span>
            <button
              type="button"
              className="text-xs text-zinc-600 underline"
              onClick={() => {
                setSelected(null);
                setPreference(null);
              }}
            >
              Change client
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
                      onClick={() => selectClient(client)}
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
      </Card>

      <div className="mt-6">
        {loading ? (
          <p className="text-sm text-zinc-500">Loading preferences…</p>
        ) : preference ? (
          <NotificationPreferencesCard
            preference={preference}
            saving={saving}
            syncing={syncing}
            onSave={handleSave}
            onSync={handleSync}
          />
        ) : selected ? null : (
          <p className="text-sm text-zinc-500">Search for and select a client to inspect their operational preferences.</p>
        )}
      </div>
    </AdminNotificationsShell>
  );
}
