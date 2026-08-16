'use client';

import { useEffect, useState } from 'react';
import type { BookableService, ReservationPaymentCatalog } from '@/lib/booking-types';
import { uploadReservationProof } from '@/services/online-booking.service';

type PaymentMethod = 'transfer' | 'stripe' | 'google_pay';

const COUNTDOWN_SECONDS = 60;

function formatMoney(cents: number): string {
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: 'GBP',
  }).format(cents / 100);
}

interface ReservationFeePanelProps {
  tenantSlug: string;
  service: BookableService;
  catalog: ReservationPaymentCatalog | null | undefined;
  fieldClass: () => string;
  documentId: string | null;
  onDocumentReady: (id: string | null) => void;
  onError: (message: string | null) => void;
}

export function ReservationFeePanel({
  tenantSlug,
  service,
  catalog,
  fieldClass,
  documentId,
  onDocumentReady,
  onError,
}: ReservationFeePanelProps) {
  const feeCents =
    service.deposit_required && service.deposit_amount_cents != null
      ? service.deposit_amount_cents
      : 0;
  const minFee = catalog?.min_fee_cents ?? 1000;
  const required = feeCents >= minFee;

  const [method, setMethod] = useState<PaymentMethod | null>(null);
  const [secondsLeft, setSecondsLeft] = useState(COUNTDOWN_SECONDS);
  const [timerActive, setTimerActive] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [proofName, setProofName] = useState<string | null>(null);

  useEffect(() => {
    onDocumentReady(null);
    setMethod(null);
    setSecondsLeft(COUNTDOWN_SECONDS);
    setTimerActive(false);
    setProofName(null);
    onError(null);
    // Reset when the selected service (fee) changes.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [service.id, feeCents]);

  useEffect(() => {
    if (!timerActive || secondsLeft <= 0) return;
    const id = window.setTimeout(() => setSecondsLeft((s) => s - 1), 1000);
    return () => window.clearTimeout(id);
  }, [timerActive, secondsLeft]);

  if (!required) {
    return null;
  }

  const bank = catalog?.bank_details;
  const notice =
    catalog?.commitment_notice ??
    'This is just a commitment charge and it counts towards your actual charge when you arrive at the shop.';

  async function handleFile(file: File | null) {
    if (!file || method !== 'transfer') return;
    onError(null);
    setUploading(true);
    try {
      const doc = await uploadReservationProof(tenantSlug, {
        booking_service_id: service.id,
        payment_method: 'transfer',
        proof: file,
      });
      setProofName(file.name);
      onDocumentReady(doc.id);
    } catch (e) {
      onDocumentReady(null);
      setProofName(null);
      onError(e instanceof Error ? e.message : 'Upload failed');
    } finally {
      setUploading(false);
    }
  }

  function selectMethod(next: PaymentMethod) {
    setMethod(next);
    onDocumentReady(null);
    setProofName(null);
    onError(null);
    if (next === 'transfer') {
      setSecondsLeft(COUNTDOWN_SECONDS);
      setTimerActive(true);
    } else {
      setTimerActive(false);
      setSecondsLeft(COUNTDOWN_SECONDS);
    }
  }

  return (
    <div className="sm:col-span-2 space-y-4 rounded-xl border border-[var(--book-line)] bg-[var(--book-wash)] p-4">
      <div>
        <p className="text-sm font-semibold text-[var(--book-ink)]">
          Reservation fee · {formatMoney(feeCents)}
        </p>
        <p className="mt-1 text-xs leading-relaxed text-[var(--book-muted)]">{notice}</p>
      </div>

      <fieldset className="space-y-2">
        <legend className="text-sm font-semibold text-[var(--book-muted)]">Pay by</legend>
        {(catalog?.methods ?? [
          { id: 'transfer' as const, label: 'Transfer', available: true, coming_soon: false },
          { id: 'stripe' as const, label: 'Stripe', available: false, coming_soon: true },
          { id: 'google_pay' as const, label: 'Google Pay', available: false, coming_soon: true },
        ]).map((opt) => (
          <label
            key={opt.id}
            className={[
              'flex cursor-pointer items-start gap-3 rounded-md border border-[var(--book-line)] bg-white px-3 py-2.5 text-sm',
              method === opt.id ? 'ring-1 ring-[var(--book-moss)]' : '',
            ].join(' ')}
          >
            <input
              type="radio"
              name="reservation-payment-method"
              className="mt-1"
              checked={method === opt.id}
              onChange={() => selectMethod(opt.id)}
            />
            <span>
              <span className="font-semibold text-[var(--book-ink)]">{opt.label}</span>
              {opt.coming_soon ? (
                <span className="mt-0.5 block text-xs text-[var(--book-muted)]">Coming soon</span>
              ) : null}
            </span>
          </label>
        ))}
      </fieldset>

      {method === 'stripe' || method === 'google_pay' ? (
        <p className="text-sm text-[var(--book-muted)]">
          {method === 'stripe' ? 'Stripe' : 'Google Pay'} is coming soon. Please pay by transfer.
        </p>
      ) : null}

      {method === 'transfer' ? (
        <div className="space-y-3 text-sm">
          {!catalog?.transfer_ready || !bank ? (
            <p className="text-red-700">
              This salon has not published bank details for transfers yet. Please contact them to
              complete your booking.
            </p>
          ) : (
            <>
              <div className="rounded-md border border-[var(--book-line)] bg-white p-3">
                <p className="font-semibold text-[var(--book-ink)]">Bank transfer details</p>
                <dl className="mt-2 space-y-1 text-xs text-[var(--book-muted)]">
                  {bank.account_name ? (
                    <div className="flex justify-between gap-3">
                      <dt>Account name</dt>
                      <dd className="font-medium text-[var(--book-ink)]">{bank.account_name}</dd>
                    </div>
                  ) : null}
                  {bank.bank_name ? (
                    <div className="flex justify-between gap-3">
                      <dt>Bank</dt>
                      <dd className="font-medium text-[var(--book-ink)]">{bank.bank_name}</dd>
                    </div>
                  ) : null}
                  {bank.sort_code ? (
                    <div className="flex justify-between gap-3">
                      <dt>Sort code</dt>
                      <dd className="font-medium text-[var(--book-ink)]">{bank.sort_code}</dd>
                    </div>
                  ) : null}
                  {bank.account_number ? (
                    <div className="flex justify-between gap-3">
                      <dt>Account number</dt>
                      <dd className="font-medium text-[var(--book-ink)]">{bank.account_number}</dd>
                    </div>
                  ) : null}
                  {bank.iban ? (
                    <div className="flex justify-between gap-3">
                      <dt>IBAN</dt>
                      <dd className="font-medium text-[var(--book-ink)]">{bank.iban}</dd>
                    </div>
                  ) : null}
                  {bank.reference_hint ? (
                    <div className="flex justify-between gap-3">
                      <dt>Reference</dt>
                      <dd className="font-medium text-[var(--book-ink)]">{bank.reference_hint}</dd>
                    </div>
                  ) : null}
                </dl>
              </div>

              {secondsLeft > 0 ? (
                <p className="text-xs text-[var(--book-muted)]">
                  After you transfer, upload your payment screenshot in{' '}
                  <span className="font-semibold tabular-nums text-[var(--book-ink)]">
                    {secondsLeft}s
                  </span>
                  .
                </p>
              ) : (
                <label className="block">
                  <span className="mb-1.5 block font-semibold text-[var(--book-muted)]">
                    Upload transfer screenshot <span className="text-red-600">*</span>
                  </span>
                  <input
                    type="file"
                    accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                    className={fieldClass()}
                    disabled={uploading}
                    onChange={(e) => void handleFile(e.target.files?.[0] ?? null)}
                  />
                  <span className="mt-1 block text-xs text-[var(--book-muted)]">
                    .jpg, .jpeg or .png only · max 2MB
                    {proofName ? ` · uploaded: ${proofName}` : ''}
                    {documentId && !uploading ? ' · ready' : ''}
                    {uploading ? ' · uploading…' : ''}
                  </span>
                </label>
              )}
            </>
          )}
        </div>
      ) : null}
    </div>
  );
}

export function reservationFeeRequired(
  service: BookableService | null | undefined,
  minFeeCents = 1000,
): boolean {
  if (!service?.deposit_required || service.deposit_amount_cents == null) return false;
  return service.deposit_amount_cents >= minFeeCents;
}
