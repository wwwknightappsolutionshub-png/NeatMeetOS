import { describe, expect, it } from 'vitest';
import {
  CHECKOUT_LINE_RETURN_STATUSES,
  CHECKOUT_STATUSES,
  DISCOUNT_TYPES,
  GIFT_CARD_STATUSES,
  RECEIPT_DELIVERY_METHODS,
  checkoutStatusLabel,
  formatMoneyCents,
  isCheckoutEditable,
  isCheckoutTerminal,
  POS_LINE_TYPES,
  POS_TENDER_TYPES,
} from '@/lib/pos-types';

describe('pos types', () => {
  it('includes checkout lifecycle statuses', () => {
    expect(CHECKOUT_STATUSES).toContain('draft');
    expect(CHECKOUT_STATUSES).toContain('open');
    expect(CHECKOUT_STATUSES).toContain('completed');
    expect(CHECKOUT_STATUSES).toContain('voided');
  });

  it('includes POS line and tender types', () => {
    expect(POS_LINE_TYPES).toContain('appointment_service');
    expect(POS_LINE_TYPES).toContain('retail_product');
    expect(POS_LINE_TYPES).toContain('deposit_credit');
    expect(POS_TENDER_TYPES).toContain('cash');
    expect(POS_TENDER_TYPES).toContain('card_manual');
  });

  it('formats money from cents', () => {
    expect(formatMoneyCents(6500)).toBe('£65.00');
  });

  it('detects editable and terminal checkout states', () => {
    expect(isCheckoutEditable('draft')).toBe(true);
    expect(isCheckoutEditable('open')).toBe(true);
    expect(isCheckoutEditable('completed')).toBe(false);
    expect(isCheckoutTerminal('completed')).toBe(true);
    expect(isCheckoutTerminal('open')).toBe(false);
  });

  it('includes advanced 8B constants', () => {
    expect(DISCOUNT_TYPES).toContain('manager_override');
    expect(CHECKOUT_LINE_RETURN_STATUSES).toContain('returned');
    expect(RECEIPT_DELIVERY_METHODS).toContain('email');
    expect(GIFT_CARD_STATUSES).toContain('active');
  });

  it('labels checkout status for display', () => {
    expect(checkoutStatusLabel('partially_refunded')).toBe('partially refunded');
  });

  it('supports membership redemption checkout fields', () => {
    const checkout = {
      wallet_credit_cents: 500,
      loyalty_discount_cents: 1000,
      loyalty_points_redeemed: 100,
      package_covered_cents: 4000,
    };
    expect(checkout.package_covered_cents).toBe(4000);
  });
});
