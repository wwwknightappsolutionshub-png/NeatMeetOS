export interface ShopProduct {
  id: string;
  title: string;
  description: string | null;
  image_url: string | null;
  price_cents: number;
  inventory_item_id: string;
  available_quantity?: number;
}

export interface ShopCartLine {
  product: ShopProduct;
  quantity: number;
}

export interface ShopOrder {
  id: string;
  order_number: string;
  status: string;
  payment_method: string;
  payment_status: string;
  customer_name: string | null;
  customer_email: string | null;
  customer_phone: string | null;
  notes: string | null;
  subtotal_cents: number;
  total_cents: number;
  public_token?: string;
  location_id?: string;
  lines?: ShopOrderLine[];
  created_at?: string | null;
}

export interface ShopOrderLine {
  id: string;
  ecommerce_product_id: string;
  inventory_item_id: string;
  title_snapshot: string;
  quantity: number;
  unit_price_cents: number;
  line_total_cents: number;
}

export interface AdminEcommerceProduct {
  id: string;
  inventory_item_id: string;
  inventory_item?: {
    id: string;
    name: string;
    sku: string | null;
    item_type: string;
    status: string;
  } | null;
  title: string;
  description: string | null;
  image_url: string | null;
  price_cents: number;
  show_on_booking_carousel: boolean;
  sort_order: number;
  status: string;
  created_at: string | null;
  updated_at: string | null;
}

export function formatShopMoney(cents: number): string {
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: 'GBP',
  }).format(cents / 100);
}

const CART_PREFIX = 'neatmeet_shop_cart:';

export function loadShopCart(tenantSlug: string): ShopCartLine[] {
  if (typeof window === 'undefined') return [];
  try {
    const raw = localStorage.getItem(`${CART_PREFIX}${tenantSlug}`);
    if (!raw) return [];
    const parsed = JSON.parse(raw) as ShopCartLine[];
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

export function saveShopCart(tenantSlug: string, lines: ShopCartLine[]): void {
  if (typeof window === 'undefined') return;
  localStorage.setItem(`${CART_PREFIX}${tenantSlug}`, JSON.stringify(lines));
}

export function clearShopCart(tenantSlug: string): void {
  if (typeof window === 'undefined') return;
  localStorage.removeItem(`${CART_PREFIX}${tenantSlug}`);
}
