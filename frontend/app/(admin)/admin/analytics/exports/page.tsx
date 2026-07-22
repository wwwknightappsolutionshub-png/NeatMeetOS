'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminAnalyticsShell } from '@/components/admin/analytics/AdminAnalyticsShell';
import { AnalyticsExportJobsTable } from '@/components/admin/analytics/AnalyticsExportJobsTable';
import { AnalyticsRunExportForm } from '@/components/admin/analytics/AnalyticsRunExportForm';
import { ErrorAlert, Field, inputClass } from '@/components/admin/ui';
import {
  ANALYTICS_REPORT_TYPES,
  exportStatusLabel,
  reportTypeLabel,
  type AnalyticsExportJob,
  type ExportCreatePayload,
  type ExportJobFilters,
} from '@/lib/analytics-types';
import {
  createAnalyticsExport,
  downloadAnalyticsExport,
  fetchAnalyticsExportJobs,
} from '@/services/analytics.service';

export default function AnalyticsExportsPage() {
  const [filters, setFilters] = useState<ExportJobFilters>({ report_type: '', status: '' });
  const [jobs, setJobs] = useState<AnalyticsExportJob[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [running, setRunning] = useState(false);
  const [downloadingId, setDownloadingId] = useState<string | null>(null);
  const [lastExport, setLastExport] = useState<AnalyticsExportJob | null>(null);

  const load = useCallback((f: ExportJobFilters) => {
    setLoading(true);
    setError(null);
    const params: ExportJobFilters = {};
    if (f.report_type) params.report_type = f.report_type;
    if (f.status) params.status = f.status;

    fetchAnalyticsExportJobs(params)
      .then(setJobs)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load export jobs'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load(filters);
  }, [filters, load]);

  async function handleRunExport(payload: ExportCreatePayload) {
    setRunning(true);
    setError(null);
    setLastExport(null);
    try {
      const job = await createAnalyticsExport(payload);
      setLastExport(job);
      load(filters);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to run export');
    } finally {
      setRunning(false);
    }
  }

  async function handleDownload(job: AnalyticsExportJob) {
    setDownloadingId(job.id);
    setError(null);
    try {
      await downloadAnalyticsExport(job);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to download export');
    } finally {
      setDownloadingId(null);
    }
  }

  return (
    <AdminAnalyticsShell title="Export jobs">
      <AnalyticsRunExportForm submitting={running} onSubmit={handleRunExport} />

      {error ? <div className="my-4"><ErrorAlert message={error} /></div> : null}

      {lastExport ? (
        <div className="my-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
          Export {exportStatusLabel(lastExport.status).toLowerCase()} — {reportTypeLabel(lastExport.report_type)}{' '}
          ({lastExport.export_format.toUpperCase()})
          {lastExport.status === 'completed' ? (
            <button
              type="button"
              className="ml-2 font-medium underline"
              onClick={() => handleDownload(lastExport)}
            >
              Download
            </button>
          ) : null}
        </div>
      ) : null}

      <div className="mt-8">
        <div className="mb-4 flex flex-wrap items-end justify-between gap-4">
          <h2 className="text-sm font-semibold">Export history</h2>
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Report type">
              <select
                className={inputClass}
                value={filters.report_type ?? ''}
                onChange={(e) => setFilters((prev) => ({ ...prev, report_type: e.target.value as ExportJobFilters['report_type'] }))}
              >
                <option value="">All types</option>
                {ANALYTICS_REPORT_TYPES.map((type) => (
                  <option key={type} value={type}>
                    {reportTypeLabel(type)}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Status">
              <select
                className={inputClass}
                value={filters.status ?? ''}
                onChange={(e) => setFilters((prev) => ({ ...prev, status: e.target.value as ExportJobFilters['status'] }))}
              >
                <option value="">All statuses</option>
                <option value="completed">Completed</option>
                <option value="failed">Failed</option>
                <option value="processing">Processing</option>
                <option value="pending">Pending</option>
              </select>
            </Field>
          </div>
        </div>

        {loading && jobs.length === 0 ? (
          <p className="text-sm text-zinc-500">Loading export jobs…</p>
        ) : (
          <AnalyticsExportJobsTable jobs={jobs} downloadingId={downloadingId} onDownload={handleDownload} />
        )}
      </div>
    </AdminAnalyticsShell>
  );
}
