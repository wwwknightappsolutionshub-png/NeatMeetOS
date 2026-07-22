'use client';

import { useCallback, useEffect, useState } from 'react';
import { EmptyState, Field, inputClass } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { ClientDocument } from '@/lib/crm-types';
import { DOCUMENT_TYPES } from '@/lib/crm-types';
import {
  archiveClientDocument,
  fetchClientDocuments,
  registerClientDocument,
} from '@/services/crm.service';

interface DocumentsTabProps {
  clientId: string;
  onChanged: () => void;
}

export function DocumentsTab({ clientId, onChanged }: DocumentsTabProps) {
  const [documents, setDocuments] = useState<ClientDocument[]>([]);
  const [form, setForm] = useState({
    title: '',
    storage_path: '',
    document_type: 'reference',
    description: '',
  });
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    fetchClientDocuments(clientId).then(setDocuments).catch(() => setDocuments([]));
  }, [clientId]);

  useEffect(() => {
    load();
  }, [load]);

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await registerClientDocument(clientId, form);
      setForm({ title: '', storage_path: '', document_type: 'reference', description: '' });
      load();
      onChanged();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Register failed');
    }
  }

  return (
    <div className="grid gap-4 md:grid-cols-2">
      <Card title="Register document">
        <p className="mb-3 text-xs text-zinc-500">
          Enter a storage path or URL for the document reference.
        </p>
        {error ? <p className="mb-2 text-sm text-red-600">{error}</p> : null}
        <form onSubmit={handleSubmit} className="grid gap-3">
          <Field label="Title">
            <input className={inputClass} value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} required />
          </Field>
          <Field label="Type">
            <select className={inputClass} value={form.document_type} onChange={(e) => setForm({ ...form, document_type: e.target.value })}>
              {DOCUMENT_TYPES.map((t) => (
                <option key={t} value={t}>{t}</option>
              ))}
            </select>
          </Field>
          <Field label="Storage path / URL">
            <input className={inputClass} value={form.storage_path} onChange={(e) => setForm({ ...form, storage_path: e.target.value })} required />
          </Field>
          <Field label="Description">
            <textarea className={inputClass} rows={2} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
          </Field>
          <Button type="submit">Register document</Button>
        </form>
      </Card>
      <Card title="Documents">
        {documents.length === 0 ? <EmptyState message="No documents yet." /> : null}
        <ul className="divide-y divide-zinc-100">
          {documents.map((d) => (
            <li key={d.id} className="flex items-start justify-between gap-2 py-3 text-sm">
              <div>
                <p className="font-medium">{d.title}</p>
                <p className="text-xs text-zinc-500">{d.document_type}</p>
                <p className="break-all text-xs text-zinc-500">{d.storage_path}</p>
              </div>
              <Button type="button" variant="secondary" onClick={async () => { await archiveClientDocument(clientId, d.id); load(); onChanged(); }}>Archive</Button>
            </li>
          ))}
        </ul>
      </Card>
    </div>
  );
}
