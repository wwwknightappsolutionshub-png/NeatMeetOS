'use client';

import { FormEvent, useEffect, useState } from 'react';
import { Button } from '@/components/ui/Button';
import type { BookingPolicySummary } from '@/lib/booking-types';
import {
  fetchTenantBookingPolicy,
  updateTenantBookingPolicy,
} from '@/services/platform.service';

const fields: Array<{ key: keyof BookingPolicySummary; label: string; min?: number; max?: number }> =
  [
    { key: 'min_advance_notice_minutes', label: 'Minimum advance notice (minutes)', min: 0, max: 10080 },
    { key: 'free_change_window_minutes', label: 'Free cancel/postpone window (minutes)', min: 0, max: 10080 },
    { key: 'late_cancel_fee_percent', label: 'Late cancel fee (% of deposit)', min: 0, max: 100 },
    {
      key: 'free_window_reminder_lead_minutes',
      label: 'WhatsApp reminder before free window closes (minutes)',
      min: 0,
      max: 1440,
    },
    { key: 'approval_reminder_interval_minutes', label: 'Confirm/decline reminder every (minutes)', min: 1, max: 60 },
    { key: 'approval_reminder_max_count', label: 'Reminders before auto-accept', min: 1, max: 20 },
  ];

export function TenantBookingPolicyPanel({
  tenantId,
  tenantName,
  onClose,
}: {
  tenantId: string;
  tenantName: string;
  onClose: () => void;
}) {
  const [form, setForm] = useState<BookingPolicySummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    void (async () => {
      setLoading(true);
      setError(null);
      try {
        const data = await fetchTenantBookingPolicy(tenantId);
        if (!cancelled) setForm(data);
      } catch (e) {
        if (!cancelled) setError(e instanceof Error ? e.message : 'Could not load policy.');
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [tenantId]);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (!form) return;
    setSaving(true);
    setError(null);
    setNotice(null);
    try {
      const updated = await updateTenantBookingPolicy(tenantId, form);
      setForm(updated);
      setNotice('Booking policy saved.');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not save policy.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
      <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-white/10 bg-[#121417] p-5 shadow-xl">
        <div className="mb-4 flex items-start justify-between gap-3">
          <div>
            <p className="text-xs uppercase tracking-[0.18em] text-stone-400">Booking policy</p>
            <h2 className="text-lg font-semibold text-white">{tenantName}</h2>
          </div>
          <button type="button" className="text-sm text-stone-400 underline" onClick={onClose}>
            Close
          </button>
        </div>

        {loading ? <p className="text-sm text-stone-400">Loading…</p> : null}
        {error ? <p className="mb-3 text-sm text-red-300">{error}</p> : null}
        {notice ? <p className="mb-3 text-sm text-emerald-300">{notice}</p> : null}

        {form ? (
          <form className="space-y-3" onSubmit={(e) => void onSubmit(e)}>
            {fields.map((field) => (
              <label key={field.key} className="block text-sm text-stone-300">
                <span className="mb-1 block">{field.label}</span>
                <input
                  type="number"
                  min={field.min}
                  max={field.max}
                  className="w-full rounded-md border border-white/10 bg-black/30 px-3 py-2 text-white"
                  value={form[field.key]}
                  onChange={(e) =>
                    setForm({ ...form, [field.key]: Number(e.target.value) })
                  }
                />
              </label>
            ))}
            <Button type="submit" disabled={saving}>
              {saving ? 'Saving…' : 'Save policy'}
            </Button>
          </form>
        ) : null}
      </div>
    </div>
  );
}
