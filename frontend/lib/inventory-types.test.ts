import { describe, expect, it } from 'vitest';
import {
  CONSUMPTION_MODES,
  INVENTORY_ITEM_TYPES,
  INVENTORY_MOVEMENT_TYPES,
  formatMoneyCents,
  formatQuantity,
} from '@/lib/inventory-types';

describe('inventory types', () => {
  it('includes retail and professional item types', () => {
    expect(INVENTORY_ITEM_TYPES).toContain('retail');
    expect(INVENTORY_ITEM_TYPES).toContain('professional');
  });

  it('includes movement types for module 7A', () => {
    expect(INVENTORY_MOVEMENT_TYPES).toContain('purchase_receipt');
    expect(INVENTORY_MOVEMENT_TYPES).toContain('service_consumption');
  });

  it('includes consumption modes', () => {
    expect(CONSUMPTION_MODES).toContain('fixed');
    expect(CONSUMPTION_MODES).toContain('estimated');
  });

  it('formats money and quantities', () => {
    expect(formatMoneyCents(1250)).toBe('£12.50');
    expect(formatQuantity('10.000')).toBe('10');
  });
});
