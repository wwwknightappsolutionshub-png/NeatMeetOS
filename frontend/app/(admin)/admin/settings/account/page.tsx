'use client';

import { useEffect, useState } from 'react';
import { AdminSettingsShell } from '@/components/admin/AdminSettingsShell';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { TenantProfile } from '@/lib/identity-types';
import { fetchOrganization, updateOrganization } from '@/services/identity.service';

export default function AccountSettingsPage() {
  const [org, setOrg] = useState<TenantProfile | null>(null);
  const [form, setForm] = useState<Partial<TenantProfile>>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    fetchOrganization()
      .then((data) => {
        setOrg(data);
        setForm(data);
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, []);

  async function handleSave(event: React.FormEvent) {
    event.preventDefault();
    setSaving(true);
    setError(null);
    setSaved(false);
    try {
      const updated = await updateOrganization({
        name: form.name,
        trading_name: form.trading_name,
        business_type: form.business_type,
        timezone: form.timezone,
        contact_email: form.contact_email,
        contact_phone: form.contact_phone,
        status: form.status,
      });
      setOrg(updated);
      setSaved(true);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  return (
    <AdminSettingsShell title="Organization account">
      <Card title="Account profile">
        {loading ? <LoadingState /> : null}
        {error ? <ErrorAlert message={error} /> : null}
        {org ? (
          <form onSubmit={handleSave} className="grid max-w-xl gap-4">
            <Field label="Organization name">
              <input
                className={inputClass}
                value={form.name ?? ''}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                required
              />
            </Field>
            <Field label="Trading name">
              <input
                className={inputClass}
                value={form.trading_name ?? ''}
                onChange={(e) => setForm({ ...form, trading_name: e.target.value })}
              />
            </Field>
            <Field label="Business type">
              <input
                className={inputClass}
                value={form.business_type ?? ''}
                onChange={(e) => setForm({ ...form, business_type: e.target.value })}
                placeholder="boutique, solo, hybrid…"
              />
            </Field>
            <Field label="Timezone">
              <input
                className={inputClass}
                value={form.timezone ?? ''}
                onChange={(e) => setForm({ ...form, timezone: e.target.value })}
              />
            </Field>
            <Field label="Contact email">
              <input
                type="email"
                className={inputClass}
                value={form.contact_email ?? ''}
                onChange={(e) => setForm({ ...form, contact_email: e.target.value })}
              />
            </Field>
            <Field label="Contact phone">
              <input
                className={inputClass}
                value={form.contact_phone ?? ''}
                onChange={(e) => setForm({ ...form, contact_phone: e.target.value })}
              />
            </Field>
            <Field label="Status">
              <select
                className={inputClass}
                value={form.status ?? 'active'}
                onChange={(e) => setForm({ ...form, status: e.target.value })}
              >
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </Field>
            <div className="flex items-center gap-3">
              <Button type="submit" disabled={saving}>
                {saving ? 'Saving…' : 'Save changes'}
              </Button>
              {saved ? <span className="text-sm text-emerald-600">Saved</span> : null}
            </div>
          </form>
        ) : null}
      </Card>
    </AdminSettingsShell>
  );
}
