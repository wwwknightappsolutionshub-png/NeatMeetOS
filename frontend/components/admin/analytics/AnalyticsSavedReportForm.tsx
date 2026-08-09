'use client';

import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { Field, inputClass } from '@/components/admin/ui';
import {
  ANALYTICS_EXPORT_FORMATS,
  ANALYTICS_REPORT_TYPES,
  ANALYTICS_SCHEDULE_FREQUENCIES,
  exportFormatLabel,
  reportSupportsLocation,
  reportSupportsProvider,
  reportTypeLabel,
  type AnalyticsExportFormat,
  type AnalyticsReportType,
  type AnalyticsSavedReport,
  type AnalyticsScheduleFrequency,
  type SavedReportPayload,
} from '@/lib/analytics-types';
import type { Location, TeamMember } from '@/lib/identity-types';
import { fetchLocations, fetchTeamMembers } from '@/services/identity.service';

interface AnalyticsSavedReportFormProps {
  initial?: AnalyticsSavedReport | null;
  submitting?: boolean;
  submitLabel?: string;
  onSubmit: (payload: SavedReportPayload) => void;
  onCancel?: () => void;
}

function teamMemberLabel(member: TeamMember): string {
  const name = [member.first_name, member.last_name].filter(Boolean).join(' ').trim();
  return name || member.display_name || member.email || member.id;
}

export function AnalyticsSavedReportForm({
  initial,
  submitting = false,
  submitLabel = 'Save report',
  onSubmit,
  onCancel,
}: AnalyticsSavedReportFormProps) {
  const [name, setName] = useState(initial?.name ?? '');
  const [reportType, setReportType] = useState<AnalyticsReportType>(initial?.report_type ?? 'overview');
  const [exportFormat, setExportFormat] = useState<AnalyticsExportFormat>(initial?.export_format ?? 'csv');
  const [from, setFrom] = useState(initial?.filters?.from ?? '');
  const [to, setTo] = useState(initial?.filters?.to ?? '');
  const [locationId, setLocationId] = useState(initial?.filters?.location_id ?? '');
  const [providerId, setProviderId] = useState(initial?.filters?.provider_id ?? '');
  const [isScheduled, setIsScheduled] = useState(initial?.is_scheduled ?? false);
  const [frequency, setFrequency] = useState<AnalyticsScheduleFrequency | ''>(initial?.schedule_frequency ?? '');
  const [dayOfWeek, setDayOfWeek] = useState<string>(
    initial?.schedule_day_of_week !== null && initial?.schedule_day_of_week !== undefined
      ? String(initial.schedule_day_of_week)
      : '',
  );
  const [dayOfMonth, setDayOfMonth] = useState<string>(
    initial?.schedule_day_of_month !== null && initial?.schedule_day_of_month !== undefined
      ? String(initial.schedule_day_of_month)
      : '',
  );
  const [time, setTime] = useState(initial?.schedule_time ?? '');
  const [deliveryEmails, setDeliveryEmails] = useState(
    (initial?.delivery_emails ?? []).join(', '),
  );

  const [locations, setLocations] = useState<Location[]>([]);
  const [providers, setProviders] = useState<TeamMember[]>([]);

  const showLocation = reportSupportsLocation(reportType);
  const showProvider = reportSupportsProvider(reportType);

  useEffect(() => {
    fetchLocations().then(setLocations).catch(() => setLocations([]));
    fetchTeamMembers().then(setProviders).catch(() => setProviders([]));
  }, []);

  const canSubmit = useMemo(() => name.trim().length > 0 && !submitting, [name, submitting]);

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    if (!canSubmit) return;

    const filters: SavedReportPayload['filters'] = {};
    if (from) filters.from = from;
    if (to) filters.to = to;
    if (showLocation && locationId) filters.location_id = locationId;
    if (showProvider && providerId) filters.provider_id = providerId;

    onSubmit({
      name: name.trim(),
      report_type: reportType,
      export_format: exportFormat,
      filters,
      is_scheduled: isScheduled,
      schedule_frequency: isScheduled && frequency ? frequency : null,
      schedule_day_of_week: isScheduled && dayOfWeek !== '' ? Number(dayOfWeek) : null,
      schedule_day_of_month: isScheduled && dayOfMonth !== '' ? Number(dayOfMonth) : null,
      schedule_time: isScheduled && time ? time : null,
      delivery_emails: isScheduled
        ? deliveryEmails
            .split(/[\s,;]+/)
            .map((e) => e.trim())
            .filter(Boolean)
        : null,
    });
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-5 rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
      <Field label="Report name">
        <input
          className={inputClass}
          value={name}
          onChange={(e) => setName(e.target.value)}
          placeholder="e.g. Weekly bookings"
          required
        />
      </Field>

      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Report type">
          <select className={inputClass} value={reportType} onChange={(e) => setReportType(e.target.value as AnalyticsReportType)}>
            {ANALYTICS_REPORT_TYPES.map((type) => (
              <option key={type} value={type}>
                {reportTypeLabel(type)}
              </option>
            ))}
          </select>
        </Field>
        <Field label="Export format">
          <select className={inputClass} value={exportFormat} onChange={(e) => setExportFormat(e.target.value as AnalyticsExportFormat)}>
            {ANALYTICS_EXPORT_FORMATS.map((format) => (
              <option key={format} value={format}>
                {exportFormatLabel(format)}
              </option>
            ))}
          </select>
        </Field>
      </div>

      <fieldset className="space-y-4 rounded-lg border border-zinc-200 p-4">
        <legend className="px-1 text-xs font-medium uppercase tracking-wide text-zinc-500">Filters</legend>
        <div className="grid gap-4 sm:grid-cols-2">
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
        <p className="text-xs text-zinc-500">Leave dates blank to use the default last-30-days window at run time.</p>
      </fieldset>

      <fieldset className="space-y-4 rounded-lg border border-zinc-200 p-4">
        <legend className="px-1 text-xs font-medium uppercase tracking-wide text-zinc-500">Schedule & delivery</legend>
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" checked={isScheduled} onChange={(e) => setIsScheduled(e.target.checked)} />
          <span>Run on a schedule (queue + email delivery)</span>
        </label>
        {isScheduled ? (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Field label="Frequency">
              <select className={inputClass} value={frequency} onChange={(e) => setFrequency(e.target.value as AnalyticsScheduleFrequency | '')}>
                <option value="">—</option>
                {ANALYTICS_SCHEDULE_FREQUENCIES.map((freq) => (
                  <option key={freq} value={freq}>
                    {freq.charAt(0).toUpperCase() + freq.slice(1)}
                  </option>
                ))}
              </select>
            </Field>
            {frequency === 'weekly' ? (
              <Field label="Day of week (0=Sun)">
                <input type="number" min={0} max={6} className={inputClass} value={dayOfWeek} onChange={(e) => setDayOfWeek(e.target.value)} />
              </Field>
            ) : null}
            {frequency === 'monthly' ? (
              <Field label="Day of month">
                <input type="number" min={1} max={31} className={inputClass} value={dayOfMonth} onChange={(e) => setDayOfMonth(e.target.value)} />
              </Field>
            ) : null}
            <Field label="Time (HH:MM)">
              <input type="time" className={inputClass} value={time} onChange={(e) => setTime(e.target.value)} />
            </Field>
            <Field label="Delivery emails">
              <input
                className={inputClass}
                value={deliveryEmails}
                onChange={(e) => setDeliveryEmails(e.target.value)}
                placeholder="owner@salon.com, manager@salon.com"
              />
            </Field>
          </div>
        ) : null}
      </fieldset>

      <div className="flex items-center gap-3">
        <button
          type="submit"
          disabled={!canSubmit}
          className="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
        >
          {submitting ? 'Saving…' : submitLabel}
        </button>
        {onCancel ? (
          <button type="button" onClick={onCancel} className="text-sm text-zinc-600 hover:underline">
            Cancel
          </button>
        ) : null}
      </div>
    </form>
  );
}
