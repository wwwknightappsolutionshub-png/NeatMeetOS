import { describe, expect, it } from 'vitest';
import {
  CONSENT_TYPES,
  DOCUMENT_TYPES,
  FORMULA_CATEGORIES,
  LOYALTY_DISPLAY_STATUSES,
  NOTE_TYPES,
  PHOTO_CATEGORIES,
} from '@/lib/crm-types';

describe('crm types', () => {
  it('includes marketing consent types', () => {
    expect(CONSENT_TYPES).toContain('marketing_email');
    expect(CONSENT_TYPES).toContain('marketing_sms');
  });

  it('includes internal note types', () => {
    expect(NOTE_TYPES).toContain('follow_up');
    expect(NOTE_TYPES).toContain('internal');
  });

  it('includes module 2B asset categories', () => {
    expect(FORMULA_CATEGORIES).toContain('colour');
    expect(PHOTO_CATEGORIES).toContain('formula_reference');
    expect(DOCUMENT_TYPES).toContain('signed');
    expect(LOYALTY_DISPLAY_STATUSES).toContain('none');
  });
});