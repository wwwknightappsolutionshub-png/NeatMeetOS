'use client';

import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import { useCallback, useEffect, useState } from 'react';
import { AdminNotificationsShell } from '@/components/admin/notifications/AdminNotificationsShell';
import {
  NotificationTemplateForm,
  type NotificationTemplateFormValues,
} from '@/components/admin/notifications/NotificationTemplateForm';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import type { NotificationTemplate } from '@/lib/notifications-types';
import {
  fetchNotificationTemplate,
  updateNotificationTemplate,
} from '@/services/notifications.service';

export default function NotificationTemplateEditPage() {
  const params = useParams();
  const router = useRouter();
  const templateId = params.templateId as string;
  const [template, setTemplate] = useState<NotificationTemplate | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    fetchNotificationTemplate(templateId)
      .then(setTemplate)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load template'))
      .finally(() => setLoading(false));
  }, [templateId]);

  useEffect(() => {
    load();
  }, [load]);

  async function handleSave(values: NotificationTemplateFormValues) {
    setSaving(true);
    setError(null);
    setNotice(null);
    try {
      const updated = await updateNotificationTemplate(templateId, {
        name: values.name,
        channel: values.channel,
        category: values.category,
        subject: values.channel === 'email' ? values.subject || null : null,
        body_text: values.body_text || null,
        body_html: values.channel === 'email' && values.body_html ? values.body_html : null,
        is_active: values.is_active,
      });
      setTemplate(updated);
      setNotice('Template saved.');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  if (loading && !template) {
    return (
      <AdminNotificationsShell title="Template">
        <LoadingState />
      </AdminNotificationsShell>
    );
  }

  return (
    <AdminNotificationsShell title={template?.is_system ? 'Template (system)' : 'Edit template'}>
      <p className="mb-4 text-sm">
        <Link href="/admin/notifications/templates" className="text-zinc-600 hover:underline">
          ← Back to templates
        </Link>
      </p>
      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}
      {notice ? (
        <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{notice}</div>
      ) : null}

      {template ? (
        <div className="max-w-2xl">
          <Card title={template.name}>
            <p className="mb-4 text-xs text-zinc-500">
              Slug: <code className="font-mono">{template.slug}</code>
            </p>
            {template.is_system ? (
              <div className="rounded-md border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600">
                System templates are managed by the platform and cannot be edited.
              </div>
            ) : (
              <NotificationTemplateForm
                initial={template}
                submitLabel="Save template"
                disabled={saving}
                onSubmit={handleSave}
                onCancel={() => router.push('/admin/notifications/templates')}
              />
            )}
          </Card>
        </div>
      ) : null}
    </AdminNotificationsShell>
  );
}
