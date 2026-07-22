'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminAnalyticsShell } from '@/components/admin/analytics/AdminAnalyticsShell';
import { AnalyticsSavedReportForm } from '@/components/admin/analytics/AnalyticsSavedReportForm';
import { AnalyticsSavedReportsTable } from '@/components/admin/analytics/AnalyticsSavedReportsTable';
import { ErrorAlert } from '@/components/admin/ui';
import type { AnalyticsExportJob, AnalyticsSavedReport, SavedReportPayload } from '@/lib/analytics-types';
import {
  archiveAnalyticsSavedReport,
  createAnalyticsSavedReport,
  downloadAnalyticsExport,
  fetchAnalyticsSavedReports,
  runAnalyticsSavedReport,
} from '@/services/analytics.service';

export default function AnalyticsReportsPage() {
  const [reports, setReports] = useState<AnalyticsSavedReport[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showCreate, setShowCreate] = useState(false);
  const [creating, setCreating] = useState(false);
  const [runningId, setRunningId] = useState<string | null>(null);
  const [lastExport, setLastExport] = useState<AnalyticsExportJob | null>(null);
  const [downloadingId, setDownloadingId] = useState<string | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    setError(null);
    fetchAnalyticsSavedReports()
      .then(setReports)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load saved reports'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function handleCreate(payload: SavedReportPayload) {
    setCreating(true);
    setError(null);
    try {
      await createAnalyticsSavedReport(payload);
      setShowCreate(false);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to create saved report');
    } finally {
      setCreating(false);
    }
  }

  async function handleRun(report: AnalyticsSavedReport) {
    setRunningId(report.id);
    setError(null);
    setLastExport(null);
    try {
      const job = await runAnalyticsSavedReport(report.id);
      setLastExport(job);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to run export');
    } finally {
      setRunningId(null);
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

  async function handleArchive(report: AnalyticsSavedReport) {
    if (!window.confirm(`Archive "${report.name}"? It will no longer appear in the list and cannot be run.`)) {
      return;
    }
    setError(null);
    try {
      await archiveAnalyticsSavedReport(report.id);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to archive report');
    }
  }

  return (
    <AdminAnalyticsShell title="Saved reports">
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-zinc-600">
          Reusable analytics presets with filters, export format, and optional schedule metadata.
        </p>
        <button
          type="button"
          onClick={() => setShowCreate((v) => !v)}
          className="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white"
        >
          {showCreate ? 'Close form' : 'Create report'}
        </button>
      </div>

      {error ? <div className="mb-4"><ErrorAlert message={error} /></div> : null}

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
          Export {lastExport.status} — {lastExport.report_type} ({lastExport.export_format.toUpperCase()})
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
          {' · '}
          <a href="/admin/analytics/exports" className="font-medium underline">
            View export history
          </a>
        </div>
      ) : null}

      {showCreate ? (
        <div className="mb-6">
          <AnalyticsSavedReportForm
            submitting={creating}
            submitLabel="Create report"
            onSubmit={handleCreate}
            onCancel={() => setShowCreate(false)}
          />
        </div>
      ) : null}

      {loading && reports.length === 0 ? (
        <p className="text-sm text-zinc-500">Loading saved reports…</p>
      ) : (
        <AnalyticsSavedReportsTable
          reports={reports}
          runningId={runningId}
          onRun={handleRun}
          onArchive={handleArchive}
        />
      )}
    </AdminAnalyticsShell>
  );
}
