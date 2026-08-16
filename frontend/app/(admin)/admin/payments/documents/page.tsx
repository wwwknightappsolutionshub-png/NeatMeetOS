'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { AdminPaymentsShell } from '@/components/admin/payments/AdminPaymentsShell';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { formatMoneyCents } from '@/lib/payments-types';
import type { ReservationPaymentDocument } from '@/lib/payments-types';
import {
  confirmReservationPaymentDocument,
  fetchReservationPaymentDocuments,
  rejectReservationPaymentDocument,
} from '@/services/payments.service';

export default function PaymentDocumentsPage() {
  const [items, setItems] = useState<ReservationPaymentDocument[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [status, setStatus] = useState('pending_review');
  const [busyId, setBusyId] = useState<string | null>(null);
  const [note, setNote] = useState('');

  const load = useCallback(() => {
    setLoading(true);
    setError(null);
    fetchReservationPaymentDocuments({ status: status || undefined })
      .then(setItems)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, [status]);

  useEffect(() => {
    load();
  }, [load]);

  async function confirm(id: string) {
    setBusyId(id);
    setError(null);
    try {
      await confirmReservationPaymentDocument(id, note.trim() || undefined);
      setNote('');
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Confirm failed');
    } finally {
      setBusyId(null);
    }
  }

  async function reject(id: string) {
    setBusyId(id);
    setError(null);
    try {
      await rejectReservationPaymentDocument(id, note.trim() || undefined);
      setNote('');
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Reject failed');
    } finally {
      setBusyId(null);
    }
  }

  return (
    <AdminPaymentsShell title="Payment documents">
      {error ? <ErrorAlert message={error} /> : null}
      <Card title="Reservation transfer proofs">
        <div className="mb-4 flex flex-wrap items-end gap-3">
          <Field label="Status">
            <select
              className={inputClass}
              value={status}
              onChange={(e) => setStatus(e.target.value)}
            >
              <option value="">All</option>
              <option value="pending_review">Pending review</option>
              <option value="confirmed">Confirmed</option>
              <option value="rejected">Rejected</option>
            </select>
          </Field>
          <Field label="Review note (optional)">
            <input
              className={inputClass}
              value={note}
              onChange={(e) => setNote(e.target.value)}
              placeholder="Shown on confirm / reject"
            />
          </Field>
        </div>

        {loading ? (
          <LoadingState />
        ) : items.length === 0 ? (
          <p className="text-sm text-zinc-500">No payment documents yet.</p>
        ) : (
          <ul className="space-y-6">
            {items.map((doc) => (
              <li key={doc.id} className="border-b border-zinc-100 pb-5 last:border-0">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p className="font-medium">
                      {formatMoneyCents(doc.amount_cents)} · {doc.payment_method} · {doc.status}
                    </p>
                    <p className="mt-1 text-sm text-zinc-500">
                      {doc.appointment?.client_name ?? doc.client_name ?? 'Guest'}
                      {doc.service_name ? ` · ${doc.service_name}` : ''}
                      {doc.appointment?.booking_reference
                        ? ` · ref ${doc.appointment.booking_reference}`
                        : ''}
                    </p>
                    {doc.appointment?.starts_at ? (
                      <p className="text-sm text-zinc-500">
                        Booking {new Date(doc.appointment.starts_at).toLocaleString()}
                        {doc.appointment.provider_name
                          ? ` · ${doc.appointment.provider_name}`
                          : ''}
                      </p>
                    ) : (
                      <p className="text-sm text-amber-700">Not linked to a booking yet</p>
                    )}
                    {doc.appointment?.id ? (
                      <Link
                        href={`/admin/bookings/${doc.appointment.id}`}
                        className="mt-1 inline-block text-sm underline"
                      >
                        Open booking
                      </Link>
                    ) : null}
                  </div>
                  {doc.status === 'pending_review' ? (
                    <div className="flex gap-2">
                      <Button
                        type="button"
                        disabled={busyId === doc.id || !doc.appointment_id}
                        onClick={() => void confirm(doc.id)}
                      >
                        Confirm
                      </Button>
                      <Button
                        type="button"
                        variant="secondary"
                        disabled={busyId === doc.id}
                        onClick={() => void reject(doc.id)}
                      >
                        Reject
                      </Button>
                    </div>
                  ) : null}
                </div>
                {doc.proof_url ? (
                  <div className="mt-3 max-w-md overflow-hidden rounded-md border border-zinc-200 bg-zinc-50 p-2">
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img
                      src={doc.proof_url}
                      alt={doc.proof_original_name ?? 'Transfer proof'}
                      className="max-h-64 w-full object-contain"
                    />
                  </div>
                ) : (
                  <p className="mt-2 text-sm text-zinc-500">No attachment</p>
                )}
                {doc.review_note ? (
                  <p className="mt-2 text-xs text-zinc-500">Note: {doc.review_note}</p>
                ) : null}
              </li>
            ))}
          </ul>
        )}
      </Card>
    </AdminPaymentsShell>
  );
}
