'use client';

import { useEffect, useState } from 'react';
import { Field, inputClass } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import {
  channelLabel,
  formatDateTime,
  NOTIFICATION_CHANNELS,
  type NotificationPreference,
} from '@/lib/notifications-types';

const CHANNEL_FLAGS: { key: keyof NotificationPreference; label: string }[] = [
  { key: 'allow_email', label: 'Allow email' },
  { key: 'allow_sms', label: 'Allow SMS' },
  { key: 'allow_whatsapp', label: 'Allow WhatsApp' },
  { key: 'allow_push', label: 'Allow push' },
];

const CATEGORY_FLAGS: { key: keyof NotificationPreference; label: string }[] = [
  { key: 'booking_notifications', label: 'Booking notifications' },
  { key: 'payment_notifications', label: 'Payment notifications' },
  { key: 'membership_notifications', label: 'Membership notifications' },
  { key: 'general_notifications', label: 'General notifications' },
];

interface NotificationPreferencesCardProps {
  preference: NotificationPreference;
  saving?: boolean;
  syncing?: boolean;
  onSave: (data: Partial<NotificationPreference>) => void | Promise<void>;
  onSync: () => void | Promise<void>;
}

export function NotificationPreferencesCard({
  preference,
  saving,
  syncing,
  onSave,
  onSync,
}: NotificationPreferencesCardProps) {
  const [form, setForm] = useState<NotificationPreference>(preference);

  useEffect(() => {
    setForm(preference);
  }, [preference]);

  return (
    <Card title="Operational communication preferences">
      <p className="mb-4 text-xs text-zinc-500">
        This is an operational projection used to gate reminders and operational messaging. Legal marketing consent
        remains in the client&apos;s CRM consent record and is never changed here.
      </p>
      <form
        className="grid gap-4"
        onSubmit={(e) => {
          e.preventDefault();
          void onSave({
            allow_email: form.allow_email,
            allow_sms: form.allow_sms,
            allow_whatsapp: form.allow_whatsapp,
            allow_push: form.allow_push,
            booking_notifications: form.booking_notifications,
            payment_notifications: form.payment_notifications,
            membership_notifications: form.membership_notifications,
            general_notifications: form.general_notifications,
            preferred_channel: form.preferred_channel ?? null,
          });
        }}
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <p className="mb-2 text-xs font-medium uppercase tracking-wide text-zinc-500">Channels</p>
            <div className="space-y-2">
              {CHANNEL_FLAGS.map((flag) => (
                <label key={flag.key} className="flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={Boolean(form[flag.key])}
                    onChange={(e) => setForm({ ...form, [flag.key]: e.target.checked })}
                  />
                  {flag.label}
                </label>
              ))}
            </div>
          </div>
          <div>
            <p className="mb-2 text-xs font-medium uppercase tracking-wide text-zinc-500">Categories</p>
            <div className="space-y-2">
              {CATEGORY_FLAGS.map((flag) => (
                <label key={flag.key} className="flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={Boolean(form[flag.key])}
                    onChange={(e) => setForm({ ...form, [flag.key]: e.target.checked })}
                  />
                  {flag.label}
                </label>
              ))}
            </div>
          </div>
        </div>

        <Field label="Preferred channel">
          <select
            className={`${inputClass} max-w-xs`}
            value={form.preferred_channel ?? ''}
            onChange={(e) => setForm({ ...form, preferred_channel: e.target.value || null })}
          >
            <option value="">No preference</option>
            {NOTIFICATION_CHANNELS.filter((c) => c !== 'internal_note').map((c) => (
              <option key={c} value={c}>
                {channelLabel(c)}
              </option>
            ))}
          </select>
        </Field>

        <p className="text-xs text-zinc-500">
          Last synced from consent: {formatDateTime(form.last_synced_from_consent_at)}
        </p>

        <div className="flex flex-wrap gap-2">
          <Button type="submit" disabled={saving}>
            {saving ? 'Saving…' : 'Save preferences'}
          </Button>
          <Button type="button" variant="secondary" disabled={syncing} onClick={() => void onSync()}>
            {syncing ? 'Syncing…' : 'Sync from CRM consent'}
          </Button>
        </div>
      </form>
    </Card>
  );
}
