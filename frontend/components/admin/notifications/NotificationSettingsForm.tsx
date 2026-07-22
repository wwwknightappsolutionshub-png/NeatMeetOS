'use client';

import { useState } from 'react';
import { Field, inputClass } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import type { NotificationAutomationSetting } from '@/lib/notifications-types';

const TOGGLES: { key: keyof NotificationAutomationSetting; label: string }[] = [
  { key: 'booking_confirmation_enabled', label: 'Booking confirmations enabled' },
  { key: 'booking_reminders_enabled', label: 'Booking reminders enabled' },
  { key: 'cancellation_notifications_enabled', label: 'Cancellation notifications enabled' },
  { key: 'payment_link_notifications_enabled', label: 'Payment link notifications enabled' },
  { key: 'payment_reminders_enabled', label: 'Payment reminders enabled' },
  { key: 'membership_expiry_notifications_enabled', label: 'Membership expiry notifications enabled' },
  { key: 'membership_renewal_notifications_enabled', label: 'Membership renewal notifications enabled' },
];

interface NotificationSettingsFormProps {
  settings: NotificationAutomationSetting;
  saving?: boolean;
  onSave: (data: Partial<NotificationAutomationSetting>) => void | Promise<void>;
}

export function NotificationSettingsForm({ settings, saving, onSave }: NotificationSettingsFormProps) {
  const [form, setForm] = useState<NotificationAutomationSetting>(settings);

  function updateNumber(key: keyof NotificationAutomationSetting, value: string) {
    const parsed = parseInt(value, 10);
    setForm((prev) => ({ ...prev, [key]: Number.isFinite(parsed) ? parsed : null }));
  }

  return (
    <form
      className="grid gap-5"
      onSubmit={(e) => {
        e.preventDefault();
        void onSave(form);
      }}
    >
      <div>
        <p className="mb-2 text-xs font-medium uppercase tracking-wide text-zinc-500">Notification types</p>
        <div className="grid gap-2 sm:grid-cols-2">
          {TOGGLES.map((toggle) => (
            <label key={toggle.key} className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={Boolean(form[toggle.key])}
                onChange={(e) => setForm({ ...form, [toggle.key]: e.target.checked })}
              />
              {toggle.label}
            </label>
          ))}
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <Field label="Booking reminder — minutes before (ops)">
          <input
            type="number"
            min={1}
            max={10080}
            className={inputClass}
            value={form.default_booking_reminder_minutes ?? ''}
            onChange={(e) => updateNumber('default_booking_reminder_minutes', e.target.value)}
          />
        </Field>
        <Field label="Booking reminder — hours before (legacy)">
          <input
            type="number"
            min={0}
            max={2160}
            className={inputClass}
            value={form.default_booking_reminder_hours ?? ''}
            onChange={(e) => updateNumber('default_booking_reminder_hours', e.target.value)}
          />
        </Field>
        <Field label="Default payment reminder — days">
          <input
            type="number"
            min={0}
            max={365}
            className={inputClass}
            value={form.default_payment_reminder_days ?? ''}
            onChange={(e) => updateNumber('default_payment_reminder_days', e.target.value)}
          />
        </Field>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <Field label="Sender name">
          <input
            className={inputClass}
            value={form.sender_name ?? ''}
            onChange={(e) => setForm({ ...form, sender_name: e.target.value })}
          />
        </Field>
        <Field label="Sender email">
          <input
            type="email"
            className={inputClass}
            value={form.sender_email ?? ''}
            onChange={(e) => setForm({ ...form, sender_email: e.target.value })}
          />
        </Field>
        <Field label="Sender SMS name">
          <input
            className={inputClass}
            value={form.sender_sms_name ?? ''}
            onChange={(e) => setForm({ ...form, sender_sms_name: e.target.value })}
          />
        </Field>
      </div>

      <div>
        <Button type="submit" disabled={saving}>
          {saving ? 'Saving…' : 'Save settings'}
        </Button>
      </div>
    </form>
  );
}
