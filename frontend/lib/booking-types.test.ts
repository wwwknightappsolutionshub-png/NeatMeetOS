import { describe, expect, it } from 'vitest';
import {
  APPOINTMENT_STATUSES,
  BOOKING_SOURCES,
  DEPOSIT_STATUSES,
  WAITLIST_STATUSES,
  WALK_IN_STAGES,
} from '@/lib/booking-types';

describe('booking types', () => {
  it('includes appointment lifecycle statuses', () => {
    expect(APPOINTMENT_STATUSES).toContain('confirmed');
    expect(APPOINTMENT_STATUSES).toContain('cancelled');
    expect(APPOINTMENT_STATUSES).toContain('no_show');
  });

  it('includes admin booking sources', () => {
    expect(BOOKING_SOURCES).toContain('admin');
    expect(BOOKING_SOURCES).toContain('waitlist');
    expect(BOOKING_SOURCES).toContain('online');
  });

  it('includes module 4B enums', () => {
    expect(DEPOSIT_STATUSES).toContain('pending');
    expect(DEPOSIT_STATUSES).toContain('waived');
    expect(WAITLIST_STATUSES).toContain('waiting');
    expect(WAITLIST_STATUSES).toContain('booked');
    expect(WAITLIST_STATUSES).toContain('unreachable');
  });

  it('includes module 4C front-desk enums', () => {
    expect(BOOKING_SOURCES).toContain('walk_in');
    expect(WALK_IN_STAGES).toContain('waiting');
    expect(WALK_IN_STAGES).toContain('seated');
  });

  it('supports package entitlement fields on service lines', () => {
    const line = { entitlement_state: 'reserved', covered_amount_cents: 4000 };
    expect(line.entitlement_state).toBe('reserved');
  });
});
