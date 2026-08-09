'use client';

import { FormEvent, useEffect, useState } from 'react';
import { AdminSettingsShell } from '@/components/admin/AdminSettingsShell';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import {
  activateTenantWhatsAppSession,
  disconnectTenantWhatsAppSession,
  fetchTenantWhatsAppSettings,
  initTenantWhatsAppSession,
  qrImageUrl,
  refreshTenantWhatsAppSession,
  type TenantWhatsAppSettings,
} from '@/services/tenant-whatsapp.service';

export default function TenantWhatsAppSettingsPage() {
  const [settings, setSettings] = useState<TenantWhatsAppSettings | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [phone, setPhone] = useState('');
  const [note, setNote] = useState<string | null>(null);

  async function reload() {
    const next = await fetchTenantWhatsAppSettings();
    setSettings(next);
    if (next.hosted_session.phone_number) {
      setPhone(next.hosted_session.phone_number);
    }
  }

  useEffect(() => {
    setLoading(true);
    reload()
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load WhatsApp settings'))
      .finally(() => setLoading(false));
  }, []);

  async function run(action: () => Promise<TenantWhatsAppSettings>, success: string) {
    setBusy(true);
    setError(null);
    setNote(null);
    try {
      const next = await action();
      setSettings(next);
      setNote(success);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Action failed');
    } finally {
      setBusy(false);
    }
  }

  async function handleActivate(e: FormEvent) {
    e.preventDefault();
    await run(() => activateTenantWhatsAppSession(phone.trim()), 'Tenant WhatsApp session activated');
  }

  const hosted = settings?.hosted_session;
  const qrPayload = hosted?.qr_payload;

  return (
    <AdminSettingsShell title="Salon WhatsApp">
      <p className="mb-4 text-sm text-[var(--admin-muted)]">
        Scan your salon WhatsApp number into Genius. The platform still supplies the API key; your
        scanned session is used for outbound booking messages. If you disconnect, NeatMeet falls
        back to the platform WhatsApp number.
      </p>

      {error ? (
        <div className="mb-4">
          <ErrorAlert message={error} />
        </div>
      ) : null}
      {note ? (
        <p className="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
          {note}
        </p>
      ) : null}
      {loading || !settings ? (
        <LoadingState label="Loading WhatsApp…" />
      ) : (
        <div className="grid gap-4 lg:grid-cols-2">
          <Card className="space-y-3 p-5">
            <h2 className="text-sm font-semibold tracking-tight">Status</h2>
            <dl className="space-y-2 text-sm">
              <div className="flex justify-between gap-3">
                <dt className="text-[var(--admin-muted)]">Active source</dt>
                <dd className="font-medium capitalize">{settings.active_source}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-[var(--admin-muted)]">Ready to send</dt>
                <dd className="font-medium">{settings.ready ? 'Yes' : 'No'}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-[var(--admin-muted)]">Platform API</dt>
                <dd className="font-medium">
                  {settings.platform_api_configured ? 'Configured' : 'Missing'}
                </dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-[var(--admin-muted)]">Session status</dt>
                <dd className="font-medium">{hosted?.status ?? 'inactive'}</dd>
              </div>
              {hosted?.phone_number ? (
                <div className="flex justify-between gap-3">
                  <dt className="text-[var(--admin-muted)]">Salon number</dt>
                  <dd className="font-medium">{hosted.phone_number}</dd>
                </div>
              ) : null}
              {hosted?.expires_at ? (
                <div className="flex justify-between gap-3">
                  <dt className="text-[var(--admin-muted)]">Expires</dt>
                  <dd className="font-medium">
                    {new Date(hosted.expires_at).toLocaleDateString()}
                    {hosted.remaining_days != null ? ` (${hosted.remaining_days}d)` : ''}
                  </dd>
                </div>
              ) : null}
            </dl>
            {settings.using_platform_fallback ? (
              <p className="text-xs text-amber-800">
                Currently using the platform WhatsApp configuration as fallback.
              </p>
            ) : (
              <p className="text-xs text-emerald-800">
                Outbound WhatsApp uses this salon&apos;s scanned session with the platform API key.
              </p>
            )}
            <div className="flex flex-wrap gap-2 pt-1">
              <Button
                type="button"
                disabled={busy}
                onClick={() =>
                  void run(initTenantWhatsAppSession, 'Scan session created — scan the QR code')
                }
              >
                {hosted?.status === 'pending_scan' ? 'Regenerate QR' : 'Start scan'}
              </Button>
              {hosted?.status === 'active' ? (
                <>
                  <Button
                    type="button"
                    variant="secondary"
                    disabled={busy}
                    onClick={() => void run(refreshTenantWhatsAppSession, 'Session renewed for 30 days')}
                  >
                    Renew 30 days
                  </Button>
                  <Button
                    type="button"
                    variant="secondary"
                    disabled={busy}
                    onClick={() =>
                      void run(
                        disconnectTenantWhatsAppSession,
                        'Disconnected — platform fallback active',
                      )
                    }
                  >
                    Disconnect
                  </Button>
                </>
              ) : null}
            </div>
          </Card>

          <Card className="space-y-3 p-5">
            <h2 className="text-sm font-semibold tracking-tight">Scan &amp; activate</h2>
            {qrPayload ? (
              <div className="flex flex-col items-start gap-3 sm:flex-row">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src={qrImageUrl(qrPayload)}
                  alt="WhatsApp session QR"
                  className="h-[220px] w-[220px] rounded-lg border border-[var(--admin-line)] bg-white p-2"
                />
                <div className="space-y-2 text-xs text-[var(--admin-muted)]">
                  <p>1. Open your Genius WhatsApp gateway and scan this QR / session.</p>
                  <p>2. Enter the salon WhatsApp number below and activate.</p>
                  <p className="break-all font-mono text-[11px] text-[var(--admin-ink)]">
                    {hosted?.session_id}
                  </p>
                </div>
              </div>
            ) : (
              <p className="text-sm text-[var(--admin-muted)]">
                Click <strong>Start scan</strong> to generate a QR payload for your Genius session.
              </p>
            )}

            <form onSubmit={handleActivate} className="space-y-3 border-t border-[var(--admin-line)] pt-3">
              <label className="block text-sm">
                <span className="mb-1 block font-medium">Salon WhatsApp number (E.164)</span>
                <input
                  className="w-full rounded-lg border border-[var(--admin-line)] px-3 py-2 text-sm"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="+447700900123"
                  required
                />
              </label>
              <Button type="submit" disabled={busy || !phone.trim() || !hosted?.session_id}>
                Activate scanned session
              </Button>
            </form>
          </Card>
        </div>
      )}
    </AdminSettingsShell>
  );
}
