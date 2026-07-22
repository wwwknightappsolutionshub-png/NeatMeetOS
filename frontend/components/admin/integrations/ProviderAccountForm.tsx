'use client';

import { Field, inputClass } from '@/components/admin/ui';
import {
  PROVIDER_ACCOUNT_STATUSES,
  PROVIDER_CATEGORIES,
  categoryLabel,
  driverLabel,
  driversForCategory,
  isLiveDriver,
  type ProviderAccount,
  type ProviderAccountPayload,
} from '@/lib/integrations-types';
import { useEffect, useState, type FormEvent } from 'react';

interface ProviderAccountFormProps {
  initial?: Partial<ProviderAccount>;
  submitting?: boolean;
  onSubmit: (payload: ProviderAccountPayload) => void | Promise<void>;
}

const emptyForm = (): ProviderAccountPayload => ({
  name: '',
  category: 'email',
  driver: 'simulation',
  status: 'active',
  is_default: false,
  from_name: '',
  from_address: '',
  reply_to: '',
  phone_number: '',
  credentials: {},
  webhook_secret: '',
});

export function ProviderAccountForm({ initial, submitting, onSubmit }: ProviderAccountFormProps) {
  const [form, setForm] = useState<ProviderAccountPayload>(() => ({
    ...emptyForm(),
    name: initial?.name ?? '',
    category: initial?.category ?? 'email',
    driver: initial?.driver ?? 'simulation',
    status: initial?.status ?? 'active',
    is_default: initial?.is_default ?? false,
    from_name: initial?.from_name ?? '',
    from_address: initial?.from_address ?? '',
    reply_to: initial?.reply_to ?? '',
    phone_number: initial?.phone_number ?? '',
    credentials: {},
    webhook_secret: '',
  }));

  const driverOptions = driversForCategory(form.category);
  const liveDriver = isLiveDriver(form.driver);

  useEffect(() => {
    if (!driverOptions.includes(form.driver)) {
      setForm((f) => ({ ...f, driver: driverOptions[0] ?? 'simulation' }));
    }
  }, [form.category, form.driver, driverOptions]);

  function setCredential(key: string, value: string) {
    setForm((f) => ({
      ...f,
      credentials: { ...(f.credentials ?? {}), [key]: value },
    }));
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    const payload: ProviderAccountPayload = {
      name: form.name.trim(),
      category: form.category,
      driver: form.driver,
      status: form.status,
      is_default: form.is_default,
    };
    if (form.from_name) payload.from_name = form.from_name;
    if (form.from_address) payload.from_address = form.from_address;
    if (form.reply_to) payload.reply_to = form.reply_to;
    if (form.phone_number) payload.phone_number = form.phone_number;
    if (form.webhook_secret) payload.webhook_secret = form.webhook_secret;

    const creds = form.credentials ?? {};
    const hasNewCreds = Object.values(creds).some((v) => String(v ?? '').trim() !== '');
    if (hasNewCreds) payload.credentials = creds;

    await onSubmit(payload);
  }

  return (
    <form onSubmit={handleSubmit} className="grid gap-4 sm:grid-cols-2">
      <Field label="Name">
        <input
          className={inputClass}
          value={form.name}
          required
          onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
        />
      </Field>
      <Field label="Category">
        <select
          className={inputClass}
          value={form.category}
          onChange={(e) => setForm((f) => ({ ...f, category: e.target.value }))}
        >
          {PROVIDER_CATEGORIES.map((c) => (
            <option key={c} value={c}>{categoryLabel(c)}</option>
          ))}
        </select>
      </Field>
      <Field label="Driver">
        <select
          className={inputClass}
          value={form.driver}
          onChange={(e) => setForm((f) => ({ ...f, driver: e.target.value }))}
        >
          {driverOptions.map((d) => (
            <option key={d} value={d}>{driverLabel(d)}</option>
          ))}
        </select>
      </Field>
      <Field label="Status">
        <select
          className={inputClass}
          value={form.status ?? 'active'}
          onChange={(e) => setForm((f) => ({ ...f, status: e.target.value }))}
        >
          {PROVIDER_ACCOUNT_STATUSES.filter((s) => s !== 'archived').map((s) => (
            <option key={s} value={s}>{s}</option>
          ))}
        </select>
      </Field>
      <Field label="From name">
        <input
          className={inputClass}
          value={form.from_name ?? ''}
          onChange={(e) => setForm((f) => ({ ...f, from_name: e.target.value }))}
        />
      </Field>
      <Field label="From address">
        <input
          className={inputClass}
          value={form.from_address ?? ''}
          onChange={(e) => setForm((f) => ({ ...f, from_address: e.target.value }))}
        />
      </Field>
      <Field label="Reply-to">
        <input
          className={inputClass}
          value={form.reply_to ?? ''}
          onChange={(e) => setForm((f) => ({ ...f, reply_to: e.target.value }))}
        />
      </Field>
      <Field label="Phone number">
        <input
          className={inputClass}
          value={form.phone_number ?? ''}
          onChange={(e) => setForm((f) => ({ ...f, phone_number: e.target.value }))}
        />
      </Field>

      {liveDriver && form.driver === 'mailgun' ? (
        <>
          <Field label="Mailgun API key">
            <input
              type="password"
              className={inputClass}
              placeholder={initial?.has_credentials ? 'Leave blank to keep existing' : 'key-…'}
              onChange={(e) => setCredential('api_key', e.target.value)}
            />
          </Field>
          <Field label="Mailgun domain">
            <input
              className={inputClass}
              placeholder="mg.example.com"
              onChange={(e) => setCredential('domain', e.target.value)}
            />
          </Field>
        </>
      ) : null}

      {liveDriver && form.driver === 'twilio' ? (
        <>
          <Field label="Twilio Account SID">
            <input
              className={inputClass}
              placeholder="AC…"
              onChange={(e) => setCredential('account_sid', e.target.value)}
            />
          </Field>
          <Field label="Twilio Auth Token">
            <input
              type="password"
              className={inputClass}
              placeholder={initial?.has_credentials ? 'Leave blank to keep existing' : 'token'}
              onChange={(e) => setCredential('auth_token', e.target.value)}
            />
          </Field>
          <Field label="Twilio from number">
            <input
              className={inputClass}
              placeholder="+15551234567"
              onChange={(e) => setCredential('from_number', e.target.value)}
            />
          </Field>
        </>
      ) : null}

      {liveDriver && form.driver === 'stripe' ? (
        <>
          <Field label="Stripe secret key">
            <input
              type="password"
              className={inputClass}
              placeholder={initial?.has_credentials ? 'Leave blank to keep existing' : 'sk_test_…'}
              onChange={(e) => setCredential('secret_key', e.target.value)}
            />
          </Field>
          <Field label="Stripe publishable key (optional)">
            <input
              className={inputClass}
              placeholder="pk_test_…"
              onChange={(e) => setCredential('publishable_key', e.target.value)}
            />
          </Field>
          <Field label="Webhook signing secret (optional)">
            <input
              type="password"
              className={inputClass}
              placeholder="whsec_…"
              onChange={(e) => setForm((f) => ({ ...f, webhook_secret: e.target.value }))}
            />
          </Field>
        </>
      ) : null}

      <label className="flex items-center gap-2 text-sm sm:col-span-2">
        <input
          type="checkbox"
          checked={!!form.is_default}
          onChange={(e) => setForm((f) => ({ ...f, is_default: e.target.checked }))}
        />
        Set as default for this category (only one default per category per tenant)
      </label>
      <p className="text-xs text-zinc-500 sm:col-span-2">
        Live drivers use stub adapters in this release (no production SDK calls). Credentials are stored encrypted server-side and never returned in API responses.
        {liveDriver && !initial?.has_credentials ? ' Save credentials before expecting live routing — missing credentials fall back to simulation.' : ''}
      </p>
      <div className="sm:col-span-2">
        <button
          type="submit"
          disabled={submitting}
          className="rounded-md bg-zinc-900 px-4 py-2 text-sm text-white hover:bg-zinc-800 disabled:opacity-50"
        >
          {submitting ? 'Saving…' : (initial as ProviderAccount | undefined)?.id ? 'Update account' : 'Create account'}
        </button>
      </div>
    </form>
  );
}
