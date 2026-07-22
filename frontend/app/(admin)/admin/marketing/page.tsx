'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { AdminMarketingShell } from '@/components/admin/marketing/AdminMarketingShell';
import { ErrorAlert } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import {
  channelLabel,
  formatDateTime,
  humanizeToken,
  statusTone,
  triggerLabel,
  type AutomationReportingSummary,
  type MarketingReportingSummary,
  type MarketingRun,
} from '@/lib/marketing-types';
import {
  fetchAutomationReportingSummary,
  fetchMarketingReportingSummary,
  fetchMarketingRuns,
} from '@/services/marketing.service';

const quickLinks = [
  {
    href: '/admin/marketing/templates',
    label: 'Templates',
    description: 'Edit the messages clients receive (email / SMS / WhatsApp / in-app)',
  },
  {
    href: '/admin/marketing/messages',
    label: 'Messages',
    description: 'See what was sent or skipped — your delivery log',
  },
  {
    href: '/admin/marketing/settings',
    label: 'Settings',
    description: 'Turn built-in automations on/off and set timing',
  },
  {
    href: '/admin/marketing/audiences',
    label: 'Audiences (advanced)',
    description: 'Build custom client lists for one-off or recurring sends',
  },
  {
    href: '/admin/marketing/campaigns',
    label: 'Campaigns (advanced)',
    description: 'Package a template + audience into a broadcast or automation',
  },
  {
    href: '/admin/marketing/runs',
    label: 'Runs (advanced)',
    description: 'Generate and dispatch a campaign batch',
  },
  {
    href: '/admin/marketing/workflows',
    label: 'Workflows (advanced)',
    description: 'Multi-step journeys triggered by events (e.g. no-show → win-back)',
  },
  {
    href: '/admin/marketing/executions',
    label: 'Executions (advanced)',
    description: 'Status of workflow journeys currently in progress',
  },
  {
    href: '/admin/marketing/suppressions',
    label: 'Suppressions (advanced)',
    description: 'Contacts who opted out or must never be messaged',
  },
];

function sumCounts(counts: Record<string, number> | undefined): number {
  if (!counts) return 0;
  return Object.values(counts).reduce((total, value) => total + (value ?? 0), 0);
}

export default function MarketingOverviewPage() {
  const [summary, setSummary] = useState<MarketingReportingSummary | null>(null);
  const [automation, setAutomation] = useState<AutomationReportingSummary | null>(null);
  const [runs, setRuns] = useState<MarketingRun[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchMarketingReportingSummary()
      .then(setSummary)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load summary'));
    fetchAutomationReportingSummary()
      .then(setAutomation)
      .catch(() => setAutomation(null));
    fetchMarketingRuns()
      .then((data) => setRuns(data.slice(0, 8)))
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load runs'));
  }, []);

  const totalMessages = sumCounts(summary?.messages);
  const sentMessages = summary?.messages?.sent ?? 0;
  const skippedMessages = summary?.messages?.skipped ?? 0;

  return (
    <AdminMarketingShell title="Marketing overview">
      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}

      <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
        <p className="font-medium">Busy-salon shortcut</p>
        <p className="mt-1 text-emerald-800/90">
          Most days you only need <strong>Templates</strong> (wording) and <strong>Settings</strong> (automations on/off).
          Welcome, birthday, win-back and membership reminders already run on a schedule. Open Advanced tools only when
          you need a custom audience or one-off campaign.
        </p>
      </div>

      <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <strong>Simulated dispatch (Module 10A).</strong> Messages are generated, rendered and marked as sent through a
        local simulation. No live email, SMS or WhatsApp is delivered until transport providers ship in a later module.
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card title="Active campaigns">
          <p className="text-2xl font-semibold">{summary?.campaigns.active ?? '—'}</p>
          <p className="mt-1 text-xs text-zinc-500">of {summary?.campaigns.total ?? 0} total</p>
        </Card>
        <Card title="Messages (30 days)">
          <p className="text-2xl font-semibold">{summary ? totalMessages : '—'}</p>
          <p className="mt-1 text-xs text-zinc-500">All statuses</p>
        </Card>
        <Card title="Sent (simulated)">
          <p className="text-2xl font-semibold">{summary ? sentMessages : '—'}</p>
          <p className="mt-1 text-xs text-zinc-500">{skippedMessages} skipped</p>
        </Card>
        <Card title="Runs (30 days)">
          <p className="text-2xl font-semibold">{summary ? sumCounts(summary.runs) : '—'}</p>
          <p className="mt-1 text-xs text-zinc-500">{summary?.runs?.completed ?? 0} completed</p>
        </Card>
      </div>

      <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card title="Active workflows">
          <p className="text-2xl font-semibold">{automation?.workflows.active ?? '—'}</p>
          <p className="mt-1 text-xs text-zinc-500">of {automation?.workflows.total ?? 0} total</p>
        </Card>
        <Card title="Executions (30 days)">
          <p className="text-2xl font-semibold">{automation ? sumCounts(automation.executions) : '—'}</p>
          <p className="mt-1 text-xs text-zinc-500">{automation?.executions?.completed ?? 0} completed</p>
        </Card>
        <Card title="Workflow messages (30 days)">
          <p className="text-2xl font-semibold">{automation ? sumCounts(automation.messages) : '—'}</p>
          <p className="mt-1 text-xs text-zinc-500">{automation?.messages?.delivered ?? 0} delivered</p>
        </Card>
        <Card title="Active suppressions">
          <p className="text-2xl font-semibold">{automation?.suppressions.active ?? '—'}</p>
          <p className="mt-1 text-xs text-zinc-500">of {automation?.suppressions.total ?? 0} total</p>
        </Card>
      </div>

      <div className="mt-6 grid gap-4 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <Card title="Recent runs">
            {runs.length === 0 ? (
              <p className="text-sm text-zinc-500">No runs yet. Generate automations or dispatch a broadcast to get started.</p>
            ) : (
              <table className="w-full text-left text-sm">
                <thead>
                  <tr className="border-b text-zinc-500">
                    <th className="py-2">Type</th>
                    <th>Campaign</th>
                    <th>Status</th>
                    <th>Messages</th>
                    <th>Created</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {runs.map((run) => (
                    <tr key={run.id} className="border-b border-zinc-100">
                      <td className="py-2">{run.trigger_type ? triggerLabel(run.trigger_type) : 'Broadcast'}</td>
                      <td className="text-zinc-600">{run.campaign?.name ?? '—'}</td>
                      <td>
                        <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusTone(run.status)}`}>
                          {humanizeToken(run.status)}
                        </span>
                      </td>
                      <td>{run.messages_count ?? run.summary?.total ?? '—'}</td>
                      <td className="text-zinc-500">{formatDateTime(run.created_at)}</td>
                      <td>
                        <Link href={`/admin/marketing/runs?run=${run.id}`} className="text-xs text-zinc-600 underline">
                          View
                        </Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </Card>
        </div>

        <div className="grid gap-4">
          <Card title="Channels (30 days)">
            {summary && Object.keys(summary.channels).length > 0 ? (
              <ul className="space-y-1 text-sm">
                {Object.entries(summary.channels).map(([channel, count]) => (
                  <li key={channel} className="flex justify-between">
                    <span className="text-zinc-600">{channelLabel(channel)}</span>
                    <span className="font-medium">{count}</span>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-sm text-zinc-500">No messages in the last 30 days.</p>
            )}
          </Card>
          <Card title="Quick links">
            <ul className="space-y-2">
              {quickLinks.map((link) => (
                <li key={link.href}>
                  <Link href={link.href} className="block rounded-md border border-zinc-200 px-3 py-2 hover:bg-zinc-50">
                    <span className="block text-sm font-medium">{link.label}</span>
                    <span className="block text-xs text-zinc-500">{link.description}</span>
                  </Link>
                </li>
              ))}
            </ul>
          </Card>
        </div>
      </div>
    </AdminMarketingShell>
  );
}
