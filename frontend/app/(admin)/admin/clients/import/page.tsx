'use client';

import Link from 'next/link';
import { useMemo, useState } from 'react';
import { AdminCrmShell } from '@/components/admin/crm/AdminCrmShell';
import { ErrorAlert, Field, inputClass } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type {
  ClientImportMapping,
  ClientImportPreview,
  ClientImportResult,
  ClientImportTargetField,
} from '@/lib/crm-types';
import { previewClientImport, runClientImport } from '@/services/crm.service';

const FIELD_LABELS: Record<ClientImportTargetField, string> = {
  first_name: 'First name',
  last_name: 'Last name',
  name: 'Full name (optional if first name mapped)',
  email: 'Email',
  phone: 'Phone / WhatsApp',
};

const emptyMapping = (): ClientImportMapping => ({
  first_name: null,
  last_name: null,
  name: null,
  email: null,
  phone: null,
});

export default function ClientImportPage() {
  const [file, setFile] = useState<File | null>(null);
  const [preview, setPreview] = useState<ClientImportPreview | null>(null);
  const [mapping, setMapping] = useState<ClientImportMapping>(emptyMapping());
  const [grantPrivacy, setGrantPrivacy] = useState(true);
  const [grantMarketingEmail, setGrantMarketingEmail] = useState(false);
  const [grantMarketingSms, setGrantMarketingSms] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [result, setResult] = useState<ClientImportResult | null>(null);

  const canImport = useMemo(() => {
    if (!file || !preview) return false;
    return Boolean(mapping.phone);
  }, [file, preview, mapping]);

  async function handlePreview() {
    if (!file) {
      setError('Choose a CSV file first.');
      return;
    }
    setLoading(true);
    setError(null);
    setResult(null);
    try {
      const data = await previewClientImport(file);
      setPreview(data);
      setMapping({ ...emptyMapping(), ...data.suggested_mapping });
    } catch (e) {
      setPreview(null);
      setError(e instanceof Error ? e.message : 'Preview failed');
    } finally {
      setLoading(false);
    }
  }

  async function handleImport() {
    if (!file || !canImport) return;
    setLoading(true);
    setError(null);
    try {
      const data = await runClientImport({
        file,
        mapping,
        grant_privacy_contact: grantPrivacy,
        grant_marketing_email: grantMarketingEmail,
        grant_marketing_sms: grantMarketingSms,
      });
      setResult(data);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Import failed');
    } finally {
      setLoading(false);
    }
  }

  return (
    <AdminCrmShell title="Import clients">
      <div className="space-y-4">
        <Card title="CSV upload">
          <p className="mb-4 text-sm text-stone-600">
            Export contacts from your phone or WhatsApp backup as CSV (phone required; name and
            email optional), then map columns and import. Duplicates are skipped by phone within this
            salon. Maximum
            2,000 rows.
          </p>
          <div className="flex flex-wrap items-end gap-3">
            <Field label="CSV file">
              <input
                type="file"
                accept=".csv,text/csv,text/plain"
                className={inputClass}
                onChange={(e) => {
                  setFile(e.target.files?.[0] ?? null);
                  setPreview(null);
                  setResult(null);
                }}
              />
            </Field>
            <Button type="button" onClick={handlePreview} disabled={loading || !file}>
              {loading && !preview ? 'Reading…' : 'Preview & map columns'}
            </Button>
            <Link href="/admin/clients" className="text-sm font-medium text-[#2f5a45] underline">
              Back to clients
            </Link>
          </div>
        </Card>

        {error ? <ErrorAlert message={error} /> : null}

        {preview ? (
          <Card title={`Column mapping (${preview.row_count} rows)`}>
            <div className="grid gap-3 sm:grid-cols-2">
              {(Object.keys(FIELD_LABELS) as ClientImportTargetField[]).map((field) => (
                <Field key={field} label={FIELD_LABELS[field]}>
                  <select
                    className={inputClass}
                    value={mapping[field] ?? ''}
                    onChange={(e) =>
                      setMapping((prev) => ({
                        ...prev,
                        [field]: e.target.value || null,
                      }))
                    }
                  >
                    <option value="">— Not mapped —</option>
                    {preview.headers.map((header) => (
                      <option key={header} value={header}>
                        {header}
                      </option>
                    ))}
                  </select>
                </Field>
              ))}
            </div>

            <div className="mt-4 space-y-2 rounded-lg border border-stone-200 bg-white p-3 text-sm text-stone-700">
              <label className="flex items-start gap-2">
                <input
                  type="checkbox"
                  className="mt-1"
                  checked={grantPrivacy}
                  onChange={(e) => setGrantPrivacy(e.target.checked)}
                />
                <span>
                  Record <strong>privacy / contact</strong> consent as granted (source: import).
                  Recommended for existing salon clients you already serve.
                </span>
              </label>
              <label className="flex items-start gap-2">
                <input
                  type="checkbox"
                  className="mt-1"
                  checked={grantMarketingEmail}
                  onChange={(e) => setGrantMarketingEmail(e.target.checked)}
                />
                <span>
                  Record <strong>marketing email</strong> consent (only if you already have
                  permission).
                </span>
              </label>
              <label className="flex items-start gap-2">
                <input
                  type="checkbox"
                  className="mt-1"
                  checked={grantMarketingSms}
                  onChange={(e) => setGrantMarketingSms(e.target.checked)}
                />
                <span>
                  Record <strong>marketing SMS / WhatsApp</strong> consent (only if you already have
                  permission).
                </span>
              </label>
            </div>

            {preview.sample_rows.length > 0 ? (
              <div className="mt-4 overflow-x-auto">
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-stone-500">
                  Sample rows
                </p>
                <table className="min-w-full text-left text-sm">
                  <thead>
                    <tr className="border-b border-stone-200 text-stone-500">
                      {preview.headers.map((header) => (
                        <th key={header} className="px-2 py-1 font-medium">
                          {header}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {preview.sample_rows.map((row, i) => (
                      <tr key={i} className="border-b border-stone-100">
                        {preview.headers.map((header) => (
                          <td key={header} className="px-2 py-1 text-stone-800">
                            {row[header] || '—'}
                          </td>
                        ))}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : null}

            <div className="mt-4">
              <Button type="button" onClick={handleImport} disabled={loading || !canImport}>
                {loading ? 'Importing…' : 'Import clients'}
              </Button>
            </div>
          </Card>
        ) : null}

        {result ? (
          <Card title="Import result">
            <ul className="space-y-1 text-sm text-stone-700">
              <li>
                Created: <strong>{result.created}</strong>
              </li>
              <li>
                Skipped duplicates: <strong>{result.skipped_duplicates}</strong>
              </li>
              <li>
                Skipped invalid: <strong>{result.skipped_invalid}</strong>
              </li>
            </ul>
            {result.errors.length > 0 ? (
              <div className="mt-3 space-y-1 text-sm text-red-700">
                {result.errors.map((err) => (
                  <p key={`${err.row}-${err.reason}`}>
                    Row {err.row}: {err.reason}
                  </p>
                ))}
              </div>
            ) : null}
            <div className="mt-4">
              <Link href="/admin/clients">
                <Button type="button">View clients</Button>
              </Link>
            </div>
          </Card>
        ) : null}
      </div>
    </AdminCrmShell>
  );
}
