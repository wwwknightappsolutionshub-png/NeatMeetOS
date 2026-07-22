/** Referral attribution cookie for marketing landing → trial lead. */

const COOKIE = 'nm_ref';
const MAX_AGE = 60 * 60 * 24 * 30; // 30 days

export function persistReferralCode(code: string): void {
  const cleaned = code.trim().toUpperCase();
  if (!cleaned || typeof document === 'undefined') return;
  document.cookie = `${COOKIE}=${encodeURIComponent(cleaned)}; path=/; max-age=${MAX_AGE}; SameSite=Lax`;
}

export function readReferralCode(): string | null {
  if (typeof document === 'undefined') return null;
  const match = document.cookie
    .split(';')
    .map((c) => c.trim())
    .find((c) => c.startsWith(`${COOKIE}=`));
  if (!match) return null;
  const value = decodeURIComponent(match.slice(COOKIE.length + 1)).trim().toUpperCase();
  return value || null;
}

export function resolveReferralCode(queryRef?: string | null): string | null {
  const fromQuery = (queryRef ?? '').trim().toUpperCase();
  if (fromQuery) {
    persistReferralCode(fromQuery);
    return fromQuery;
  }
  return readReferralCode();
}
