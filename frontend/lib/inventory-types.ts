export const INVENTORY_ITEM_TYPES = ['retail', 'professional'] as const;
export type InventoryItemType = (typeof INVENTORY_ITEM_TYPES)[number];

export const INVENTORY_ITEM_STATUSES = ['active', 'archived'] as const;
export type InventoryItemStatus = (typeof INVENTORY_ITEM_STATUSES)[number];

export const INVENTORY_MOVEMENT_TYPES = [
  'opening',
  'adjustment',
  'purchase_receipt',
  'sale',
  'service_consumption',
  'waste',
  'transfer_in',
  'transfer_out',
] as const;

export type InventoryMovementType = (typeof INVENTORY_MOVEMENT_TYPES)[number];

export const CONSUMPTION_MODES = ['fixed', 'optional', 'estimated'] as const;

export interface InventorySupplier {
  id: string;
  name: string;
  contact_name?: string | null;
  email?: string | null;
  phone?: string | null;
  website?: string | null;
  notes?: string | null;
  is_active: boolean;
}

export interface InventoryLevel {
  id: string;
  inventory_item_id: string;
  location_id: string;
  location?: { id: string; name: string } | null;
  item?: { id: string; name: string; sku?: string | null; item_type: string } | null;
  on_hand_quantity: string;
  reserved_quantity: string;
  reorder_point?: string | null;
  reorder_target?: string | null;
  is_low_stock: boolean;
  last_restocked_at?: string | null;
}

export interface InventoryMovement {
  id: string;
  inventory_item_id: string;
  location_id: string;
  movement_type: InventoryMovementType;
  quantity_delta: string;
  quantity_before?: string | null;
  quantity_after?: string | null;
  notes?: string | null;
  item?: { id: string; name: string } | null;
  location?: { id: string; name: string } | null;
  created_at?: string | null;
}

export interface ServiceConsumptionRule {
  id: string;
  booking_service_id: string;
  inventory_item_id: string;
  quantity_required: string;
  consumption_mode?: string | null;
  notes?: string | null;
  is_active: boolean;
  booking_service?: { id: string; name: string } | null;
  inventory_item?: { id: string; name: string; unit_label?: string | null } | null;
}

export interface InventoryItem {
  id: string;
  name: string;
  sku?: string | null;
  item_type: InventoryItemType;
  status: InventoryItemStatus;
  brand?: string | null;
  category?: string | null;
  description?: string | null;
  unit_label?: string | null;
  unit_size?: string | null;
  cost_price_cents?: number | null;
  retail_price_cents?: number | null;
  tax_code?: string | null;
  preferred_supplier_id?: string | null;
  preferred_supplier?: InventorySupplier | null;
  barcode?: string | null;
  levels?: InventoryLevel[];
  consumption_rules?: ServiceConsumptionRule[];
  is_low_stock?: boolean;
}

export function formatMoneyCents(cents?: number | null): string {
  if (cents == null) return '—';
  return `£${(cents / 100).toFixed(2)}`;
}

export function formatQuantity(qty: string | number): string {
  const n = typeof qty === 'string' ? parseFloat(qty) : qty;
  return Number.isInteger(n) ? String(n) : n.toFixed(3).replace(/\.?0+$/, '');
}
