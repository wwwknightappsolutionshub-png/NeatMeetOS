export const SUPPORTED_CURRENCIES = [
  'GBP',
  'NGN',
  'USD',
  'EUR',
  'CAD',
  'AUD',
  'NZD',
  'ZAR',
  'KES',
  'GHS',
  'INR',
] as const;

export type CurrencyCode = (typeof SUPPORTED_CURRENCIES)[number];

const SYMBOLS: Record<string, string> = {
  GBP: '£',
  EUR: '€',
  USD: '$',
  CAD: '$',
  AUD: '$',
  NZD: '$',
  NGN: '₦',
  ZAR: 'R',
  KES: 'KSh',
  GHS: 'GH₵',
  INR: '₹',
};

export function normalizeCurrency(code?: string | null): CurrencyCode {
  const upper = (code ?? 'GBP').toUpperCase();
  return (SUPPORTED_CURRENCIES as readonly string[]).includes(upper)
    ? (upper as CurrencyCode)
    : 'GBP';
}

export function currencySymbol(code?: string | null): string {
  return SYMBOLS[normalizeCurrency(code)] ?? `${normalizeCurrency(code)} `;
}

export function formatMoneyMinor(cents: number | null | undefined, currency?: string | null): string {
  if (cents == null) return '—';
  const code = normalizeCurrency(currency);
  try {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency: code,
    }).format(cents / 100);
  } catch {
    return `${currencySymbol(code)}${(cents / 100).toFixed(2)}`;
  }
}

export function centsToMajorInput(cents: number | null | undefined): string {
  if (cents == null) return '';
  return (cents / 100).toFixed(2);
}

export function majorInputToCents(value: string): number | null {
  const trimmed = value.trim();
  if (trimmed === '') return null;
  const n = Number(trimmed);
  if (!Number.isFinite(n) || n < 0) return null;
  return Math.round(n * 100);
}

export function priceFieldLabel(prefix: string, currency?: string | null): string {
  return `${prefix} (${normalizeCurrency(currency)})`;
}
