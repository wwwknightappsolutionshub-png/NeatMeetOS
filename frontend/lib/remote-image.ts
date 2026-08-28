/**
 * Build smaller remote image URLs (Unsplash) for faster LCP.
 */
export function optimizeUnsplashUrl(
  url: string,
  width: number,
  quality = 70,
): string {
  if (!url.includes('images.unsplash.com')) {
    return url;
  }

  try {
    const parsed = new URL(url);
    parsed.searchParams.set('auto', 'format');
    parsed.searchParams.set('fit', 'crop');
    parsed.searchParams.set('w', String(width));
    parsed.searchParams.set('q', String(quality));
    return parsed.toString();
  } catch {
    return url;
  }
}

/** Whether Next.js <Image> can optimize this src via remotePatterns. */
export function canOptimizeRemoteImage(src: string): boolean {
  if (src.startsWith('/')) return true;
  if (src.includes('images.unsplash.com')) return true;
  return /\/storage\//.test(src);
}
