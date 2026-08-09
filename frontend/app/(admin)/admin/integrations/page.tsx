'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { AdminIntegrationsShell } from '@/components/admin/integrations/AdminIntegrationsShell';
import { IntegrationsOverviewCards } from '@/components/admin/integrations/IntegrationsOverviewCards';
import { ProviderAttemptsTable } from '@/components/admin/integrations/ProviderAttemptsTable';
import { ProviderWebhookEventsTable } from '@/components/admin/integrations/ProviderWebhookEventsTable';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import {
  buildIntegrationsOverview,
  INTEGRATIONS_LIST_WINDOW,
  type ProviderAccount,
  type ProviderDeliveryAttempt,
  type ProviderWebhookEvent,
} from '@/lib/integrations-types';
import {
  fetchProviderAccounts,
  fetchProviderAttempts,
  fetchProviderWebhookEvents,
} from '@/services/integrations.service';

const quickLinks = [
  {
    href: '/admin/integrations/whatsapp',
    label: 'Salon WhatsApp',
    description: 'Scan your number; platform API key powers delivery with salon fallback',
  },
  {
    href: '/admin/integrations/provider-accounts',
    label: 'Provider accounts',
    description: 'Configure email, SMS, payment gateway, and webhook providers',
  },
  {
    href: '/admin/integrations/provider-attempts',
    label: 'Delivery attempts',
    description: 'Cross-domain external traffic ledger',
  },
  {
    href: '/admin/integrations/provider-events',
    label: 'Webhook events',
    description: 'Inbound provider callback storage',
  },
];

export default function IntegrationsOverviewPage() {
  const [accounts, setAccounts] = useState<ProviderAccount[]>([]);
  const [attempts, setAttempts] = useState<ProviderDeliveryAttempt[]>([]);
  const [events, setEvents] = useState<ProviderWebhookEvent[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true);
    setError(null);
    Promise.all([
      fetchProviderAccounts({ archived: false }),
      fetchProviderAttempts(),
      fetchProviderWebhookEvents(),
    ])
      .then(([a, t, e]) => {
        setAccounts(a);
        setAttempts(t);
        setEvents(e);
      })
      .catch((err) => setError(err instanceof Error ? err.message : 'Failed to load integrations overview'))
      .finally(() => setLoading(false));
  }, []);

  const summary = buildIntegrationsOverview(accounts, attempts, events);
  const failedAttempts = attempts.filter((a) => a.status === 'failed').slice(0, 8);
  const recentEvents = events.slice(0, 8);

  return (
    <AdminIntegrationsShell title="Integrations overview">
      <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <strong>Provider integrations (Module 13A + 13B).</strong> Simulation remains the safe default. Mailgun, Twilio, and Stripe use stub live adapters when credentials are valid — no production SDK calls yet. Missing credentials or inactive accounts fall back to simulation. Webhook signature validation is deferred. Attempt and webhook counts use the latest {INTEGRATIONS_LIST_WINDOW} list rows.
      </div>

      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}
      {loading ? <LoadingState label="Loading integrations…" /> : null}

      {!loading ? (
        <>
          <IntegrationsOverviewCards summary={summary} />

          <div className="mt-6 grid gap-4 lg:grid-cols-3">
            {quickLinks.map((link) => (
              <Link key={link.href} href={link.href} className="block rounded-lg border border-zinc-200 bg-white p-4 hover:border-zinc-300">
                <p className="font-medium">{link.label}</p>
                <p className="mt-1 text-sm text-zinc-600">{link.description}</p>
              </Link>
            ))}
          </div>

          <div className="mt-6 grid gap-4 lg:grid-cols-2">
            <Card title="Recent failed attempts">
              <p className="mb-3 text-xs text-zinc-500">
                Shared ledger across Notifications, Marketing, and Payments. Domain message tables remain authoritative.
              </p>
              <ProviderAttemptsTable attempts={failedAttempts} compact />
              <Link href="/admin/integrations/provider-attempts?status=failed" className="mt-3 inline-block text-sm text-zinc-600 underline">
                View all attempts
              </Link>
            </Card>

            <Card title="Recent webhook events">
              <p className="mb-3 text-xs text-zinc-500">
                Append-only intake records. Processing and reconciliation are lightweight in this release.
              </p>
              <ProviderWebhookEventsTable events={recentEvents} compact />
              <Link href="/admin/integrations/provider-events" className="mt-3 inline-block text-sm text-zinc-600 underline">
                View all events
              </Link>
            </Card>
          </div>
        </>
      ) : null}
    </AdminIntegrationsShell>
  );
}
