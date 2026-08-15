'use client';

import Link from 'next/link';
import { Button } from '@/components/ui/Button';

interface AvailabilitySetupModalProps {
  open: boolean;
  staffPath: string;
  onDismiss: () => void;
}

export function AvailabilitySetupModal({
  open,
  staffPath,
  onDismiss,
}: AvailabilitySetupModalProps) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[80] flex items-end justify-center bg-black/45 p-4 sm:items-center">
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="availability-setup-title"
        className="w-full max-w-md rounded-2xl border border-[var(--admin-line)] bg-white p-6 shadow-xl"
      >
        <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-[var(--admin-accent)]">
          Getting started
        </p>
        <h2
          id="availability-setup-title"
          className="mt-2 text-xl font-semibold tracking-tight text-[var(--admin-ink)]"
        >
          Set Your Availability
        </h2>
        <p className="mt-2 text-sm leading-relaxed text-zinc-600">
          Your services are not yet bookable because your availability is not yet set.
        </p>
        <div className="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-end">
          <Button type="button" variant="secondary" onClick={onDismiss}>
            Later
          </Button>
          <Link
            href={staffPath}
            onClick={onDismiss}
            className="inline-flex items-center justify-center rounded-lg bg-[var(--admin-accent)] px-4 py-2 text-sm font-semibold text-white hover:brightness-110"
          >
            Set now
          </Link>
        </div>
      </div>
    </div>
  );
}
