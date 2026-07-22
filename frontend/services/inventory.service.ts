import { api } from '@/lib/api-client';
import type {
  InventoryItem,
  InventoryLevel,
  InventoryMovement,
  InventorySupplier,
  ServiceConsumptionRule,
} from '@/lib/inventory-types';

const auth = { auth: true as const, tenant: true as const };

export async function fetchInventoryItems(params?: {
  status?: string;
  item_type?: string;
  search?: string;
}): Promise<InventoryItem[]> {
  const search = new URLSearchParams();
  if (params?.status) search.set('status', params.status);
  if (params?.item_type) search.set('item_type', params.item_type);
  if (params?.search) search.set('search', params.search);
  const query = search.toString();
  return api<InventoryItem[]>(`/admin/inventory/items${query ? `?${query}` : ''}`, auth);
}

export async function fetchInventoryItem(id: string): Promise<InventoryItem> {
  return api<InventoryItem>(`/admin/inventory/items/${id}`, auth);
}

export async function createInventoryItem(data: Partial<InventoryItem>): Promise<InventoryItem> {
  return api<InventoryItem>('/admin/inventory/items', { ...auth, method: 'POST', body: JSON.stringify(data) });
}

export async function updateInventoryItem(id: string, data: Partial<InventoryItem>): Promise<InventoryItem> {
  return api<InventoryItem>(`/admin/inventory/items/${id}`, { ...auth, method: 'PUT', body: JSON.stringify(data) });
}

export async function archiveInventoryItem(id: string): Promise<InventoryItem> {
  return api<InventoryItem>(`/admin/inventory/items/${id}/archive`, { ...auth, method: 'PATCH' });
}

export async function fetchInventoryLevels(params?: {
  location_id?: string;
  item_type?: string;
  low_stock?: boolean;
}): Promise<InventoryLevel[]> {
  const search = new URLSearchParams();
  if (params?.location_id) search.set('location_id', params.location_id);
  if (params?.item_type) search.set('item_type', params.item_type);
  if (params?.low_stock) search.set('low_stock', '1');
  const query = search.toString();
  return api<InventoryLevel[]>(`/admin/inventory/levels${query ? `?${query}` : ''}`, auth);
}

export async function updateInventoryItemLevel(
  itemId: string,
  locationId: string,
  data: { reorder_point?: number; reorder_target?: number; opening_quantity?: number },
): Promise<InventoryLevel> {
  return api<InventoryLevel>(`/admin/inventory/items/${itemId}/levels/${locationId}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function fetchInventoryMovements(params?: {
  inventory_item_id?: string;
  location_id?: string;
  movement_type?: string;
}): Promise<InventoryMovement[]> {
  const search = new URLSearchParams();
  if (params?.inventory_item_id) search.set('inventory_item_id', params.inventory_item_id);
  if (params?.location_id) search.set('location_id', params.location_id);
  if (params?.movement_type) search.set('movement_type', params.movement_type);
  const query = search.toString();
  return api<InventoryMovement[]>(`/admin/inventory/movements${query ? `?${query}` : ''}`, auth);
}

export async function recordInventoryMovement(data: {
  inventory_item_id: string;
  location_id: string;
  movement_type: string;
  quantity_delta: number;
  notes?: string;
  allow_negative?: boolean;
}): Promise<InventoryMovement> {
  return api<InventoryMovement>('/admin/inventory/movements', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function fetchInventorySuppliers(): Promise<InventorySupplier[]> {
  return api<InventorySupplier[]>('/admin/inventory/suppliers', auth);
}

export async function createInventorySupplier(data: Partial<InventorySupplier>): Promise<InventorySupplier> {
  return api<InventorySupplier>('/admin/inventory/suppliers', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function updateInventorySupplier(
  id: string,
  data: Partial<InventorySupplier>,
): Promise<InventorySupplier> {
  return api<InventorySupplier>(`/admin/inventory/suppliers/${id}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function fetchServiceConsumptionRules(params?: {
  booking_service_id?: string;
  inventory_item_id?: string;
}): Promise<ServiceConsumptionRule[]> {
  const search = new URLSearchParams();
  if (params?.booking_service_id) search.set('booking_service_id', params.booking_service_id);
  if (params?.inventory_item_id) search.set('inventory_item_id', params.inventory_item_id);
  const query = search.toString();
  return api<ServiceConsumptionRule[]>(
    `/admin/inventory/service-consumption-rules${query ? `?${query}` : ''}`,
    auth,
  );
}

export async function createServiceConsumptionRule(data: {
  booking_service_id: string;
  inventory_item_id: string;
  quantity_required: number;
  consumption_mode?: string;
  notes?: string;
}): Promise<ServiceConsumptionRule> {
  return api<ServiceConsumptionRule>('/admin/inventory/service-consumption-rules', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}
