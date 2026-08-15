'use client';

import Link from 'next/link';
import { useParams, useSearchParams } from 'next/navigation';
import { Suspense, useCallback, useEffect, useState } from 'react';
import type { BookingChangeRequest } from '@/lib/booking-types';
import {
  fetchBookingChangeRequest,
  resolveBookingChangeRequest,
} from '@/services/online-booking.service';

function ChangeRequestInner() {
  const params = useParams<{ tenantSlug: string }>();
  const search = useSearchParams();
  const tenantSlug = params.tenantSlug;
  const id = search.get('id') ?? '';
  const token = search.get('token') ?? '';
  const actionParam = search.get('action');

  const [request, setRequest] = useState<BookingChangeRequest | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!id || !token) {
      setError('This link is missing a request id or token.');
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const data = await fetchBookingChangeRequest(tenantSlug, id, token);
      setRequest(data);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not load this request.');
    } finally {
      setLoading(false);
    }
  }, [id, token, tenantSlug]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    if (!request || request.status !== 'pending') return;
    if (actionParam !== 'accept' && actionParam !== 'decline') return;
    if (actionParam === 'decline' && !request.decline_allowed) {
      setNotice('Decline is not allowed for this free-window request. Please confirm instead.');
      return;
    }

    let cancelled = false;
    void (async () => {
      setBusy(true);
      try {
        const updated = await resolveBookingChangeRequest(tenantSlug, id, token, actionParam);
        if (!cancelled) {
          setRequest(updated);
          setNotice(actionParam === 'accept' ? 'Request accepted.' : 'Request declined. Original time kept.');
        }
      } catch (e) {
        if (!cancelled) {
          setError(e instanceof Error ? e.message : 'Could not resolve this request.');
        }
      } finally {
        if (!cancelled) setBusy(false);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [actionParam, id, request, tenantSlug, token]);

  async function onAction(action: 'accept' | 'decline') {
    if (!request) return;
    setBusy(true);
    setError(null);
    try {
      const updated = await resolveBookingChangeRequest(tenantSlug, id, token, action);
      setRequest(updated);
      setNotice(action === 'accept' ? 'Request accepted.' : 'Request declined. Original time kept.');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not resolve this request.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="mx-auto min-h-screen max-w-lg px-4 py-10">
      <p className="text-xs font-semibold uppercase tracking-[0.2em] text-stone-500">
        Booking change request
      </p>
      <h1 className="mt-2 font-[family-name:var(--font-anek)] text-2xl font-semibold text-stone-900">
        Confirm or decline
      </h1>

      {loading ? <p className="mt-6 text-sm text-stone-600">Loading…</p> : null}
      {error ? (
        <p className="mt-6 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
          {error}
        </p>
      ) : null}
      {notice ? (
        <p className="mt-6 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
          {notice}
        </p>
      ) : null}

      {request ? (
        <div className="mt-6 space-y-3 rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
          <p className="text-sm text-stone-700">
            Type: <strong>{request.type}</strong> · Status: <strong>{request.status}</strong>
          </p>
          {request.appointment?.starts_at ? (
            <p className="text-sm text-stone-700">
              Current time: {new Date(request.appointment.starts_at).toLocaleString()}
            </p>
          ) : null}
          {request.proposed_starts_at ? (
            <p className="text-sm text-stone-700">
              Proposed time: {new Date(request.proposed_starts_at).toLocaleString()}
            </p>
          ) : null}
          {request.late_fee_applies ? (
            <p className="text-sm text-amber-800">
              Late cancel fee: {((request.late_fee_cents ?? 0) / 100).toFixed(2)} (50% of deposit)
            </p>
          ) : null}

          {request.status === 'pending' ? (
            <div className="flex flex-wrap gap-2 pt-2">
              <button
                type="button"
                disabled={busy}
                onClick={() => void onAction('accept')}
                className="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
              >
                Confirm
              </button>
              {request.decline_allowed ? (
                <button
                  type="button"
                  disabled={busy}
                  onClick={() => void onAction('decline')}
                  className="rounded-md border border-stone-300 bg-white px-4 py-2 text-sm font-semibold text-stone-800 disabled:opacity-50"
                >
                  Decline
                </button>
              ) : (
                <p className="text-xs text-stone-500">Decline is not allowed in the free window.</p>
              )}
            </div>
          ) : null}
        </div>
      ) : null}

      <p className="mt-8 text-sm">
        <Link href={`/book/${tenantSlug}`} className="text-emerald-800 underline">
          Back to booking
        </Link>
      </p>
    </div>
  );
}

export default function ChangeRequestPage() {
  return (
    <Suspense fallback={<div className="p-8 text-sm text-stone-600">Loading…</div>}>
      <ChangeRequestInner />
    </Suspense>
  );
}
