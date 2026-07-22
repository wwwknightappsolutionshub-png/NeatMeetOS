'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminIntegrationsShell } from '@/components/admin/integrations/AdminIntegrationsShell';
import { ProviderAccountForm } from '@/components/admin/integrations/ProviderAccountForm';
import { ProviderAccountsTable } from '@/components/admin/integrations/ProviderAccountsTable';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Card } from '@/components/ui/Card';
import {
  PROVIDER_ACCOUNT_STATUSES,
  PROVIDER_CATEGORIES,
  type ProviderAccount,
  type ProviderAccountPayload,
} from '@/lib/integrations-types';
import {
  archiveProviderAccount,
  createProviderAccount,
  fetchProviderAccounts,
  setDefaultProviderAccount,
  testProviderAccount,
} from '@/services/integrations.service';

export default function ProviderAccountsPage() {
  const [category, setCategory] = useState('');
  const [status, setStatus] = useState('');
  const [accounts, setAccounts] = useState<ProviderAccount[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [creating, setCreating] = useState(false);
  const [testingId, setTestingId] = useState<string | null>(null);
  const [actionId, setActionId] = useState<string | null>(null);
  const [showCreate, setShowCreate] = useState(false);

  const load = useCallback(() => {
    setLoading(true);
    setError(null);
    const params: { category?: string; status?: string; archived?: boolean } = { archived: false };
    if (category) params.category = category;
    if (status) params.status = status;

    fetchProviderAccounts(params)
      .then(setAccounts)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load provider accounts'))
      .finally(() => setLoading(false));
  }, [category, status]);

  useEffect(() => {
    load();
  }, [load]);

  async function handleCreate(payload: ProviderAccountPayload) {
    setCreating(true);
    setError(null);
    try {
      await createProviderAccount(payload);
      setShowCreate(false);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to create account');
    } finally {
      setCreating(false);
    }
  }

  async function handleTest(id: string) {
    setTestingId(id);
    setError(null);
    try {
      await testProviderAccount(id);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Test failed');
    } finally {
      setTestingId(null);
    }
  }

  async function handleArchive(id: string) {
    if (!confirm('Archive this provider account? It will no longer be used for routing.')) return;
    setActionId(id);
    setError(null);
    try {
      await archiveProviderAccount(id);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to archive account');
    } finally {
      setActionId(null);
    }
  }

  async function handleSetDefault(id: string) {
    setActionId(id);
    setError(null);
    try {
      await setDefaultProviderAccount(id);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to set default');
    } finally {
      setActionId(null);
    }
  }

  return (
    <AdminIntegrationsShell title="Provider accounts">
      <div className="mb-4 rounded-md border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-600">
        Configure tenant provider accounts per category. Only one <strong>default</strong> active account is allowed
        per category. Simulation accounts are valid first-class providers in the current phase.
      </div>

      <div className="mb-4 flex flex-wrap items-end gap-4">
        <Field label="Category">
          <select className={inputClass} value={category} onChange={(e) => setCategory(e.target.value)}>
            <option value="">All</option>
            {PROVIDER_CATEGORIES.map((c) => (
              <option key={c} value={c}>{c}</option>
            ))}
          </select>
        </Field>
        <Field label="Status">
          <select className={inputClass} value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">All</option>
            {PROVIDER_ACCOUNT_STATUSES.filter((s) => s !== 'archived').map((s) => (
              <option key={s} value={s}>{s}</option>
            ))}
          </select>
        </Field>
        <button
          type="button"
          onClick={() => setShowCreate((v) => !v)}
          className="rounded-md bg-zinc-900 px-4 py-2 text-sm text-white hover:bg-zinc-800"
        >
          {showCreate ? 'Cancel' : 'New account'}
        </button>
      </div>

      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}

      {showCreate ? (
        <div className="mb-6">
          <Card title="Create provider account">
            <ProviderAccountForm submitting={creating} onSubmit={handleCreate} />
          </Card>
        </div>
      ) : null}

      <Card title="Accounts">
        {loading ? <LoadingState /> : (
          <ProviderAccountsTable
            accounts={accounts}
            onTest={handleTest}
            onArchive={handleArchive}
            onSetDefault={handleSetDefault}
            testingId={testingId}
            actionId={actionId}
          />
        )}
      </Card>
    </AdminIntegrationsShell>
  );
}
