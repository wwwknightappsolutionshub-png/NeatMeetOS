import { describe, expect, it } from 'vitest';
import { shiftYearMonth } from '@/lib/money-types';

describe('My money helpers', () => {
  it('moves the month forward and back across years', () => {
    expect(shiftYearMonth('2026-08', -1)).toBe('2026-07');
    expect(shiftYearMonth('2026-12', 1)).toBe('2027-01');
  });
});
