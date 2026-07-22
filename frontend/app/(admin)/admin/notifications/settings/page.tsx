'use client';

import { useEffect, useState } from 'react';
import { AdminNotificationsShell } from '@/components/admin/notifications/AdminNotificationsShell';
import { NotificationSettingsForm } from '@/components/admin/notifications/NotificationSettingsForm';
import { ErrorAlert } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import type { NotificationAutomationSetting } from '@/lib/notifications-types';
import { fetchNotificationSettings, updateNotificationSettings } from '@/services/notifications.service';

export default function NotificationSettingsPage() {
  const [settings, setSettings] = useState<NotificationAutomationSetting | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    fetchNotificationSettings()
      .then(setSettings)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load settings'));
  }, []);

  async function handleSave(data: Partial<NotificationAutomationSetting>) {
    setSaving(true);
    setError(null);
    setNotice(null);
    try {
      const updated = await updateNotificationSettings(data);
      setSettings(updated);
      setNotice('Settings saved.');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  return (
    <AdminNotificationsShell title="Notification settings">
      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}
      {notice ? (
        <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{notice}</div>
      ) : null}

      <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <strong>Tenant-level operational defaults.</strong> These control which transactional notifications are enabled
        and the default timings used by booking, payments and membership triggers.
      </div>

      {settings ? (
        <div className="max-w-3xl">
          <Card title="Operational notification settings">
            <NotificationSettingsForm settings={settings} saving={saving} onSave={handleSave} />
          </Card>
        </div>
      ) : (
        <p className="text-sm text-zinc-500">{error ?? 'Loading…'}</p>
      )}
    </AdminNotificationsShell>
  );
}
