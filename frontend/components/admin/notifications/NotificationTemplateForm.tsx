'use client';

import { useState } from 'react';
import { Field, inputClass } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import {
  channelLabel,
  humanizeToken,
  NOTIFICATION_CATEGORIES,
  NOTIFICATION_CHANNELS,
  type NotificationTemplate,
} from '@/lib/notifications-types';

export interface NotificationTemplateFormValues {
  name: string;
  channel: string;
  category: string;
  subject: string;
  body_text: string;
  body_html: string;
  is_active: boolean;
}

export function templateToForm(template?: NotificationTemplate | null): NotificationTemplateFormValues {
  return {
    name: template?.name ?? '',
    channel: String(template?.channel ?? 'email'),
    category: String(template?.category ?? 'general'),
    subject: template?.subject ?? '',
    body_text: template?.body_text ?? '',
    body_html: template?.body_html ?? '',
    is_active: template?.is_active ?? true,
  };
}

interface NotificationTemplateFormProps {
  initial?: NotificationTemplate | null;
  submitLabel: string;
  disabled?: boolean;
  onSubmit: (values: NotificationTemplateFormValues) => void | Promise<void>;
  onCancel?: () => void;
}

export function NotificationTemplateForm({
  initial,
  submitLabel,
  disabled,
  onSubmit,
  onCancel,
}: NotificationTemplateFormProps) {
  const [form, setForm] = useState<NotificationTemplateFormValues>(templateToForm(initial));
  const isEmail = form.channel === 'email';

  return (
    <form
      className="grid gap-3"
      onSubmit={(e) => {
        e.preventDefault();
        void onSubmit(form);
      }}
    >
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
            {NOTIFICATION_CHANNELS.map((c) => (
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
          {NOTIFICATION_CATEGORIES.map((c) => (
            <option key={c} value={c}>
              {humanizeToken(c)}
            </option>
          ))}
        </select>
      </Field>
      {isEmail ? (
        <Field label="Subject">
          <input
            className={inputClass}
            value={form.subject}
            onChange={(e) => setForm({ ...form, subject: e.target.value })}
          />
        </Field>
      ) : null}
      <Field label="Body (plain text)">
        <textarea
          className={`${inputClass} min-h-32`}
          value={form.body_text}
          onChange={(e) => setForm({ ...form, body_text: e.target.value })}
        />
      </Field>
      {isEmail ? (
        <Field label="Body (HTML, optional)">
          <textarea
            className={`${inputClass} min-h-24 font-mono text-xs`}
            value={form.body_html}
            onChange={(e) => setForm({ ...form, body_html: e.target.value })}
          />
        </Field>
      ) : null}
      <p className="text-xs text-zinc-500">
        Variables such as <code className="font-mono">{'{{client_first_name}}'}</code> are rendered when the message is
        created.
      </p>
      <label className="flex items-center gap-2 text-sm">
        <input
          type="checkbox"
          checked={form.is_active}
          onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
        />
        Active
      </label>
      <div className="flex flex-wrap gap-2">
        <Button type="submit" disabled={disabled}>
          {disabled ? 'Saving…' : submitLabel}
        </Button>
        {onCancel ? (
          <Button type="button" variant="secondary" onClick={onCancel}>
            Cancel
          </Button>
        ) : null}
      </div>
    </form>
  );
}
