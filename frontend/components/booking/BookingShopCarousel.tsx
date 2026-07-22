'use client';

import { useCallback, useEffect, useState } from 'react';
import {
  clearShopCart,
  formatShopMoney,
  loadShopCart,
  saveShopCart,
  type ShopCartLine,
  type ShopProduct,
} from '@/lib/ecommerce-types';
import { fetchShopProducts, placeShopOrder } from '@/services/ecommerce.service';

interface BookingShopCarouselProps {
  tenantSlug: string;
  locationId: string;
}

const CARD_WIDTH = 240;
const CARD_GAP = 16;
const STEP = CARD_WIDTH + CARD_GAP;

const FALLBACK_IMAGES = [
  '/shop/shampoo.svg',
  '/shop/conditioner.svg',
  '/shop/serum.svg',
  '/shop/mask.svg',
];

function productImageSrc(product: ShopProduct, index: number): string {
  if (product.image_url) return product.image_url;
  const title = product.title.toLowerCase();
  if (title.includes('shampoo')) return '/shop/shampoo.svg';
  if (title.includes('condition')) return '/shop/conditioner.svg';
  if (title.includes('serum')) return '/shop/serum.svg';
  if (title.includes('mask')) return '/shop/mask.svg';
  return FALLBACK_IMAGES[index % FALLBACK_IMAGES.length];
}

export function BookingShopCarousel({ tenantSlug, locationId }: BookingShopCarouselProps) {
  const [products, setProducts] = useState<ShopProduct[]>([]);
  const [cart, setCart] = useState<ShopCartLine[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showCheckout, setShowCheckout] = useState(false);
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [orderRef, setOrderRef] = useState<string | null>(null);
  const [activeIndex, setActiveIndex] = useState(0);
  const [paused, setPaused] = useState(false);

  const load = useCallback(() => {
    const slug = typeof tenantSlug === 'string' ? tenantSlug : '';
    if (!slug) return;
    setLoading(true);
    setError(null);
    fetchShopProducts(slug, {
      location_id: locationId || undefined,
      carousel: true,
    })
      .then((rows) => {
        setProducts(Array.isArray(rows) ? rows : []);
        setActiveIndex(0);
      })
      .catch((e) => {
        setProducts([]);
        setError(e instanceof Error ? e.message : 'Unable to load products');
      })
      .finally(() => setLoading(false));
  }, [tenantSlug, locationId]);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    setCart(loadShopCart(tenantSlug));
  }, [tenantSlug]);

  const goNext = useCallback(() => {
    setActiveIndex((i) => (products.length === 0 ? 0 : (i + 1) % products.length));
  }, [products.length]);

  const goPrev = useCallback(() => {
    setActiveIndex((i) =>
      products.length === 0 ? 0 : (i - 1 + products.length) % products.length,
    );
  }, [products.length]);

  useEffect(() => {
    if (paused || products.length < 2) return;
    const id = window.setInterval(goNext, 3000);
    return () => window.clearInterval(id);
  }, [paused, products.length, goNext]);

  function persist(next: ShopCartLine[]) {
    setCart(next);
    saveShopCart(tenantSlug, next);
  }

  function addToCart(product: ShopProduct) {
    const existing = cart.find((l) => l.product.id === product.id);
    if (existing) {
      persist(
        cart.map((l) =>
          l.product.id === product.id ? { ...l, quantity: l.quantity + 1 } : l,
        ),
      );
    } else {
      persist([...cart, { product, quantity: 1 }]);
    }
  }

  function changeQty(productId: string, quantity: number) {
    if (quantity <= 0) {
      persist(cart.filter((l) => l.product.id !== productId));
      return;
    }
    persist(cart.map((l) => (l.product.id === productId ? { ...l, quantity } : l)));
  }

  const cartTotal = cart.reduce((sum, l) => sum + l.product.price_cents * l.quantity, 0);

  async function checkout(e: React.FormEvent) {
    e.preventDefault();
    if (!locationId || cart.length === 0) return;
    setSubmitting(true);
    setError(null);
    try {
      const order = await placeShopOrder(tenantSlug, {
        location_id: locationId,
        customer_name: name,
        customer_email: email,
        customer_phone: phone || undefined,
        lines: cart.map((l) => ({
          ecommerce_product_id: l.product.id,
          quantity: l.quantity,
        })),
      });
      clearShopCart(tenantSlug);
      setCart([]);
      setShowCheckout(false);
      setOrderRef(order.order_number);
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Order failed');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <section
      className="mt-12 border-t border-[var(--book-line)] pt-10"
      onMouseEnter={() => setPaused(true)}
      onMouseLeave={() => setPaused(false)}
      onFocusCapture={() => setPaused(true)}
      onBlurCapture={(e) => {
        if (!e.currentTarget.contains(e.relatedTarget as Node | null)) {
          setPaused(false);
        }
      }}
    >
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 className="book-display text-2xl font-bold sm:text-3xl">Take home</h2>
          <p className="mt-1 text-sm text-[var(--book-muted)]">
            Click &amp; collect · pay cash in salon
          </p>
        </div>
        <div className="flex items-center gap-2">
          <button
            type="button"
            aria-label="Previous product"
            className="rounded-md border border-[var(--book-line)] bg-white px-3 py-1.5 text-sm font-semibold hover:bg-[var(--book-wash)] disabled:opacity-40"
            onClick={goPrev}
            disabled={products.length < 2}
          >
            ‹
          </button>
          <button
            type="button"
            aria-label="Next product"
            className="rounded-md border border-[var(--book-line)] bg-white px-3 py-1.5 text-sm font-semibold hover:bg-[var(--book-wash)] disabled:opacity-40"
            onClick={goNext}
            disabled={products.length < 2}
          >
            ›
          </button>
          {cart.length > 0 ? (
            <button
              type="button"
              className="rounded-md bg-[var(--book-moss)] px-3 py-1.5 text-sm font-semibold text-white"
              onClick={() => setShowCheckout(true)}
            >
              Cart ({cart.reduce((n, l) => n + l.quantity, 0)}) · {formatShopMoney(cartTotal)}
            </button>
          ) : null}
        </div>
      </div>

      {error ? (
        <p className="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
          Could not load shop products: {error}
        </p>
      ) : null}
      {orderRef ? (
        <p className="mt-3 rounded-lg bg-[var(--book-wash)] px-3 py-2 text-sm text-[var(--book-ink)]">
          Order <strong>{orderRef}</strong> placed. Collect and pay in salon (cash).
        </p>
      ) : null}
      {loading && products.length === 0 ? (
        <p className="mt-5 text-sm text-[var(--book-muted)]">Loading retail products…</p>
      ) : null}
      {!loading && !error && products.length === 0 ? (
        <p className="mt-5 text-sm text-[var(--book-muted)]">
          No click &amp; collect products are listed for this salon yet.
        </p>
      ) : null}

      {products.length > 0 ? (
        <div className="relative mt-5 overflow-hidden">
          <div
            className="flex transition-transform duration-500 ease-out"
            style={{
              gap: CARD_GAP,
              transform: `translateX(-${activeIndex * STEP}px)`,
            }}
          >
            {products.map((product, index) => (
              <article
                key={product.id}
                className="shrink-0 rounded-2xl border border-[var(--book-line)] bg-white p-4 shadow-[var(--book-shadow)]"
                style={{ width: CARD_WIDTH }}
              >
                <div className="relative h-44 overflow-hidden rounded-xl bg-[var(--book-panel)]">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={productImageSrc(product, index)}
                    alt={product.title}
                    className="h-full w-full object-cover"
                  />
                </div>
                <h3 className="mt-3 line-clamp-2 text-base font-semibold text-[var(--book-ink)]">
                  {product.title}
                </h3>
                {product.description ? (
                  <p className="mt-1 line-clamp-2 text-xs text-[var(--book-muted)]">
                    {product.description}
                  </p>
                ) : null}
                <p className="mt-2 text-lg font-bold tabular-nums text-[var(--book-ink)]">
                  {formatShopMoney(product.price_cents)}
                </p>
                {product.available_quantity !== undefined ? (
                  <p className="mt-0.5 text-xs text-[var(--book-muted)]">
                    {product.available_quantity > 0
                      ? `${product.available_quantity} in stock`
                      : 'Out of stock'}
                  </p>
                ) : null}
                <button
                  type="button"
                  disabled={product.available_quantity === 0}
                  className="mt-3 w-full rounded-md bg-[var(--book-moss)] px-3 py-2.5 text-sm font-semibold text-white hover:bg-[var(--book-moss-deep)] disabled:opacity-40"
                  onClick={() => addToCart(product)}
                >
                  Add to cart
                </button>
              </article>
            ))}
          </div>

          <div className="mt-4 flex justify-center gap-1.5">
            {products.map((product, index) => (
              <button
                key={product.id}
                type="button"
                aria-label={`Show ${product.title}`}
                className={`h-2 rounded-full transition ${
                  index === activeIndex
                    ? 'w-6 bg-[var(--book-moss)]'
                    : 'w-2 bg-[var(--book-line)] hover:bg-[var(--book-muted)]'
                }`}
                onClick={() => setActiveIndex(index)}
              />
            ))}
          </div>
        </div>
      ) : null}

      {showCheckout ? (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center">
          <div className="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl bg-white p-5 shadow-xl">
            <div className="flex items-center justify-between">
              <h3 className="text-lg font-bold">Click &amp; collect</h3>
              <button
                type="button"
                className="text-sm text-zinc-500"
                onClick={() => setShowCheckout(false)}
              >
                Close
              </button>
            </div>
            <ul className="mt-4 space-y-2 text-sm">
              {cart.map((line) => (
                <li key={line.product.id} className="flex items-center justify-between gap-2">
                  <span className="min-w-0 flex-1 truncate">{line.product.title}</span>
                  <input
                    type="number"
                    min={1}
                    className="w-14 rounded border border-zinc-300 px-1 py-0.5 text-center"
                    value={line.quantity}
                    onChange={(e) => changeQty(line.product.id, Number(e.target.value))}
                  />
                  <span className="w-16 text-right tabular-nums">
                    {formatShopMoney(line.product.price_cents * line.quantity)}
                  </span>
                </li>
              ))}
            </ul>
            <p className="mt-3 text-right text-sm font-bold">
              Total {formatShopMoney(cartTotal)} · pay cash in salon
            </p>
            <form onSubmit={(e) => void checkout(e)} className="mt-4 grid gap-3">
              <input
                required
                placeholder="Your name"
                className="rounded-md border border-zinc-300 px-3 py-2 text-sm"
                value={name}
                onChange={(e) => setName(e.target.value)}
              />
              <input
                required
                type="email"
                placeholder="Email"
                className="rounded-md border border-zinc-300 px-3 py-2 text-sm"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
              />
              <input
                placeholder="Phone (optional)"
                className="rounded-md border border-zinc-300 px-3 py-2 text-sm"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
              />
              <button
                type="submit"
                disabled={submitting || !locationId}
                className="rounded-md bg-[var(--book-moss)] px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
              >
                {submitting ? 'Placing order…' : 'Place order'}
              </button>
            </form>
          </div>
        </div>
      ) : null}
    </section>
  );
}
