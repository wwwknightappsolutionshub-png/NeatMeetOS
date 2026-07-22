'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { AdminMarketingShell } from '@/components/admin/marketing/AdminMarketingShell';
import { EmailHtmlEditor, type EmailHtmlEditorHandle } from '@/components/admin/marketing/EmailHtmlEditor';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import {
  channelLabel,
  MARKETING_CHANNELS,
  TEMPLATE_PLACEHOLDERS,
  type MarketingTemplate,
} from '@/lib/marketing-types';
import {
  archiveMarketingTemplate,
  createMarketingTemplate,
  fetchMarketingTemplates,
  installMarketingSampleTemplates,
  previewMarketingTemplate,
  updateMarketingTemplate,
  type TemplatePreviewResult,
} from '@/services/marketing.service';

const CATEGORIES = [
  'broadcast',
  'booking_reminder',
  'rebooking_nudge',
  'win_back',
  'review_request',
  'membership_reminder',
  'birthday',
  'client_created',
];

interface FormState {
  name: string;
  category: string;
  channel: string;
  subject: string;
  body_text: string;
  body_html: string;
  is_active: boolean;
}

const emptyForm: FormState = {
  name: '',
  category: 'broadcast',
  channel: 'email',
  subject: '',
  body_text: '',
  body_html: '',
  is_active: true,
};

export default function MarketingTemplatesPage() {
  const [templates, setTemplates] = useState<MarketingTemplate[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [installing, setInstalling] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [preview, setPreview] = useState<TemplatePreviewResult | null>(null);
  const [previewError, setPreviewError] = useState<string | null>(null);
  const htmlEditorRef = useRef<EmailHtmlEditorHandle>(null);
  const bodyTextRef = useRef<HTMLTextAreaElement>(null);

  const load = useCallback(() => {
    setLoading(true);
    fetchMarketingTemplates()
      .then(setTemplates)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load templates'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  function resetForm() {
    setEditingId(null);
    setForm(emptyForm);
    setPreview(null);
    setPreviewError(null);
  }

  function startEdit(template: MarketingTemplate) {
    setEditingId(template.id);
    setForm({
      name: template.name,
      category: template.category ?? 'broadcast',
      channel: String(template.channel),
      subject: template.subject ?? '',
      body_text: template.body_text ?? '',
      body_html: template.body_html ?? '',
      is_active: template.is_active ?? true,
    });
    setPreview(null);
    setPreviewError(null);
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    try {
      const payload = {
        name: form.name,
        category: form.category,
        channel: form.channel,
        subject: form.channel === 'sms' || form.channel === 'whatsapp' ? null : form.subject || null,
        body_text: form.body_text,
        body_html: form.channel === 'email' && form.body_html ? form.body_html : null,
        is_active: form.is_active,
      };
      if (editingId) {
        await updateMarketingTemplate(editingId, payload);
      } else {
        await createMarketingTemplate(payload);
      }
      resetForm();
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  async function handlePreview() {
    if (!editingId) {
      setPreviewError('Save the template before previewing.');
      return;
    }
    setPreviewError(null);
    try {
      const result = await previewMarketingTemplate(editingId);
      setPreview(result);
    } catch (err) {
      setPreviewError(err instanceof Error ? err.message : 'Preview failed');
    }
  }

  async function handleInstallSamples() {
    setInstalling(true);
    setError(null);
    try {
      const result = await installMarketingSampleTemplates();
      if (result.created === 0) {
        setError(null);
      }
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to install samples');
    } finally {
      setInstalling(false);
    }
  }

  function insertPlaceholder(placeholder: string) {
    const token = `{{${placeholder}}}`;
    if (form.channel === 'email') {
      htmlEditorRef.current?.insertText(token);
      setForm((prev) => ({ ...prev, body_text: `${prev.body_text}${token}` }));
      return;
    }

    const el = bodyTextRef.current;
    if (el) {
      const start = el.selectionStart;
      const end = el.selectionEnd;
      const next = form.body_text.slice(0, start) + token + form.body_text.slice(end);
      setForm((prev) => ({ ...prev, body_text: next }));
      requestAnimationFrame(() => {
        el.focus();
        el.setSelectionRange(start + token.length, start + token.length);
      });
      return;
    }

    setForm((prev) => ({ ...prev, body_text: `${prev.body_text}${token}` }));
  }

  return (
    <AdminMarketingShell title="Message templates">
      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}

      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p className="max-w-2xl text-sm text-zinc-600">
          Create email and SMS templates with placeholders. Email HTML uses a visual editor — edit sample
          templates freely for booking reminders, reviews, win-back, memberships, and more.
        </p>
        <Button type="button" variant="secondary" disabled={installing} onClick={handleInstallSamples}>
          {installing ? 'Installing…' : 'Install sample emails'}
        </Button>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <Card title={editingId ? 'Edit template' : 'New template'}>
            <form onSubmit={handleSubmit} className="grid gap-3">
              <div className="grid gap-3 sm:grid-cols-2">
                <Field label="Name">
                  <input
                    className={inputClass}
                    value={form.name}
                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                    required
                  />
                </Field>
                <Field label="Channel">
                  <select
                    className={inputClass}
                    value={form.channel}
                    onChange={(e) => setForm({ ...form, channel: e.target.value })}
                  >
                    {MARKETING_CHANNELS.map((c) => (
                      <option key={c} value={c}>
                        {channelLabel(c)}
                      </option>
                    ))}
                  </select>
                </Field>
              </div>
              <Field label="Category">
                <select
                  className={inputClass}
                  value={form.category}
                  onChange={(e) => setForm({ ...form, category: e.target.value })}
                >
                  {CATEGORIES.map((c) => (
                    <option key={c} value={c}>
                      {c.replace(/_/g, ' ')}
                    </option>
                  ))}
                </select>
              </Field>
              {form.channel === 'email' || form.channel === 'push' || form.channel === 'in_app' ? (
                <Field label={form.channel === 'email' ? 'Subject' : 'Title'}>
                  <input
                    className={inputClass}
                    value={form.subject}
                    onChange={(e) => setForm({ ...form, subject: e.target.value })}
                  />
                </Field>
              ) : null}
              <Field label="Body (plain text)">
                <textarea
                  ref={bodyTextRef}
                  className={`${inputClass} min-h-28`}
                  value={form.body_text}
                  onChange={(e) => setForm({ ...form, body_text: e.target.value })}
                  required
                />
              </Field>
              {form.channel === 'email' ? (
                <Field label="Body (HTML)">
                  <EmailHtmlEditor
                    key={editingId ?? 'new'}
                    ref={htmlEditorRef}
                    value={form.body_html}
                    onChange={(body_html) => setForm((prev) => ({ ...prev, body_html }))}
                    placeholder="Design your email — use placeholders from the sidebar"
                  />
                </Field>
              ) : null}
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={form.is_active}
                  onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                />
                Active
              </label>
              <div className="flex flex-wrap gap-2">
                <Button type="submit" disabled={saving}>
                  {saving ? 'Saving…' : editingId ? 'Update template' : 'Create template'}
                </Button>
                <Button type="button" variant="secondary" onClick={handlePreview}>
                  Preview
                </Button>
                {editingId ? (
                  <Button type="button" variant="secondary" onClick={resetForm}>
                    Cancel edit
                  </Button>
                ) : null}
              </div>
            </form>

            {previewError ? <p className="mt-3 text-sm text-red-600">{previewError}</p> : null}
            {preview ? (
              <div className="mt-4 rounded-md border border-zinc-200 bg-zinc-50 p-3 text-sm">
                <p className="mb-1 text-xs font-medium uppercase tracking-wide text-zinc-500">
                  Rendered preview (sample data)
                </p>
                {preview.subject ? <p className="mb-2 font-medium">{preview.subject}</p> : null}
                {preview.body_html ? (
                  <div
                    className="prose prose-sm max-w-none rounded border border-zinc-200 bg-white p-3 text-zinc-700"
                    dangerouslySetInnerHTML={{ __html: preview.body_html }}
                  />
                ) : (
                  <p className="whitespace-pre-wrap text-zinc-700">{preview.body_text}</p>
                )}
              </div>
            ) : null}
          </Card>
        </div>

        <Card title="Placeholders">
          <p className="mb-3 text-xs text-zinc-500">
            Click to insert into the body. Tokens render with client and business data at send time.
          </p>
          <ul className="space-y-1">
            {TEMPLATE_PLACEHOLDERS.map((placeholder) => (
              <li key={placeholder}>
                <button
                  type="button"
                  onClick={() => insertPlaceholder(placeholder)}
                  className="w-full rounded-md border border-zinc-200 px-2 py-1 text-left text-xs font-mono text-zinc-700 hover:bg-zinc-50"
                >
                  {`{{${placeholder}}}`}
                </button>
              </li>
            ))}
          </ul>
        </Card>
      </div>

      <div className="mt-6">
        {loading ? (
          <LoadingState />
        ) : (
          <Card title="Templates">
            {templates.length === 0 ? (
              <p className="text-sm text-zinc-500">
                No templates yet. Use &quot;Install sample emails&quot; to add editable starters.
              </p>
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
                          <span className="ml-2 rounded bg-zinc-200 px-1.5 py-0.5 text-[10px] uppercase text-zinc-600">
                            System
                          </span>
                        ) : null}
                      </td>
                      <td>{channelLabel(String(template.channel))}</td>
                      <td className="text-zinc-600">{template.category?.replace(/_/g, ' ') ?? '—'}</td>
                      <td>{template.is_active ? 'Active' : 'Archived'}</td>
                      <td className="space-x-3 whitespace-nowrap">
                        {!template.is_system ? (
                          <button
                            type="button"
                            className="text-xs text-zinc-600 underline"
                            onClick={() => startEdit(template)}
                          >
                            Edit
                          </button>
                        ) : null}
                        {template.is_active && !template.is_system ? (
                          <button
                            type="button"
                            className="text-xs text-zinc-600 underline"
                            onClick={() =>
                              archiveMarketingTemplate(template.id)
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
    </AdminMarketingShell>
  );
}
