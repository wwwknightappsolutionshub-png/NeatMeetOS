import { describe, expect, it } from 'vitest';
import { canOptimizeRemoteImage, optimizeUnsplashUrl } from '@/lib/remote-image';

describe('remote-image', () => {
  it('shrinks unsplash query params', () => {
    const url = optimizeUnsplashUrl(
      'https://images.unsplash.com/photo-1?w=1800&q=80',
      1200,
      70,
    );
    expect(url).toContain('w=1200');
    expect(url).toContain('q=70');
  });

  it('detects optimizable remote images', () => {
    expect(canOptimizeRemoteImage('/book/hero.jpg')).toBe(true);
    expect(
      canOptimizeRemoteImage('https://neatmeetos.com/storage/branding/hero.jpg'),
    ).toBe(true);
    expect(canOptimizeRemoteImage('https://cdn.example.com/x.jpg')).toBe(false);
  });
});
