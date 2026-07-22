'use client';

import Link from 'next/link';
import { AnalyticsEmptyState } from '@/components/admin/analytics/AnalyticsEmptyState';
import { AnalyticsStatusBadge } from '@/components/admin/analytics/AnalyticsStatusBadge';
import {
  exportFormatLabel,
  formatDateTime,
  reportTypeLabel,
  scheduleSummary,
  type AnalyticsSavedReport,
} from '@/lib/analytics-types';

interface AnalyticsSavedReportsTableProps {
  reports: AnalyticsSavedReport[];
  runningId?: string | null;
  onRun: (report: AnalyticsSavedReport) => void;
  onArchive: (report: AnalyticsSavedReport) => void;
}

export function AnalyticsSavedReportsTable({
  reports,
  runningId,
  onRun,
  onArchive,
}: AnalyticsSavedReportsTableProps) {
  if (reports.length === 0) {
    return <AnalyticsEmptyState message="No saved reports yet. Create one to reuse analytics filters and export settings." />;
  }

  return (
    <div className="overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-sm">
      <table className="min-w-full text-left text-sm">
        <thead className="border-b border-zinc-200 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
          <tr>
            <th className="px-4 py-3 font-medium">Name</th>
            <th className="px-4 py-3 font-medium">Type</th>
            <th className="px-4 py-3 font-medium">Format</th>
            <th className="px-4 py-3 font-medium">Schedule</th>
            <th className="px-4 py-3 font-medium">Last run</th>
            <th className="px-4 py-3 font-medium text-right">Actions</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-zinc-100">
          {reports.map((report) => {
            const schedule = scheduleSummary(report);
            const isRunning = runningId === report.id;

            return (
              <tr key={report.id} className="hover:bg-zinc-50">
                <td className="px-4 py-3">
                  <Link href={`/admin/analytics/reports/${report.id}`} className="font-medium hover:underline">
                    {report.name}
                  </Link>
                </td>
                <td className="px-4 py-3">{reportTypeLabel(report.report_type)}</td>
                <td className="px-4 py-3">{exportFormatLabel(report.export_format)}</td>
                <td className="px-4 py-3">
                  {schedule ? (
                    <AnalyticsStatusBadge label={schedule} tone="amber" />
                  ) : (
                    <span className="text-zinc-400">—</span>
                  )}
                </td>
                <td className="px-4 py-3 text-zinc-600">{formatDateTime(report.last_run_at)}</td>
                <td className="px-4 py-3">
                  <div className="flex justify-end gap-2">
                    <button
                      type="button"
                      disabled={isRunning}
                      onClick={() => onRun(report)}
                      className="rounded-md border border-zinc-300 px-2 py-1 text-xs font-medium hover:bg-zinc-50 disabled:opacity-50"
                    >
                      {isRunning ? 'Running…' : 'Run export'}
                    </button>
                    <Link
                      href={`/admin/analytics/reports/${report.id}`}
                      className="rounded-md border border-zinc-300 px-2 py-1 text-xs font-medium hover:bg-zinc-50"
                    >
                      Edit
                    </Link>
                    <button
                      type="button"
                      onClick={() => onArchive(report)}
                      className="rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50"
                    >
                      Archive
                    </button>
                  </div>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
