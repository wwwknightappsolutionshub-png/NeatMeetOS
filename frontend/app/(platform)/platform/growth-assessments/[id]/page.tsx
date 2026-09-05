'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useCallback, useEffect, useState } from 'react';
import {
  PlatformButton,
  PlatformCard,
  PlatformErrorAlert,
  PlatformField,
  PlatformLoadingState,
  PlatformPage,
  PlatformPageIntro,
  PlatformSuccessAlert,
  platformInputClass,
  platformSelectClass,
} from '@/components/platform/ui';
import type { PlatformGrowthAssessmentDetail } from '@/lib/growth-assessment-types';
import {
  fetchPlatformGrowthAssessment,
  updatePlatformGrowthAssessment,
} from '@/services/growth-assessment.service';

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

export default function PlatformGrowthAssessmentDetailPage() {
  const params = useParams();
  const id = String(params.id ?? '');
  const [detail, setDetail] = useState<PlatformGrowthAssessmentDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [leadStatus, setLeadStatus] = useState('new');
  const [notes, setNotes] = useState('');
  const [nextFollowUp, setNextFollowUp] = useState('');
  const [lastContacted, setLastContacted] = useState('');

  const load = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const data = await fetchPlatformGrowthAssessment(id);
      setDetail(data);
      setLeadStatus(data.lead_status);
      setNotes(data.internal_notes ?? '');
      setNextFollowUp(data.next_follow_up_on ?? '');
      setLastContacted(data.last_contacted_at ? data.last_contacted_at.slice(0, 10) : '');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load assessment');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void load();
  }, [load]);

  async function save() {
    if (!id) return;
    setSaving(true);
    setError(null);
    setMessage(null);
    try {
      const updated = await updatePlatformGrowthAssessment(id, {
        lead_status: leadStatus,
        internal_notes: notes || null,
        next_follow_up_on: nextFollowUp || null,
        last_contacted_at: lastContacted || null,
      });
      setDetail(updated);
      setMessage('Lead updated.');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  if (loading) {
    return <PlatformLoadingState label="Loading assessment…" />;
  }

  if (!detail) {
    return (
      <PlatformPage>
        <PlatformErrorAlert message={error ?? 'Not found'} />
        <Link href="/platform/growth-assessments" className="text-sm text-[#2f5a45]">
          ← Back to list
        </Link>
      </PlatformPage>
    );
  }

  const opp = detail.prospect_opportunity;

  return (
    <PlatformPage>
      <PlatformPageIntro
        eyebrow="Outreach"
        title={detail.business_name}
        description={`${detail.contact_name ?? '—'} · ${detail.email ?? '—'} · ${detail.phone ?? '—'}`}
      />

      <div className="mb-4">
        <Link
          href="/platform/growth-assessments"
          className="text-sm font-semibold text-[#2f5a45] hover:underline"
        >
          ← All assessments
        </Link>
      </div>

      {error ? <PlatformErrorAlert message={error} /> : null}
      {message ? <PlatformSuccessAlert message={message} /> : null}

      <div className="grid gap-4 lg:grid-cols-2">
        <PlatformCard>
          <p className="text-xs font-semibold uppercase tracking-[0.16em] text-[#2f5a45]">
            Prospect opportunity
          </p>
          <h2 className="mt-2 text-xl font-semibold">{opp.label}</h2>
          <dl className="mt-4 space-y-2 text-sm">
            <div className="flex justify-between gap-4">
              <dt className="text-stone-500">Growth score</dt>
              <dd className="font-semibold tabular-nums">{opp.growth_score}/100</dd>
            </div>
            <div className="flex justify-between gap-4">
              <dt className="text-stone-500">Est. repeat-revenue opportunity</dt>
              <dd className="font-semibold">{opp.estimated_opportunity_display}</dd>
            </div>
            <div className="flex justify-between gap-4">
              <dt className="text-stone-500">Current system</dt>
              <dd className="text-right">{opp.current_system}</dd>
            </div>
            <div className="flex justify-between gap-4">
              <dt className="text-stone-500">Main weakness</dt>
              <dd className="text-right">{opp.main_weakness}</dd>
            </div>
          </dl>
          <p className="mt-4 rounded-lg bg-[#f3f1ec] p-3 text-sm leading-relaxed text-stone-700">
            {opp.suggested_sales_conversation}
          </p>
        </PlatformCard>

        <PlatformCard>
          <p className="text-xs font-semibold uppercase tracking-[0.16em] text-[#2f5a45]">
            Scores
          </p>
          <ul className="mt-3 space-y-2 text-sm">
            <li className="flex justify-between">
              <span>Overall</span>
              <span className="font-semibold tabular-nums">{detail.score_overall}</span>
            </li>
            <li className="flex justify-between">
              <span>Visibility</span>
              <span className="tabular-nums">{detail.score_visibility}</span>
            </li>
            <li className="flex justify-between">
              <span>Retention</span>
              <span className="tabular-nums">{detail.score_retention}</span>
            </li>
            <li className="flex justify-between">
              <span>Revenue visibility</span>
              <span className="tabular-nums">{detail.score_revenue_visibility}</span>
            </li>
            <li className="flex justify-between">
              <span>Re-engagement</span>
              <span className="tabular-nums">{detail.score_reengagement}</span>
            </li>
          </ul>
          <p className="mt-4 text-xs text-stone-500">
            Email: {detail.email_delivery_status}
            {detail.email_sent_at ? ` · ${new Date(detail.email_sent_at).toLocaleString()}` : ''}
          </p>
          <p className="text-xs text-stone-500">
            WhatsApp: {detail.whatsapp_delivery_status}
            {detail.whatsapp_delivery_error ? ` — ${detail.whatsapp_delivery_error}` : ''}
          </p>
        </PlatformCard>

        <PlatformCard>
          <p className="mb-3 text-xs font-semibold uppercase tracking-[0.16em] text-[#2f5a45]">
            Follow-up
          </p>
          <div className="space-y-3">
            <PlatformField label="Lead status">
              <select
                className={platformSelectClass}
                value={leadStatus}
                onChange={(e) => setLeadStatus(e.target.value)}
              >
                {Object.entries(STATUS_LABEL).map(([v, label]) => (
                  <option key={v} value={v}>
                    {label}
                  </option>
                ))}
              </select>
            </PlatformField>
            <PlatformField label="Last contacted">
              <input
                type="date"
                className={platformInputClass}
                value={lastContacted}
                onChange={(e) => setLastContacted(e.target.value)}
              />
            </PlatformField>
            <PlatformField label="Next follow-up">
              <input
                type="date"
                className={platformInputClass}
                value={nextFollowUp}
                onChange={(e) => setNextFollowUp(e.target.value)}
              />
            </PlatformField>
            <PlatformField label="Internal notes">
              <textarea
                className={platformInputClass}
                rows={5}
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
              />
            </PlatformField>
            <PlatformButton type="button" disabled={saving} onClick={() => void save()}>
              {saving ? 'Saving…' : 'Save lead'}
            </PlatformButton>
          </div>
        </PlatformCard>

        <PlatformCard>
          <p className="mb-3 text-xs font-semibold uppercase tracking-[0.16em] text-[#2f5a45]">
            Assessment answers
          </p>
          <pre className="max-h-80 overflow-auto rounded-lg bg-stone-50 p-3 text-xs text-stone-700">
            {JSON.stringify(detail.answers, null, 2)}
          </pre>
          <p className="mt-3 text-xs text-stone-500">
            Postcode: {detail.postcode ?? '—'} · Source: {detail.source}
            {detail.referral_code ? ` · Ref: ${detail.referral_code}` : ''}
          </p>
          <p className="mt-1 text-xs text-stone-500">
            Satisfaction: {detail.software_satisfaction ?? '—'} · Tracking:{' '}
            {detail.tracking_methods ?? '—'}
          </p>
        </PlatformCard>
      </div>
    </PlatformPage>
  );
}
