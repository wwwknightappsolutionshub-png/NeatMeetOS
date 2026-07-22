'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminInventoryShell } from '@/components/admin/inventory/AdminInventoryShell';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { InventorySupplier } from '@/lib/inventory-types';
import { createInventorySupplier, fetchInventorySuppliers, updateInventorySupplier } from '@/services/inventory.service';

export default function InventorySuppliersPage() {
  const [suppliers, setSuppliers] = useState<InventorySupplier[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');

  const load = useCallback(() => {
    setLoading(true);
    fetchInventorySuppliers().then(setSuppliers).catch((e) => setError(e instanceof Error ? e.message : 'Failed')).finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  return (
    <AdminInventoryShell title="Suppliers">
      {error ? <ErrorAlert message={error} /> : null}
      <div className="mb-4">
      <Card title="Add supplier">
        <form className="grid gap-2 md:grid-cols-3" onSubmit={async (e) => {
          e.preventDefault();
          await createInventorySupplier({ name, email: email || undefined });
          setName('');
          setEmail('');
          load();
        }}>
          <Field label="Name"><input className={inputClass} value={name} onChange={(e) => setName(e.target.value)} required /></Field>
          <Field label="Email"><input className={inputClass} type="email" value={email} onChange={(e) => setEmail(e.target.value)} /></Field>
          <div className="flex items-end"><Button type="submit">Create</Button></div>
        </form>
      </Card>
      </div>
      {loading ? <LoadingState /> : (
        <Card title="Suppliers">
          <ul className="space-y-2 text-sm">
            {suppliers.map((s) => (
              <li key={s.id} className="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-100 pb-2">
                <span>{s.name} {s.is_active ? '' : '(archived)'}</span>
                <span className="text-zinc-500">{s.email ?? '—'}</span>
                {s.is_active ? (
                  <Button type="button" variant="secondary" onClick={() => updateInventorySupplier(s.id, { is_active: false }).then(load)}>Archive</Button>
                ) : null}
              </li>
            ))}
          </ul>
        </Card>
      )}
    </AdminInventoryShell>
  );
}
