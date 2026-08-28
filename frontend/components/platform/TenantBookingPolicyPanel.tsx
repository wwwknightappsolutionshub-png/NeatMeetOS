'use client';

import { FormEvent, useEffect, useState } from 'react';
import {
  PlatformButton,
  PlatformErrorAlert,
  PlatformField,
  PlatformLoadingState,
  PlatformModalBackdrop,
  PlatformModalPanel,
  PlatformSuccessAlert,
  platformInputClass,
} from '@/components/platform/ui';
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
    <PlatformModalBackdrop>
      <PlatformModalPanel title="Booking policy" subtitle={tenantName} onClose={onClose}>
        {loading ? <PlatformLoadingState label="Loading policy…" /> : null}
        {error ? <PlatformErrorAlert message={error} /> : null}
        {notice ? <PlatformSuccessAlert message={notice} /> : null}

        {form ? (
          <form className="space-y-3" onSubmit={(e) => void onSubmit(e)}>
            {fields.map((field) => (
              <PlatformField key={field.key} label={field.label}>
                <input
                  type="number"
                  min={field.min}
                  max={field.max}
                  className={platformInputClass}
                  value={form[field.key]}
                  onChange={(e) =>
                    setForm({ ...form, [field.key]: Number(e.target.value) })
                  }
                />
              </PlatformField>
            ))}
            <PlatformButton type="submit" disabled={saving}>
              {saving ? 'Saving…' : 'Save policy'}
            </PlatformButton>
          </form>
        ) : null}
      </PlatformModalPanel>
    </PlatformModalBackdrop>
  );
}
