'use client';

import Link from 'next/link';
import { IntegrationsStatusBadge } from '@/components/admin/integrations/IntegrationsStatusBadge';
import { EmptyState } from '@/components/admin/ui';
import {
  accountStatusLabel,
  accountStatusTone,
  categoryLabel,
  driverLabel,
  formatDateTime,
  testResultLabel,
  type ProviderAccount,
} from '@/lib/integrations-types';

interface ProviderAccountsTableProps {
  accounts: ProviderAccount[];
  onTest?: (id: string) => void;
  onArchive?: (id: string) => void;
  onSetDefault?: (id: string) => void;
  testingId?: string | null;
  actionId?: string | null;
}

export function ProviderAccountsTable({
  accounts,
  onTest,
  onArchive,
  onSetDefault,
  testingId,
  actionId,
}: ProviderAccountsTableProps) {
  if (accounts.length === 0) {
    return <EmptyState message="No provider accounts configured yet." />;
  }

  return (
    <div className="overflow-x-auto">
      <table className="w-full text-left text-sm">
        <thead>
          <tr className="border-b text-zinc-500">
            <th className="py-2 pr-2">Name</th>
            <th className="pr-2">Category</th>
            <th className="pr-2">Driver</th>
            <th className="pr-2">Status</th>
            <th className="pr-2">Default</th>
            <th className="pr-2">Last tested</th>
            <th className="pr-2">Actions</th>
          </tr>
        </thead>
        <tbody>
          {accounts.map((account) => (
            <tr key={account.id} className="border-b border-zinc-100">
              <td className="py-2 pr-2 font-medium">
                <Link href={`/admin/integrations/provider-accounts/${account.id}`} className="underline">
                  {account.name}
                </Link>
              </td>
              <td className="pr-2">{categoryLabel(account.category)}</td>
              <td className="pr-2">{driverLabel(account.driver)}</td>
              <td className="pr-2">
                <IntegrationsStatusBadge
                  label={accountStatusLabel(account.status)}
                  tone={accountStatusTone(account.status)}
                />
              </td>
              <td className="pr-2">{account.is_default ? 'Yes' : '—'}</td>
              <td className="pr-2 text-zinc-600">
                <div>{formatDateTime(account.last_tested_at)}</div>
                <div className="text-xs text-zinc-500">{testResultLabel(account.last_test_result)}</div>
              </td>
              <td className="pr-2">
                <div className="flex flex-wrap gap-2">
                  {onTest ? (
                    <button
                      type="button"
                      disabled={testingId === account.id || !!account.archived_at}
                      onClick={() => onTest(account.id)}
                      className="text-xs text-zinc-600 underline disabled:opacity-50"
                    >
                      {testingId === account.id ? 'Testing…' : 'Test'}
                    </button>
                  ) : null}
                  {onSetDefault && !account.archived_at && !account.is_default ? (
                    <button
                      type="button"
                      disabled={actionId === account.id}
                      onClick={() => onSetDefault(account.id)}
                      className="text-xs text-zinc-600 underline disabled:opacity-50"
                    >
                      Set default
                    </button>
                  ) : null}
                  {onArchive && !account.archived_at ? (
                    <button
                      type="button"
                      disabled={actionId === account.id}
                      onClick={() => onArchive(account.id)}
                      className="text-xs text-red-600 underline disabled:opacity-50"
                    >
                      Archive
                    </button>
                  ) : null}
                </div>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
