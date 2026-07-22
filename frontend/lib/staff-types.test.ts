import { describe, expect, it } from 'vitest';
import { ABSENCE_CATEGORIES, DAYS_OF_WEEK } from '@/lib/staff-types';

describe('staff types', () => {
  it('includes weekday labels', () => {
    expect(DAYS_OF_WEEK[1]).toBe('Monday');
    expect(DAYS_OF_WEEK[7]).toBe('Sunday');
  });

  it('includes absence categories', () => {
    expect(ABSENCE_CATEGORIES).toContain('holiday');
    expect(ABSENCE_CATEGORIES).toContain('training');
  });
});
