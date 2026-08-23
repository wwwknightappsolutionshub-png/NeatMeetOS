import { describe, expect, it } from 'vitest';
import { monthBounds, shiftYearMonth } from '@/lib/money-types';

describe('My money helpers', () => {
  it('moves the month forward and back across years', () => {
    expect(shiftYearMonth('2026-08', -1)).toBe('2026-07');
    expect(shiftYearMonth('2026-12', 1)).toBe('2027-01');
  });

  it('returns first and last day for a month', () => {
    expect(monthBounds('2026-08')).toEqual({ from: '2026-08-01', to: '2026-08-31' });
    expect(monthBounds('2026-02')).toEqual({ from: '2026-02-01', to: '2026-02-28' });
  });
});
