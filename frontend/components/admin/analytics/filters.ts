import type { AnalyticsFilterValues } from './AnalyticsFilterBar';

/**
 * Default filter state. Dates are left blank so the backend applies its own
 * "last 30 days" default; the resolved window is echoed back in `range`.
 */
export function emptyAnalyticsFilters(): AnalyticsFilterValues {
  return { from: '', to: '', location_id: '', provider_id: '' };
}
