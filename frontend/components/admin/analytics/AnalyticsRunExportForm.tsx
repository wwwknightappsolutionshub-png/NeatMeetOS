'use client';

import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { Field, inputClass } from '@/components/admin/ui';
import {
  ANALYTICS_EXPORT_FORMATS,
  ANALYTICS_REPORT_TYPES,
  exportFormatLabel,
  reportSupportsLocation,
  reportSupportsProvider,
  reportTypeLabel,
  type AnalyticsExportFormat,
  type AnalyticsReportType,
  type ExportCreatePayload,
} from '@/lib/analytics-types';
import type { Location, TeamMember } from '@/lib/identity-types';
import { fetchLocations, fetchTeamMembers } from '@/services/identity.service';

interface AnalyticsRunExportFormProps {
  submitting?: boolean;
  onSubmit: (payload: ExportCreatePayload) => void;
}

function teamMemberLabel(member: TeamMember): string {
  const name = [member.first_name, member.last_name].filter(Boolean).join(' ').trim();
  return name || member.display_name || member.email || member.id;
}

export function AnalyticsRunExportForm({ submitting = false, onSubmit }: AnalyticsRunExportFormProps) {
  const [reportType, setReportType] = useState<AnalyticsReportType>('overview');
  const [exportFormat, setExportFormat] = useState<AnalyticsExportFormat>('csv');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [locationId, setLocationId] = useState('');
  const [providerId, setProviderId] = useState('');

  const [locations, setLocations] = useState<Location[]>([]);
  const [providers, setProviders] = useState<TeamMember[]>([]);

  const showLocation = reportSupportsLocation(reportType);
  const showProvider = reportSupportsProvider(reportType);

  useEffect(() => {
    fetchLocations().then(setLocations).catch(() => setLocations([]));
    fetchTeamMembers().then(setProviders).catch(() => setProviders([]));
  }, []);

  const canSubmit = useMemo(() => !submitting, [submitting]);

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    if (!canSubmit) return;

    const filters: ExportCreatePayload['filters'] = {};
    if (from) filters.from = from;
    if (to) filters.to = to;
    if (showLocation && locationId) filters.location_id = locationId;
    if (showProvider && providerId) filters.provider_id = providerId;

    onSubmit({
      report_type: reportType,
      export_format: exportFormat,
      filters,
    });
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4 rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div>
        <h2 className="text-sm font-semibold">Run ad-hoc export</h2>
        <p className="mt-1 text-xs text-zinc-500">Exports run immediately and appear in the history below.</p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Field label="Report type">
          <select className={inputClass} value={reportType} onChange={(e) => setReportType(e.target.value as AnalyticsReportType)}>
            {ANALYTICS_REPORT_TYPES.map((type) => (
              <option key={type} value={type}>
                {reportTypeLabel(type)}
              </option>
            ))}
          </select>
        </Field>
        <Field label="Format">
          <select className={inputClass} value={exportFormat} onChange={(e) => setExportFormat(e.target.value as AnalyticsExportFormat)}>
            {ANALYTICS_EXPORT_FORMATS.map((format) => (
              <option key={format} value={format}>
                {exportFormatLabel(format)}
              </option>
            ))}
          </select>
        </Field>
        <Field label="From">
          <input type="date" className={inputClass} value={from} max={to || undefined} onChange={(e) => setFrom(e.target.value)} />
        </Field>
        <Field label="To">
          <input type="date" className={inputClass} value={to} min={from || undefined} onChange={(e) => setTo(e.target.value)} />
        </Field>
        {showLocation ? (
          <Field label="Location">
            <select className={inputClass} value={locationId} onChange={(e) => setLocationId(e.target.value)}>
              <option value="">All locations</option>
              {locations.map((loc) => (
                <option key={loc.id} value={loc.id}>
                  {loc.name}
                </option>
              ))}
            </select>
          </Field>
        ) : null}
        {showProvider ? (
          <Field label="Provider">
            <select className={inputClass} value={providerId} onChange={(e) => setProviderId(e.target.value)}>
              <option value="">All providers</option>
              {providers.map((member) => (
                <option key={member.id} value={member.id}>
                  {teamMemberLabel(member)}
                </option>
              ))}
            </select>
          </Field>
        ) : null}
      </div>

      <button
        type="submit"
        disabled={!canSubmit}
        className="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
      >
        {submitting ? 'Running export…' : 'Run export'}
      </button>
    </form>
  );
}
