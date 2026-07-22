'use client';

import { AnalyticsEmptyState } from '@/components/admin/analytics/AnalyticsEmptyState';
import { AnalyticsStatusBadge } from '@/components/admin/analytics/AnalyticsStatusBadge';
import {
  exportFormatLabel,
  exportStatusLabel,
  exportStatusTone,
  formatDateTime,
  formatNumber,
  reportTypeLabel,
  type AnalyticsExportJob,
} from '@/lib/analytics-types';

interface AnalyticsExportJobsTableProps {
  jobs: AnalyticsExportJob[];
  downloadingId?: string | null;
  onDownload: (job: AnalyticsExportJob) => void;
}

export function AnalyticsExportJobsTable({
  jobs,
  downloadingId,
  onDownload,
}: AnalyticsExportJobsTableProps) {
  if (jobs.length === 0) {
    return <AnalyticsEmptyState message="No export jobs yet. Run an ad-hoc export or run a saved report." />;
  }

  return (
    <div className="overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-sm">
      <table className="min-w-full text-left text-sm">
        <thead className="border-b border-zinc-200 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
          <tr>
            <th className="px-4 py-3 font-medium">Report</th>
            <th className="px-4 py-3 font-medium">Source</th>
            <th className="px-4 py-3 font-medium">Format</th>
            <th className="px-4 py-3 font-medium">Status</th>
            <th className="px-4 py-3 font-medium">Rows</th>
            <th className="px-4 py-3 font-medium">Created</th>
            <th className="px-4 py-3 font-medium">Completed / failed</th>
            <th className="px-4 py-3 font-medium text-right">Actions</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-zinc-100">
          {jobs.map((job) => {
            const canDownload = job.status === 'completed' && Boolean(job.download_url);
            const isDownloading = downloadingId === job.id;

            return (
              <tr key={job.id} className="hover:bg-zinc-50">
                <td className="px-4 py-3 font-medium">{reportTypeLabel(job.report_type)}</td>
                <td className="px-4 py-3 text-zinc-600">
                  {job.saved_report?.name ?? <span className="text-zinc-400">Ad-hoc</span>}
                </td>
                <td className="px-4 py-3">{exportFormatLabel(job.export_format)}</td>
                <td className="px-4 py-3">
                  <AnalyticsStatusBadge label={exportStatusLabel(job.status)} tone={exportStatusTone(job.status)} />
                </td>
                <td className="px-4 py-3">{formatNumber(job.row_count)}</td>
                <td className="px-4 py-3 text-zinc-600">{formatDateTime(job.created_at)}</td>
                <td className="px-4 py-3 text-zinc-600">
                  {job.status === 'failed' ? (
                    <span className="text-red-700" title={job.failure_reason ?? undefined}>
                      {formatDateTime(job.failed_at)}
                    </span>
                  ) : (
                    formatDateTime(job.completed_at)
                  )}
                </td>
                <td className="px-4 py-3 text-right">
                  {canDownload ? (
                    <button
                      type="button"
                      disabled={isDownloading}
                      onClick={() => onDownload(job)}
                      className="rounded-md border border-zinc-300 px-2 py-1 text-xs font-medium hover:bg-zinc-50 disabled:opacity-50"
                    >
                      {isDownloading ? 'Downloading…' : 'Download'}
                    </button>
                  ) : (
                    <span className="text-xs text-zinc-400">—</span>
                  )}
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
