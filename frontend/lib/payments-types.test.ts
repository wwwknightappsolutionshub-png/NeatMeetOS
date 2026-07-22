import { describe, expect, it } from 'vitest';
import {
  formatMoneyCents,
  PAYMENT_METHOD_TYPES,
  PAYMENT_TRANSACTION_STATUSES,
  PAYMENT_TRANSACTION_TYPES,
} from '@/lib/payments-types';

describe('payments types', () => {
  it('includes transaction lifecycle statuses', () => {
    expect(PAYMENT_TRANSACTION_STATUSES).toContain('pending');
    expect(PAYMENT_TRANSACTION_STATUSES).toContain('succeeded');
    expect(PAYMENT_TRANSACTION_STATUSES).toContain('partially_refunded');
  });

  it('includes transaction types for module 6A', () => {
    expect(PAYMENT_TRANSACTION_TYPES).toContain('deposit');
    expect(PAYMENT_TRANSACTION_TYPES).toContain('refund');
  });

  it('includes payment method types', () => {
    expect(PAYMENT_METHOD_TYPES).toContain('payment_link');
    expect(PAYMENT_METHOD_TYPES).toContain('cash');
  });

  it('formats money from cents', () => {
    expect(formatMoneyCents(2500)).toBe('£25.00');
  });
});
