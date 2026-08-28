'use client';

import { useEffect, useState } from 'react';
import {
  PlatformButton,
  PlatformCard,
  PlatformErrorAlert,
  PlatformField,
  PlatformLoadingState,
  PlatformPage,
  PlatformPageIntro,
  PlatformSuccessAlert,
  platformInputClass,
} from '@/components/platform/ui';
import type { PlatformBroadcastResult, PlatformTenantRow } from '@/lib/types';
import {
  fetchPlatformTenants,
  sendPlatformBroadcast,
} from '@/services/platform.service';

export default function PlatformBroadcastsPage() {
  const [tenants, setTenants] = useState<PlatformTenantRow[]>([]);
  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [href, setHref] = useState('/admin/dashboard');
  const [tenantId, setTenantId] = useState('');
  const [sendEmail, setSendEmail] = useState(true);
  const [sendPush, setSendPush] = useState(true);
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [result, setResult] = useState<PlatformBroadcastResult | null>(null);

  useEffect(() => {
    fetchPlatformTenants()
      .then(setTenants)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load tenants'))
      .finally(() => setLoading(false));
  }, []);

  async function handleSend() {
    setSending(true);
    setError(null);
    setResult(null);
    try {
      const data = await sendPlatformBroadcast({
        title: title.trim(),
        body: body.trim(),
        href: href.trim() || '/admin/dashboard',
        tenant_id: tenantId || null,
        send_email: sendEmail,
        send_push: sendPush,
      });
      setResult(data);
      setTitle('');
      setBody('');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Broadcast failed');
    } finally {
      setSending(false);
    }
  }

  if (loading) {
    return <PlatformLoadingState label="Loading tenants…" />;
  }

  return (
    <PlatformPage width="2xl">
      <PlatformPageIntro
        title="Tenant broadcasts"
        description="Send reminders to all active salons or one tenant. Delivered as in-app owner notices, optional email, and Web Push when the admin app is installed."
      />

      {error ? <PlatformErrorAlert message={error} /> : null}
      {result ? (
        <PlatformSuccessAlert
          message={`Sent to ${result.tenants} tenant(s): ${result.notices} notices, ${result.emails} emails, ${result.pushes} pushes.`}
        />
      ) : null}

      <PlatformCard title="Compose">
        <div className="space-y-4">
          <PlatformField label="Audience">
            <select
              className={platformInputClass}
              value={tenantId}
              onChange={(e) => setTenantId(e.target.value)}
            >
              <option value="">All active tenants</option>
              {tenants.map((t) => (
                <option key={t.id} value={t.id}>
                  {t.name} ({t.slug})
                </option>
              ))}
            </select>
          </PlatformField>
          <PlatformField label="Title">
            <input
              className={platformInputClass}
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              maxLength={160}
              placeholder="Reminder from NeatMeet"
            />
          </PlatformField>
          <PlatformField label="Message">
            <textarea
              className={platformInputClass}
              rows={5}
              value={body}
              onChange={(e) => setBody(e.target.value)}
              maxLength={4000}
              placeholder="Short reminder for salon owners…"
            />
          </PlatformField>
          <PlatformField label="Deep link (admin path)">
            <input
              className={platformInputClass}
              value={href}
              onChange={(e) => setHref(e.target.value)}
            />
          </PlatformField>
          <div className="flex flex-wrap gap-5 text-sm text-[var(--platform-label)]">
            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                className="h-4 w-4 rounded border-stone-500"
                checked={sendEmail}
                onChange={(e) => setSendEmail(e.target.checked)}
              />
              Email owner
            </label>
            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                className="h-4 w-4 rounded border-stone-500"
                checked={sendPush}
                onChange={(e) => setSendPush(e.target.checked)}
              />
              Web Push (if subscribed)
            </label>
          </div>
          <PlatformButton
            disabled={sending || !title.trim() || !body.trim()}
            onClick={() => void handleSend()}
          >
            {sending ? 'Sending…' : 'Send broadcast'}
          </PlatformButton>
        </div>
      </PlatformCard>
    </PlatformPage>
  );
}
