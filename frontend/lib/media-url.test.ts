import { describe, expect, it } from 'vitest';
import { resolveMediaUrl } from '@/lib/media-url';

describe('resolveMediaUrl', () => {
  it('rewrites /storage paths to the API origin', () => {
    expect(resolveMediaUrl('/storage/branding/x.jpg')).toBe(
      'http://localhost:8000/storage/branding/x.jpg',
    );
  });

  it('rewrites localhost storage URLs missing the API port', () => {
    expect(
      resolveMediaUrl('http://localhost/storage/branding/x.jpg'),
    ).toBe('http://localhost:8000/storage/branding/x.jpg');
  });

  it('leaves external https URLs unchanged', () => {
    expect(resolveMediaUrl('https://cdn.example.com/logo.png')).toBe(
      'https://cdn.example.com/logo.png',
    );
  });
});
