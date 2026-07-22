import { describe, expect, it } from 'vitest';
import { MEMBERSHIP_BILLING_FREQUENCIES, MEMBERSHIP_PLAN_STATUSES, CLIENT_MEMBERSHIP_STATUSES, WALLET_ENTRY_TYPES, LOYALTY_ENTRY_TYPES, CLIENT_PACKAGE_STATUSES, PACKAGE_REDEMPTION_TYPES } from '@/lib/memberships-types';

describe('memberships types', () => {
  it('exports plan status constants', () => {
    expect(MEMBERSHIP_PLAN_STATUSES).toContain('active');
    expect(MEMBERSHIP_PLAN_STATUSES).toContain('archived');
  });

  it('exports billing frequencies', () => {
    expect(MEMBERSHIP_BILLING_FREQUENCIES).toContain('monthly');
    expect(MEMBERSHIP_BILLING_FREQUENCIES).toHaveLength(4);
  });

  it('exports client membership statuses', () => {
    expect(CLIENT_MEMBERSHIP_STATUSES).toContain('active');
    expect(CLIENT_MEMBERSHIP_STATUSES).toContain('cancelled');
  });

  it('exports wallet and loyalty entry enums', () => {
    expect(WALLET_ENTRY_TYPES).toContain('membership_credit');
    expect(WALLET_ENTRY_TYPES).toContain('pos_redemption');
    expect(LOYALTY_ENTRY_TYPES).toContain('membership_bonus');
    expect(LOYALTY_ENTRY_TYPES).toContain('pos_redeem');
  });

  it('exports package status and redemption enums', () => {
    expect(CLIENT_PACKAGE_STATUSES).toContain('depleted');
    expect(PACKAGE_REDEMPTION_TYPES).toContain('manual_redeem');
    expect(PACKAGE_REDEMPTION_TYPES).toContain('pos_redeem');
    expect(PACKAGE_REDEMPTION_TYPES).toContain('booking_redeem');
  });
});
