'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { AdminNotificationsShell } from '@/components/admin/notifications/AdminNotificationsShell';
import {
  NotificationTemplateForm,
  type NotificationTemplateFormValues,
} from '@/components/admin/notifications/NotificationTemplateForm';
import { NotificationChannelBadge } from '@/components/admin/notifications/badges';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import {
  humanizeToken,
  NOTIFICATION_CATEGORIES,
  NOTIFICATION_CHANNELS,
  channelLabel,
  type NotificationTemplate,
} from '@/lib/notifications-types';
import {
  archiveNotificationTemplate,
  createNotificationTemplate,
  fetchNotificationTemplates,
  installNotificationSampleTemplates,
} from '@/services/notifications.service';

export default function NotificationTemplatesPage() {
  const [templates, setTemplates] = useState<NotificationTemplate[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [installing, setInstalling] = useState(false);
  const [showForm, setShowForm] = useState(false);
  const [filters, setFilters] = useState({ channel: '', category: '', active: '', system: '' });

  const load = useCallback(() => {
    setLoading(true);
    fetchNotificationTemplates({
      channel: filters.channel || undefined,
      category: filters.category || undefined,
      is_active: filters.active === '' ? undefined : filters.active === 'true',
      is_system: filters.system === '' ? undefined : filters.system === 'true',
    })
      .then(setTemplates)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load templates'))
      .finally(() => setLoading(false));
  }, [filters]);

  useEffect(() => {
    load();
  }, [load]);

  async function handleCreate(values: NotificationTemplateFormValues) {
    setSaving(true);
    setError(null);
    try {
      await createNotificationTemplate({
        name: values.name,
        channel: values.channel,
        category: values.category,
        subject: values.channel === 'email' ? values.subject || null : null,
        body_text: values.body_text || null,
        body_html: values.channel === 'email' && values.body_html ? values.body_html : null,
        is_active: values.is_active,
      });
      setShowForm(false);
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  async function handleInstallSamples() {
    setInstalling(true);
    setError(null);
    try {
      await installNotificationSampleTemplates();
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Install failed');
    } finally {
      setInstalling(false);
    }
  }

  return (
    <AdminNotificationsShell title="Templates">
      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}

      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-zinc-500">Reusable operational copy for booking, payments, membership and general notices.</p>
        <div className="flex flex-wrap items-center gap-2">
          <button
            type="button"
            className="inline-flex items-center justify-center rounded-md border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-800 hover:bg-zinc-50 disabled:opacity-50"
            disabled={installing}
            onClick={() => void handleInstallSamples()}
          >
            {installing ? 'Installing…' : 'Install sample templates'}
          </button>
          <button
            type="button"
            className="inline-flex items-center justify-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800"
            onClick={() => setShowForm((v) => !v)}
          >
            {showForm ? 'Close' : 'New template'}
          </button>
        </div>
      </div>

      {showForm ? (
        <div className="mb-6">
          <Card title="New template">
            <NotificationTemplateForm
              submitLabel="Create template"
              disabled={saving}
              onSubmit={handleCreate}
              onCancel={() => setShowForm(false)}
            />
          </Card>
        </div>
      ) : null}

      <Card title="Filters">
        <div className="flex flex-wrap items-end gap-3">
          <Field label="Channel">
            <select className={`${inputClass} w-36`} value={filters.channel} onChange={(e) => setFilters({ ...filters, channel: e.target.value })}>
              <option value="">All channels</option>
              {NOTIFICATION_CHANNELS.map((c) => (
                <option key={c} value={c}>{channelLabel(c)}</option>
              ))}
            </select>
          </Field>
          <Field label="Category">
            <select className={`${inputClass} w-36`} value={filters.category} onChange={(e) => setFilters({ ...filters, category: e.target.value })}>
              <option value="">All categories</option>
              {NOTIFICATION_CATEGORIES.map((c) => (
                <option key={c} value={c}>{humanizeToken(c)}</option>
              ))}
            </select>
          </Field>
          <Field label="Active">
            <select className={`${inputClass} w-32`} value={filters.active} onChange={(e) => setFilters({ ...filters, active: e.target.value })}>
              <option value="">Any</option>
              <option value="true">Active</option>
              <option value="false">Archived</option>
            </select>
          </Field>
          <Field label="System">
            <select className={`${inputClass} w-32`} value={filters.system} onChange={(e) => setFilters({ ...filters, system: e.target.value })}>
              <option value="">Any</option>
              <option value="true">System</option>
              <option value="false">Custom</option>
            </select>
          </Field>
        </div>
      </Card>

      <div className="mt-6">
        {loading ? (
          <LoadingState />
        ) : (
          <Card title="Templates">
            {templates.length === 0 ? (
              <p className="text-sm text-zinc-500">No templates match the current filters.</p>
            ) : (
              <table className="w-full text-left text-sm">
                <thead>
                  <tr className="border-b text-zinc-500">
                    <th className="py-2">Name</th>
                    <th>Channel</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {templates.map((template) => (
                    <tr key={template.id} className="border-b border-zinc-100">
                      <td className="py-2 font-medium">
                        {template.name}
                        {template.is_system ? (
                          <span className="ml-2 rounded bg-zinc-200 px-1.5 py-0.5 text-[10px] uppercase text-zinc-600">System</span>
                        ) : null}
                      </td>
                      <td><NotificationChannelBadge channel={template.channel} /></td>
                      <td className="text-zinc-600">{humanizeToken(String(template.category))}</td>
                      <td>{template.is_active ? 'Active' : 'Archived'}</td>
                      <td className="space-x-3 whitespace-nowrap">
                        <Link href={`/admin/notifications/templates/${template.id}`} className="text-xs text-zinc-600 underline">
                          {template.is_system ? 'View' : 'Edit'}
                        </Link>
                        {template.is_active && !template.is_system ? (
                          <button
                            type="button"
                            className="text-xs text-zinc-600 underline"
                            onClick={() =>
                              archiveNotificationTemplate(template.id)
                                .then(load)
                                .catch((e) => setError(e instanceof Error ? e.message : 'Archive failed'))
                            }
                          >
                            Archive
                          </button>
                        ) : null}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </Card>
        )}
      </div>
    </AdminNotificationsShell>
  );
}
