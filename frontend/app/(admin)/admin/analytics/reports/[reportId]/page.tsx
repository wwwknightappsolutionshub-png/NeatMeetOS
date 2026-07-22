'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useCallback, useEffect, useState } from 'react';
import { AdminAnalyticsShell } from '@/components/admin/analytics/AdminAnalyticsShell';
import { AnalyticsSavedReportForm } from '@/components/admin/analytics/AnalyticsSavedReportForm';
import { AnalyticsStatusBadge } from '@/components/admin/analytics/AnalyticsStatusBadge';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import {
  exportFormatLabel,
  formatDateTime,
  reportTypeLabel,
  scheduleSummary,
  type AnalyticsExportJob,
  type AnalyticsSavedReport,
  type SavedReportPayload,
} from '@/lib/analytics-types';
import {
  archiveAnalyticsSavedReport,
  downloadAnalyticsExport,
  fetchAnalyticsSavedReport,
  runAnalyticsSavedReport,
  updateAnalyticsSavedReport,
} from '@/services/analytics.service';

export default function AnalyticsReportDetailPage() {
  const params = useParams();
  const reportId = String(params.reportId ?? '');

  const [report, setReport] = useState<AnalyticsSavedReport | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [running, setRunning] = useState(false);
  const [lastExport, setLastExport] = useState<AnalyticsExportJob | null>(null);
  const [downloadingId, setDownloadingId] = useState<string | null>(null);

  const load = useCallback(() => {
    if (!reportId) return;
    setLoading(true);
    setError(null);
    fetchAnalyticsSavedReport(reportId)
      .then(setReport)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load saved report'))
      .finally(() => setLoading(false));
  }, [reportId]);

  useEffect(() => {
    load();
  }, [load]);

  async function handleSave(payload: SavedReportPayload) {
    if (!report) return;
    setSaving(true);
    setError(null);
    try {
      const updated = await updateAnalyticsSavedReport(report.id, payload);
      setReport(updated);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to update report');
    } finally {
      setSaving(false);
    }
  }

  async function handleRun() {
    if (!report) return;
    setRunning(true);
    setError(null);
    setLastExport(null);
    try {
      const job = await runAnalyticsSavedReport(report.id);
      setLastExport(job);
      load();
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

  async function handleArchive() {
    if (!report) return;
    if (!window.confirm(`Archive "${report.name}"?`)) return;
    setError(null);
    try {
      await archiveAnalyticsSavedReport(report.id);
      window.location.href = '/admin/analytics/reports';
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to archive report');
    }
  }

  const schedule = report ? scheduleSummary(report) : null;

  return (
    <AdminAnalyticsShell title={report?.name ?? 'Saved report'}>
      <div className="mb-4">
        <Link href="/admin/analytics/reports" className="text-sm text-zinc-600 hover:underline">
          ← Back to saved reports
        </Link>
      </div>

      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}
      {loading && !report ? <LoadingState label="Loading report…" /> : null}

      {report ? (
        <>
          <div className="mb-6 flex flex-wrap items-start justify-between gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
            <div className="space-y-1 text-sm">
              <p>
                <span className="text-zinc-500">Type:</span> {reportTypeLabel(report.report_type)}
              </p>
              <p>
                <span className="text-zinc-500">Format:</span> {exportFormatLabel(report.export_format)}
              </p>
              <p>
                <span className="text-zinc-500">Last run:</span> {formatDateTime(report.last_run_at)}
              </p>
              {schedule ? (
                <p className="flex items-center gap-2">
                  <span className="text-zinc-500">Schedule:</span>
                  <AnalyticsStatusBadge label={schedule} tone="amber" />
                </p>
              ) : null}
            </div>
            <div className="flex flex-wrap gap-2">
              <button
                type="button"
                disabled={running}
                onClick={handleRun}
                className="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
              >
                {running ? 'Running…' : 'Run export now'}
              </button>
              <button
                type="button"
                onClick={handleArchive}
                className="rounded-md border border-red-200 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
              >
                Archive
              </button>
            </div>
          </div>

          {lastExport ? (
            <div
              className={`mb-4 rounded-md border px-3 py-2 text-sm ${
                lastExport.status === 'completed'
                  ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                  : lastExport.status === 'failed'
                    ? 'border-red-200 bg-red-50 text-red-700'
                    : 'border-amber-200 bg-amber-50 text-amber-800'
              }`}
            >
              Export {lastExport.status}
              {lastExport.status === 'failed' && lastExport.failure_reason ? (
                <span className="ml-1">— {lastExport.failure_reason}</span>
              ) : null}
              {lastExport.status === 'completed' ? (
                <>
                  {' '}
                  <button
                    type="button"
                    disabled={downloadingId === lastExport.id}
                    onClick={() => handleDownload(lastExport)}
                    className="font-medium underline disabled:opacity-50"
                  >
                    {downloadingId === lastExport.id ? 'Downloading…' : 'Download'}
                  </button>
                </>
              ) : null}
            </div>
          ) : null}

          <AnalyticsSavedReportForm
            initial={report}
            submitting={saving}
            submitLabel="Save changes"
            onSubmit={handleSave}
          />
        </>
      ) : null}
    </AdminAnalyticsShell>
  );
}
