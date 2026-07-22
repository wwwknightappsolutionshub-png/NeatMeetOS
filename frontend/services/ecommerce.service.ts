import { api } from '@/lib/api-client';
import type {
  AdminEcommerceProduct,
  ShopOrder,
  ShopProduct,
} from '@/lib/ecommerce-types';

function publicOpts(tenantSlug: string, init?: RequestInit) {
  return {
    auth: false as const,
    tenant: false as const,
    headers: { 'X-Tenant-Slug': tenantSlug },
    ...init,
  };
}

const auth = { auth: true as const };

function unwrapList<T>(data: unknown): T[] {
  if (Array.isArray(data)) return data as T[];
  if (data && typeof data === 'object') {
    const nested = (data as { data?: unknown }).data;
    if (Array.isArray(nested)) return nested as T[];
    // Rare: JSON object with numeric keys
    const values = Object.values(data as Record<string, unknown>);
    if (values.length > 0 && values.every((v) => v && typeof v === 'object' && 'id' in (v as object))) {
      return values as T[];
    }
  }
  return [];
}

export async function fetchShopProducts(
  tenantSlug: string,
  params?: { location_id?: string; carousel?: boolean },
): Promise<ShopProduct[]> {
  const search = new URLSearchParams();
  if (params?.location_id) search.set('location_id', params.location_id);
  if (params?.carousel) search.set('carousel', '1');
  const q = search.toString() ? `?${search}` : '';
  const data = await api<ShopProduct[] | { data: ShopProduct[] }>(
    `/shop/products${q}`,
    publicOpts(tenantSlug),
  );
  return unwrapList(data);
}

export async function placeShopOrder(
  tenantSlug: string,
  payload: {
    location_id: string;
    customer_name: string;
    customer_email: string;
    customer_phone?: string;
    notes?: string;
    lines: { ecommerce_product_id: string; quantity: number }[];
  },
): Promise<ShopOrder> {
  return api<ShopOrder>('/shop/orders', {
    ...publicOpts(tenantSlug, {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  });
}

export async function fetchAdminEcommerceProducts(params?: {
  status?: string;
  search?: string;
}): Promise<AdminEcommerceProduct[]> {
  const search = new URLSearchParams();
  if (params?.status) search.set('status', params.status);
  if (params?.search) search.set('search', params.search);
  const q = search.toString() ? `?${search}` : '';
  const data = await api<AdminEcommerceProduct[] | { data: AdminEcommerceProduct[] }>(
    `/admin/ecommerce/products${q}`,
    auth,
  );
  return unwrapList(data);
}

export async function createAdminEcommerceProduct(payload: {
  inventory_item_id: string;
  title: string;
  description?: string;
  image_url?: string;
  price_cents: number;
  show_on_booking_carousel?: boolean;
  sort_order?: number;
}): Promise<AdminEcommerceProduct> {
  return api<AdminEcommerceProduct>('/admin/ecommerce/products', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function updateAdminEcommerceProduct(
  id: string,
  payload: Partial<{
    inventory_item_id: string;
    title: string;
    description: string | null;
    image_url: string | null;
    price_cents: number;
    show_on_booking_carousel: boolean;
    sort_order: number;
  }>,
): Promise<AdminEcommerceProduct> {
  return api<AdminEcommerceProduct>(`/admin/ecommerce/products/${id}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(payload),
  });
}

export async function setAdminEcommerceProductStatus(
  id: string,
  status: 'active' | 'archived',
): Promise<AdminEcommerceProduct> {
  return api<AdminEcommerceProduct>(`/admin/ecommerce/products/${id}/status`, {
    ...auth,
    method: 'PATCH',
    body: JSON.stringify({ status }),
  });
}

export async function fetchAdminEcommerceOrders(params?: {
  status?: string;
}): Promise<ShopOrder[]> {
  const search = new URLSearchParams();
  if (params?.status) search.set('status', params.status);
  const q = search.toString() ? `?${search}` : '';
  const data = await api<ShopOrder[] | { data: ShopOrder[] }>(
    `/admin/ecommerce/orders${q}`,
    auth,
  );
  return unwrapList(data);
}

export async function updateAdminEcommerceOrderStatus(
  id: string,
  payload: { status: string; payment_status?: string },
): Promise<ShopOrder> {
  return api<ShopOrder>(`/admin/ecommerce/orders/${id}/status`, {
    ...auth,
    method: 'PATCH',
    body: JSON.stringify(payload),
  });
}
