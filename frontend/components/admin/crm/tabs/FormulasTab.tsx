'use client';

import { useCallback, useEffect, useState } from 'react';
import { EmptyState, Field, inputClass } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { ClientFormula } from '@/lib/crm-types';
import { FORMULA_CATEGORIES } from '@/lib/crm-types';
import {
  archiveClientFormula,
  createClientFormula,
  fetchClientFormulas,
  updateClientFormula,
} from '@/services/crm.service';

interface FormulasTabProps {
  clientId: string;
  onChanged: () => void;
}

export function FormulasTab({ clientId, onChanged }: FormulasTabProps) {
  const [formulas, setFormulas] = useState<ClientFormula[]>([]);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState({
    title: '',
    formula_body: '',
    category: 'colour',
    service_context: '',
  });
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    fetchClientFormulas(clientId).then(setFormulas).catch(() => setFormulas([]));
  }, [clientId]);

  useEffect(() => {
    load();
  }, [load]);

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      if (editingId) {
        await updateClientFormula(clientId, editingId, form);
        setEditingId(null);
      } else {
        await createClientFormula(clientId, form);
      }
      setForm({ title: '', formula_body: '', category: 'colour', service_context: '' });
      load();
      onChanged();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed');
    }
  }

  function startEdit(formula: ClientFormula) {
    setEditingId(formula.id);
    setForm({
      title: formula.title,
      formula_body: formula.formula_body,
      category: formula.category ?? 'other',
      service_context: formula.service_context ?? '',
    });
  }

  async function handleArchive(id: string) {
    await archiveClientFormula(clientId, id);
    load();
    onChanged();
  }

  return (
    <div className="grid gap-4 md:grid-cols-2">
      <Card title={editingId ? 'Edit formula' : 'Add formula'}>
        {error ? <p className="mb-2 text-sm text-red-600">{error}</p> : null}
        <form onSubmit={handleSubmit} className="grid gap-3">
          <Field label="Title">
            <input className={inputClass} value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} required />
          </Field>
          <Field label="Category">
            <select className={inputClass} value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })}>
              {FORMULA_CATEGORIES.map((c) => (
                <option key={c} value={c}>{c}</option>
              ))}
            </select>
          </Field>
          <Field label="Service context">
            <input className={inputClass} value={form.service_context} onChange={(e) => setForm({ ...form, service_context: e.target.value })} />
          </Field>
          <Field label="Formula">
            <textarea className={inputClass} rows={5} value={form.formula_body} onChange={(e) => setForm({ ...form, formula_body: e.target.value })} required />
          </Field>
          <div className="flex gap-2">
            <Button type="submit">{editingId ? 'Update' : 'Add'}</Button>
            {editingId ? (
              <Button type="button" variant="secondary" onClick={() => setEditingId(null)}>Cancel</Button>
            ) : null}
          </div>
        </form>
      </Card>
      <Card title="Formulas">
        {formulas.length === 0 ? <EmptyState message="No formulas yet." /> : null}
        <ul className="divide-y divide-zinc-100">
          {formulas.map((f) => (
            <li key={f.id} className="py-3 text-sm">
              <p className="font-medium">{f.title}</p>
              <p className="text-xs text-zinc-500">{f.category} · {f.service_context ?? '—'}</p>
              <pre className="mt-1 whitespace-pre-wrap text-xs text-zinc-700">{f.formula_body}</pre>
              <div className="mt-2 flex gap-2">
                <Button type="button" variant="secondary" onClick={() => startEdit(f)}>Edit</Button>
                <Button type="button" variant="secondary" onClick={() => handleArchive(f.id)}>Archive</Button>
              </div>
            </li>
          ))}
        </ul>
      </Card>
    </div>
  );
}
