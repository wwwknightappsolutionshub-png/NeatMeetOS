'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import {
  PlatformButton,
  PlatformCard,
  PlatformErrorAlert,
  PlatformField,
  PlatformLoadingState,
  PlatformPage,
  PlatformPageIntro,
  PlatformTable,
  PlatformTableHead,
  platformInputClass,
  platformSelectClass,
} from '@/components/platform/ui';
import type { PlatformGrowthAssessmentRow } from '@/lib/growth-assessment-types';
import { fetchPlatformGrowthAssessments } from '@/services/growth-assessment.service';

const STATUS_LABEL: Record<string, string> = {
  new: 'New',
  contacted: 'Contacted',
  qualified: 'Qualified',
  demo_booked: 'Demo booked',
  trial_started: 'Trial started',
  converted: 'Converted',
  not_interested: 'Not interested',
  no_response: 'No response',
};

export default function PlatformGrowthAssessmentsPage() {
  const [items, setItems] = useState<PlatformGrowthAssessmentRow[]>([]);
  const [statuses, setStatuses] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [q, setQ] = useState('');
  const [leadStatus, setLeadStatus] = useState('');
  const [businessType, setBusinessType] = useState('');
  const [usesSoftware, setUsesSoftware] = useState('');
  const [scoreMin, setScoreMin] = useState('');
  const [scoreMax, setScoreMax] = useState('');
  const [opportunityMin, setOpportunityMin] = useState('');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [sort, setSort] = useState('created_at');
  const [total, setTotal] = useState(0);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await fetchPlatformGrowthAssessments({
        q: q.trim() || undefined,
        lead_status: leadStatus || undefined,
        business_type: businessType || undefined,
        uses_software: usesSoftware || undefined,
        score_min: scoreMin || undefined,
        score_max: scoreMax || undefined,
        opportunity_min_cents: opportunityMin
          ? String(Math.round(Number(opportunityMin) * 100))
          : undefined,
        from: from || undefined,
        to: to || undefined,
        sort,
        dir: 'desc',
        per_page: 50,
      });
      setItems(data.items);
      setStatuses(data.lead_statuses);
      setTotal(data.meta.total);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load assessments');
    } finally {
      setLoading(false);
    }
  }, [
    q,
    leadStatus,
    businessType,
    usesSoftware,
    scoreMin,
    scoreMax,
    opportunityMin,
    from,
    to,
    sort,
  ]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <PlatformPage>
      <PlatformPageIntro
        eyebrow="Outreach"
        title="Salon growth assessments"
        description="Pre-tenant diagnostic leads — scores, indicative opportunity, and follow-up intelligence."
      />

      {error ? <PlatformErrorAlert message={error} /> : null}

      <PlatformCard className="mb-4">
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <PlatformField label="Search">
            <input
              className={platformInputClass}
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder="Name, email, business…"
            />
          </PlatformField>
          <PlatformField label="Lead status">
            <select
              className={platformSelectClass}
              value={leadStatus}
              onChange={(e) => setLeadStatus(e.target.value)}
            >
              <option value="">All</option>
              {statuses.map((s) => (
                <option key={s} value={s}>
                  {STATUS_LABEL[s] ?? s}
                </option>
              ))}
            </select>
          </PlatformField>
          <PlatformField label="Business type">
            <select
              className={platformSelectClass}
              value={businessType}
              onChange={(e) => setBusinessType(e.target.value)}
            >
              <option value="">All</option>
              <option value="hair_salon">Hair salon</option>
              <option value="barber_shop">Barber shop</option>
              <option value="beauty_salon">Beauty salon</option>
              <option value="spa">Spa</option>
              <option value="other">Other</option>
            </select>
          </PlatformField>
          <PlatformField label="Uses software">
            <select
              className={platformSelectClass}
              value={usesSoftware}
              onChange={(e) => setUsesSoftware(e.target.value)}
            >
              <option value="">All</option>
              <option value="yes">Yes</option>
              <option value="no">No</option>
            </select>
          </PlatformField>
          <PlatformField label="Score min">
            <input
              className={platformInputClass}
              value={scoreMin}
              onChange={(e) => setScoreMin(e.target.value)}
              placeholder="0"
            />
          </PlatformField>
          <PlatformField label="Score max">
            <input
              className={platformInputClass}
              value={scoreMax}
              onChange={(e) => setScoreMax(e.target.value)}
              placeholder="100"
            />
          </PlatformField>
          <PlatformField label="Min opportunity £/mo">
            <input
              className={platformInputClass}
              value={opportunityMin}
              onChange={(e) => setOpportunityMin(e.target.value)}
              placeholder="e.g. 2000"
            />
          </PlatformField>
          <PlatformField label="Sort">
            <select
              className={platformSelectClass}
              value={sort}
              onChange={(e) => setSort(e.target.value)}
            >
              <option value="created_at">Newest</option>
              <option value="score_overall">Growth score</option>
              <option value="estimated_opportunity_cents">Opportunity</option>
              <option value="business_name">Business name</option>
            </select>
          </PlatformField>
          <PlatformField label="From">
            <input
              type="date"
              className={platformInputClass}
              value={from}
              onChange={(e) => setFrom(e.target.value)}
            />
          </PlatformField>
          <PlatformField label="To">
            <input
              type="date"
              className={platformInputClass}
              value={to}
              onChange={(e) => setTo(e.target.value)}
            />
          </PlatformField>
        </div>
        <div className="mt-3 flex items-center gap-3">
          <PlatformButton type="button" onClick={() => void load()}>
            Apply filters
          </PlatformButton>
          <span className="text-sm text-stone-500">{total} lead{total === 1 ? '' : 's'}</span>
        </div>
      </PlatformCard>

      {loading ? (
        <PlatformLoadingState label="Loading assessments…" />
      ) : (
        <PlatformCard padded={false}>
          <PlatformTable>
            <PlatformTableHead>
              <tr>
                <th>Business</th>
                <th>Contact</th>
                <th>Score</th>
                <th>Opportunity</th>
                <th>Software</th>
                <th>Status</th>
                <th>Date</th>
              </tr>
            </PlatformTableHead>
            <tbody>
              {items.map((row) => (
                <tr key={row.id} className="border-t border-stone-100">
                  <td className="px-3 py-2.5">
                    <Link
                      href={`/platform/growth-assessments/${row.id}`}
                      className="font-semibold text-[#2f5a45] hover:underline"
                    >
                      {row.business_name}
                    </Link>
                    <div className="text-xs text-stone-500">{row.business_type}</div>
                  </td>
                  <td className="px-3 py-2.5 text-sm">
                    <div>{row.contact_name}</div>
                    <div className="text-xs text-stone-500">{row.email}</div>
                  </td>
                  <td className="px-3 py-2.5 tabular-nums font-semibold">{row.score_overall}</td>
                  <td className="px-3 py-2.5 tabular-nums">
                    {row.estimated_opportunity_display}/mo
                  </td>
                  <td className="px-3 py-2.5 text-sm">{row.uses_software ?? '—'}</td>
                  <td className="px-3 py-2.5 text-sm">
                    {STATUS_LABEL[row.lead_status] ?? row.lead_status}
                  </td>
                  <td className="px-3 py-2.5 text-xs text-stone-500">
                    {row.created_at
                      ? new Date(row.created_at).toLocaleDateString()
                      : '—'}
                  </td>
                </tr>
              ))}
              {items.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-3 py-8 text-center text-sm text-stone-500">
                    No assessments yet.
                  </td>
                </tr>
              ) : null}
            </tbody>
          </PlatformTable>
        </PlatformCard>
      )}
    </PlatformPage>
  );
}
