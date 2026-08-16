'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminPaymentsShell } from '@/components/admin/payments/AdminPaymentsShell';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { TenantPaymentsSettings } from '@/lib/payments-types';
import {
  fetchTenantPaymentsSettings,
  updateTenantPaymentsSettings,
} from '@/services/payments.service';

const empty: TenantPaymentsSettings = {
  bank_account_name: '',
  bank_name: '',
  bank_sort_code: '',
  bank_account_number: '',
  bank_iban: '',
  bank_reference_hint: '',
};

export default function PaymentsSettingsPage() {
  const [form, setForm] = useState<TenantPaymentsSettings>(empty);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  const load = useCallback(() => {
    setLoading(true);
    setError(null);
    fetchTenantPaymentsSettings()
      .then((data) =>
        setForm({
          bank_account_name: data.bank_account_name ?? '',
          bank_name: data.bank_name ?? '',
          bank_sort_code: data.bank_sort_code ?? '',
          bank_account_number: data.bank_account_number ?? '',
          bank_iban: data.bank_iban ?? '',
          bank_reference_hint: data.bank_reference_hint ?? '',
        }),
      )
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setSaving(true);
    setError(null);
    setSaved(false);
    try {
      const updated = await updateTenantPaymentsSettings({
        bank_account_name: form.bank_account_name || null,
        bank_name: form.bank_name || null,
        bank_sort_code: form.bank_sort_code || null,
        bank_account_number: form.bank_account_number || null,
        bank_iban: form.bank_iban || null,
        bank_reference_hint: form.bank_reference_hint || null,
      });
      setForm({
        bank_account_name: updated.bank_account_name ?? '',
        bank_name: updated.bank_name ?? '',
        bank_sort_code: updated.bank_sort_code ?? '',
        bank_account_number: updated.bank_account_number ?? '',
        bank_iban: updated.bank_iban ?? '',
        bank_reference_hint: updated.bank_reference_hint ?? '',
      });
      setSaved(true);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  return (
    <AdminPaymentsShell title="Bank details">
      {error ? <ErrorAlert message={error} /> : null}
      {loading ? (
        <LoadingState />
      ) : (
        <Card title="Transfer details for reservation fees">
          <p className="mb-4 text-sm text-zinc-500">
            Shown to customers when they pay the reservation fee by bank transfer. Provide either
            sort code + account number, or an IBAN.
          </p>
          <form onSubmit={handleSubmit} className="grid gap-3 sm:grid-cols-2">
            <Field label="Account name">
              <input
                className={inputClass}
                value={form.bank_account_name ?? ''}
                onChange={(e) => setForm({ ...form, bank_account_name: e.target.value })}
              />
            </Field>
            <Field label="Bank name">
              <input
                className={inputClass}
                value={form.bank_name ?? ''}
                onChange={(e) => setForm({ ...form, bank_name: e.target.value })}
              />
            </Field>
            <Field label="Sort code">
              <input
                className={inputClass}
                value={form.bank_sort_code ?? ''}
                onChange={(e) => setForm({ ...form, bank_sort_code: e.target.value })}
              />
            </Field>
            <Field label="Account number">
              <input
                className={inputClass}
                value={form.bank_account_number ?? ''}
                onChange={(e) => setForm({ ...form, bank_account_number: e.target.value })}
              />
            </Field>
            <Field label="IBAN (optional)">
              <input
                className={inputClass}
                value={form.bank_iban ?? ''}
                onChange={(e) => setForm({ ...form, bank_iban: e.target.value })}
              />
            </Field>
            <Field label="Payment reference hint">
              <input
                className={inputClass}
                value={form.bank_reference_hint ?? ''}
                onChange={(e) => setForm({ ...form, bank_reference_hint: e.target.value })}
                placeholder="e.g. Use your mobile number as reference"
              />
            </Field>
            <div className="flex items-center gap-3 sm:col-span-2">
              <Button type="submit" disabled={saving}>
                {saving ? 'Saving…' : 'Save bank details'}
              </Button>
              {saved ? <span className="text-sm text-emerald-700">Saved</span> : null}
            </div>
          </form>
        </Card>
      )}
    </AdminPaymentsShell>
  );
}
