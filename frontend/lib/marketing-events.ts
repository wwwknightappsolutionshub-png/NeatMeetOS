/**
 * Lightweight marketing conversion events.
 * Reuses dataLayer when present; no new analytics product.
 */
export type MarketingEventName =
  | 'landing_page_view'
  | 'growth_assessment_cta_clicked'
  | 'trial_cta_clicked'
  | 'pricing_cta_clicked'
  | 'faq_opened'
  | 'nav_cta_clicked';

export function trackMarketingEvent(
  name: MarketingEventName,
  props?: Record<string, string | number | boolean | undefined>,
): void {
  if (typeof window === 'undefined') return;
  const payload = { event: name, ...props, source: 'neatmeet_landing_v2' };
  const w = window as Window & { dataLayer?: unknown[] };
  if (Array.isArray(w.dataLayer)) {
    w.dataLayer.push(payload);
  }
  try {
    window.dispatchEvent(new CustomEvent('nm:marketing', { detail: payload }));
  } catch {
    /* ignore */
  }
}
