'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useCallback, useEffect, useState } from 'react';
import { AdminIntegrationsShell } from '@/components/admin/integrations/AdminIntegrationsShell';
import { IntegrationsStatusBadge } from '@/components/admin/integrations/IntegrationsStatusBadge';
import { ProviderAccountForm } from '@/components/admin/integrations/ProviderAccountForm';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import {
  accountStatusLabel,
  accountStatusTone,
  accountNeedsCredentials,
  categoryLabel,
  driverLabel,
  formatDateTime,
  testResultLabel,
  testResultTone,
  type ProviderAccount,
  type ProviderAccountPayload,
} from '@/lib/integrations-types';
import {
  activateProviderAccount,
  archiveProviderAccount,
  deactivateProviderAccount,
  fetchProviderAccount,
  setDefaultProviderAccount,
  testProviderAccount,
  updateProviderAccount,
} from '@/services/integrations.service';

export default function ProviderAccountDetailPage() {
  const params = useParams();
  const accountId = params.accountId as string;

  const [account, setAccount] = useState<ProviderAccount | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [actionBusy, setActionBusy] = useState(false);

  const load = useCallback(() => {
    setLoading(true);
    setError(null);
    fetchProviderAccount(accountId)
      .then(setAccount)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load account'))
      .finally(() => setLoading(false));
  }, [accountId]);

  useEffect(() => {
    load();
  }, [load]);

  async function handleUpdate(payload: ProviderAccountPayload) {
    setSaving(true);
    setError(null);
    try {
      const updated = await updateProviderAccount(accountId, payload);
      setAccount(updated);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to update account');
    } finally {
      setSaving(false);
    }
  }

  async function runAction(action: () => Promise<ProviderAccount>) {
    setActionBusy(true);
    setError(null);
    try {
      const updated = await action();
      setAccount(updated);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Action failed');
    } finally {
      setActionBusy(false);
    }
  }

  return (
    <AdminIntegrationsShell title={account?.name ?? 'Provider account'}>
      <p className="mb-4">
        <Link href="/admin/integrations/provider-accounts" className="text-sm text-zinc-600 underline">
          ← Back to accounts
        </Link>
      </p>

      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}
      {loading && !account ? <LoadingState /> : null}

      {account ? (
        <>
          {accountNeedsCredentials(account) ? (
            <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
              This live driver account is missing credentials. Outbound dispatch will fall back to simulation until credentials are saved and the connection test passes.
            </div>
          ) : null}
          <div className="mb-6">
            <Card title="Account summary">
            <div className="flex flex-wrap items-center gap-3 text-sm">
              <IntegrationsStatusBadge
                label={accountStatusLabel(account.status)}
                tone={accountStatusTone(account.status)}
              />
              <span>{categoryLabel(account.category)} · {driverLabel(account.driver)}</span>
              {account.is_default ? (
                <span className="rounded-full bg-zinc-900 px-2 py-0.5 text-xs text-white">Default</span>
              ) : null}
              {account.archived_at ? (
                <span className="text-red-600">Archived {formatDateTime(account.archived_at)}</span>
              ) : null}
            </div>
            <dl className="mt-4 grid gap-2 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-zinc-500">Last tested</dt>
                <dd>{formatDateTime(account.last_tested_at)}</dd>
              </div>
              <div>
                <dt className="text-zinc-500">Test result</dt>
                <dd>
                  <IntegrationsStatusBadge
                    label={testResultLabel(account.last_test_result)}
                    tone={testResultTone(account.last_test_result)}
                  />
                </dd>
              </div>
              <div>
                <dt className="text-zinc-500">Credentials</dt>
                <dd>{account.has_credentials ? 'Configured (redacted)' : 'None'}</dd>
              </div>
              {account.config_summary ? (
                <div className="sm:col-span-2">
                  <dt className="text-zinc-500">Config summary</dt>
                  <dd className="mt-1 font-mono text-xs text-zinc-600">
                    {JSON.stringify(account.config_summary)}
                  </dd>
                </div>
              ) : null}
              <div>
                <dt className="text-zinc-500">Created</dt>
                <dd>{formatDateTime(account.created_at)}</dd>
              </div>
            </dl>
            {!account.archived_at ? (
              <div className="mt-4 flex flex-wrap gap-2">
                <button
                  type="button"
                  disabled={actionBusy}
                  onClick={() => runAction(() => testProviderAccount(account.id))}
                  className="rounded-md border border-zinc-300 px-3 py-1.5 text-sm hover:bg-zinc-50 disabled:opacity-50"
                >
                  Test connection
                </button>
                {!account.is_default ? (
                  <button
                    type="button"
                    disabled={actionBusy}
                    onClick={() => runAction(() => setDefaultProviderAccount(account.id))}
                    className="rounded-md border border-zinc-300 px-3 py-1.5 text-sm hover:bg-zinc-50 disabled:opacity-50"
                  >
                    Set as default
                  </button>
                ) : null}
                {account.status === 'active' || account.status === 'test_only' ? (
                  <button
                    type="button"
                    disabled={actionBusy}
                    onClick={() => runAction(() => deactivateProviderAccount(account.id))}
                    className="rounded-md border border-zinc-300 px-3 py-1.5 text-sm hover:bg-zinc-50 disabled:opacity-50"
                  >
                    Deactivate
                  </button>
                ) : (
                  <button
                    type="button"
                    disabled={actionBusy}
                    onClick={() => runAction(() => activateProviderAccount(account.id))}
                    className="rounded-md border border-zinc-300 px-3 py-1.5 text-sm hover:bg-zinc-50 disabled:opacity-50"
                  >
                    Activate
                  </button>
                )}
                <button
                  type="button"
                  disabled={actionBusy}
                  onClick={() => {
                    if (confirm('Archive this provider account?')) {
                      runAction(() => archiveProviderAccount(account.id));
                    }
                  }}
                  className="rounded-md border border-red-200 px-3 py-1.5 text-sm text-red-700 hover:bg-red-50 disabled:opacity-50"
                >
                  Archive
                </button>
              </div>
            ) : null}
            </Card>
          </div>

          {!account.archived_at ? (
            <Card title="Edit account">
              <ProviderAccountForm initial={account} submitting={saving} onSubmit={handleUpdate} />
            </Card>
          ) : null}
        </>
      ) : null}
    </AdminIntegrationsShell>
  );
}
